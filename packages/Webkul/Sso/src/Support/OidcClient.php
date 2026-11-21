<?php

namespace Webkul\Sso\Support;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;
use Webkul\Email\Support\SmtpHostGuard;

/**
 * OpenID Connect authorization-code flow with PKCE, a state and a nonce, for any standard
 * provider (Google Workspace, Microsoft Entra ID, Okta, Keycloak...).
 *
 * Every address it calls comes from settings a business typed in, so each one must be HTTPS on a
 * public address, and the connection is pinned to the address that was checked.
 */
class OidcClient
{
    private const CACHE_SECONDS = 3600;

    private const LEEWAY_SECONDS = 60;

    /**
     * The provider's published configuration, checked to belong to the issuer that was configured.
     *
     * @return array{authorization_endpoint: string, token_endpoint: string, jwks_uri: string, issuer: string}
     */
    public function discover(string $issuer): array
    {
        return Cache::remember('sso.discovery.'.sha1($issuer), self::CACHE_SECONDS, function () use ($issuer): array {
            $document = $this->getJson($issuer.'/.well-known/openid-configuration');

            if (rtrim((string) ($document['issuer'] ?? ''), '/') !== $issuer) {
                throw new SsoException('provider', 'Discovery document names a different issuer.');
            }

            foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $key) {
                if (! is_string($document[$key] ?? null) || ! str_starts_with($document[$key], 'https://')) {
                    throw new SsoException('provider', "Discovery document has no usable {$key}.");
                }
            }

            return [
                'issuer' => $issuer,
                'authorization_endpoint' => $document['authorization_endpoint'],
                'token_endpoint' => $document['token_endpoint'],
                'jwks_uri' => $document['jwks_uri'],
            ];
        });
    }

    public function authorizationUrl(SsoSettings $settings, string $redirectUri, string $state, string $nonce, string $verifier, string $loginHint): string
    {
        $endpoint = $this->discover($settings->issuer)['authorization_endpoint'];

        $query = [
            'response_type' => 'code',
            'client_id' => $settings->clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => self::challenge($verifier),
            'code_challenge_method' => 'S256',
            'login_hint' => $loginHint,
        ];

        // Google Workspace: show only accounts of this domain in the account chooser.
        if (count($settings->domains) === 1 && str_contains($settings->issuer, 'accounts.google.com')) {
            $query['hd'] = $settings->domains[0];
        }

        return $endpoint.(str_contains($endpoint, '?') ? '&' : '?').http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Swap the one-time code for tokens and return the verified ID-token claims.
     *
     * @return array<string, mixed>
     */
    public function finish(SsoSettings $settings, string $code, string $verifier, string $redirectUri, string $nonce): array
    {
        $discovery = $this->discover($settings->issuer);

        $response = $this->request($discovery['token_endpoint'])
            ->asForm()
            ->post($discovery['token_endpoint'], [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'client_id' => $settings->clientId,
                'client_secret' => $settings->clientSecret,
                'code_verifier' => $verifier,
            ]);

        if (! $response->successful() || ! is_string($response->json('id_token'))) {
            throw new SsoException('provider', 'Token endpoint answered '.$response->status().' without an ID token.');
        }

        return $this->verifyIdToken($settings, $discovery, (string) $response->json('id_token'), $nonce);
    }

    /**
     * Signature against the provider's published keys, then issuer, audience, authorized party,
     * expiry (the JWT library) and nonce.
     *
     * @param  array{jwks_uri: string, issuer: string}  $discovery
     * @return array<string, mixed>
     */
    public function verifyIdToken(SsoSettings $settings, array $discovery, string $idToken, string $nonce): array
    {
        $keys = Cache::remember('sso.jwks.'.sha1($discovery['jwks_uri']), self::CACHE_SECONDS, fn (): array => $this->getJson($discovery['jwks_uri']));

        JWT::$leeway = self::LEEWAY_SECONDS;

        try {
            $claims = (array) JWT::decode($idToken, JWK::parseKeySet($keys, 'RS256'));
        } catch (Throwable $e) {
            // A key the cache has not seen yet: fetch the key set once more, then give up.
            Cache::forget('sso.jwks.'.sha1($discovery['jwks_uri']));

            try {
                $claims = (array) JWT::decode($idToken, JWK::parseKeySet($this->getJson($discovery['jwks_uri']), 'RS256'));
            } catch (Throwable $retry) {
                throw new SsoException('token', 'ID token signature or timing invalid: '.$retry->getMessage());
            }
        }

        if (rtrim((string) ($claims['iss'] ?? ''), '/') !== $discovery['issuer']) {
            throw new SsoException('token', 'ID token issuer does not match.');
        }

        $audience = (array) ($claims['aud'] ?? []);

        if (! in_array($settings->clientId, $audience, true)
            || (count($audience) > 1 && ($claims['azp'] ?? null) !== $settings->clientId)) {
            throw new SsoException('token', 'ID token was issued to a different application.');
        }

        if (! is_string($claims['nonce'] ?? null) || ! hash_equals($nonce, $claims['nonce'])) {
            throw new SsoException('token', 'ID token nonce does not match this sign-in.');
        }

        return $claims;
    }

    public static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public static function randomToken(int $length = 64): string
    {
        return Str::random($length);
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $url): array
    {
        $response = $this->request($url)->get($url);

        if (! $response->successful() || ! is_array($response->json())) {
            throw new SsoException('provider', "Provider address {$url} answered ".$response->status().'.');
        }

        return $response->json();
    }

    private function request(string $url): PendingRequest
    {
        $parts = parse_url($url);
        $host = (string) ($parts['host'] ?? '');

        if (($parts['scheme'] ?? '') !== 'https' && ! config('sso.allow_private_issuers')) {
            throw new SsoException('provider', 'Provider addresses must use HTTPS.');
        }

        $request = Http::timeout(10)->connectTimeout(5)->acceptJson()->withoutRedirecting();

        if (config('sso.allow_private_issuers')) {
            return $request;
        }

        try {
            $address = app(SmtpHostGuard::class)->resolvePublicAddress($host, 443, [443]);
        } catch (Throwable) {
            throw new SsoException('provider', "Provider host {$host} is not a public address.");
        }

        return $request->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$host}:443:{$address}"]]]);
    }
}
