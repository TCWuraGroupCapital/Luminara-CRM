<?php

namespace Webkul\Tma\Support;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use OpenSSLAsymmetricKey;
use Webkul\Ledger\Support\LedgerSigner;
use Webkul\Security\Support\SecurityAuditRecorder;
use Webkul\Tma\Exceptions\TmaAuthException;
use Webkul\Tma\Models\UserPasskey;
use Webkul\User\Models\User;

final class WebAuthnVerifier
{
    public const CHALLENGE_TTL_SECONDS = 300;

    private const P256_SPKI_HEADER = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00";

    private const ED25519_SPKI_PREFIX = "\x30\x2a\x30\x05\x06\x03\x2b\x65\x70\x03\x21\x00";

    private LedgerSigner $signer;

    public function __construct(?LedgerSigner $signer = null)
    {
        $this->signer = $signer ?? (function_exists('app') && app()->bound(LedgerSigner::class)
            ? app(LedgerSigner::class)
            : new LedgerSigner);
    }

    /**
     * Issue and store a single-use 32-byte base64url WebAuthn challenge for the user, optionally bound to a call id.
     */
    public function issueChallenge(int $userId, ?int $callId = null): string
    {
        $challenge = self::base64UrlEncode(random_bytes(32));

        Cache::put(
            self::challengeCacheKey($userId),
            ['challenge' => $challenge, 'call_id' => $callId],
            self::CHALLENGE_TTL_SECONDS
        );

        return $challenge;
    }

    /**
     * Build WebAuthn request/creation options including the active challenge and registered credential IDs.
     *
     * @return array{
     *     challenge: string,
     *     expires_in: int,
     *     rp_id: string,
     *     allow_credentials: array<int, array{type: string, id: string}>
     * }
     */
    public function challengeOptions(int $userId, string $rpId, ?int $callId = null): array
    {
        $challenge = $this->issueChallenge($userId, $callId);

        /** @var array<int, array{type: string, id: string}> $allowCredentials */
        $allowCredentials = UserPasskey::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->pluck('credential_id')
            ->map(static fn (string $credentialId): array => [
                'type' => 'public-key',
                'id' => $credentialId,
            ])
            ->values()
            ->all();

        return [
            'challenge' => $challenge,
            'expires_in' => self::CHALLENGE_TTL_SECONDS,
            'rp_id' => $rpId,
            'allow_credentials' => $allowCredentials,
        ];
    }

    /**
     * Validate, normalize, and persist a newly registered passkey from either an attested CBOR `attestation_object`
     * (all W3C browsers/WebViews including Safari, iOS WKWebView, Firefox, and Ed25519 authenticators) or explicit SPKI/raw `public_key`.
     *
     * @param  array<string, mixed>  $input
     */
    public function registerPasskey(
        User $user,
        array $input,
        string $expectedOrigin,
        string $expectedRpId,
        string $defaultLabel = 'Hardware Passkey',
    ): UserPasskey {
        $credentialId = is_string($input['credential_id'] ?? null) ? trim($input['credential_id']) : '';
        $alg = is_string($input['alg'] ?? null) ? strtolower(trim($input['alg'])) : '';
        $publicKey = is_string($input['public_key'] ?? null) ? trim($input['public_key']) : '';
        $attestationObject = is_string($input['attestation_object'] ?? null) ? trim($input['attestation_object']) : '';
        $clientDataJson = is_string($input['client_data_json'] ?? null) ? trim($input['client_data_json']) : '';
        $labelInput = is_string($input['label'] ?? null) ? trim($input['label']) : '';
        $label = $labelInput !== '' ? mb_substr($labelInput, 0, 80, 'UTF-8') : $defaultLabel;

        $hasAttestation = $attestationObject !== '';
        $hasDirectKey = in_array($alg, [UserPasskey::ALG_ES256, UserPasskey::ALG_ED25519], true) && $publicKey !== '';

        if ($credentialId === '' || strlen($credentialId) > 255 || $clientDataJson === '' || (! $hasAttestation && ! $hasDirectKey)) {
            throw new TmaAuthException('invalid_params', trans('tma::app.passkeys.invalid'));
        }

        $this->verifyRegistrationClientData($user->id, $clientDataJson, $expectedOrigin);

        if ($hasAttestation) {
            $attested = $this->extractAttestedPublicKey($attestationObject, $credentialId, $expectedRpId);
            $alg = $attested['alg'];
            $normalizedKey = $attested['public_key'];
        } else {
            $normalizedKey = $this->normalizePublicKey($alg, $publicKey);
        }

        if (UserPasskey::query()->where('credential_id', $credentialId)->exists()) {
            throw new TmaAuthException('duplicate_credential', trans('tma::app.passkeys.duplicate'));
        }

        try {
            /** @var UserPasskey $passkey */
            $passkey = UserPasskey::query()->create([
                'user_id' => $user->id,
                'credential_id' => $credentialId,
                'label' => $label,
                'alg' => $alg,
                'public_key' => $normalizedKey,
                'sign_count' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new TmaAuthException('duplicate_credential', trans('tma::app.passkeys.duplicate'));
        }

        SecurityAuditRecorder::record(
            'tma.passkey.registered',
            'user_passkey',
            $passkey->id,
            ['alg' => $passkey->alg, 'label' => $passkey->label],
            $user->id,
            (string) $user->getAttribute('email')
        );

        return $passkey;
    }

    /**
     * Verify a registration `clientDataJSON` against the user's issued challenge and origin.
     */
    public function verifyRegistrationClientData(int $userId, string $clientDataJsonB64, ?string $expectedOrigin = null): void
    {
        $expectedChallenge = $this->pullChallenge($userId);

        $clientDataRaw = self::base64UrlDecode($clientDataJsonB64);
        $clientData = json_decode($clientDataRaw, true);

        if (! is_array($clientData) || ($clientData['type'] ?? null) !== 'webauthn.create') {
            throw new TmaAuthException('invalid_client_data', 'WebAuthn clientDataJSON type must be webauthn.create.');
        }

        $this->assertValidOrigin($clientData['origin'] ?? null, $expectedOrigin);

        $receivedChallenge = is_string($clientData['challenge'] ?? null) ? rtrim($clientData['challenge'], '=') : '';

        if ($receivedChallenge === '' || ! hash_equals(rtrim($expectedChallenge, '='), $receivedChallenge)) {
            throw new TmaAuthException('challenge_mismatch', 'WebAuthn registration challenge mismatch.');
        }
    }

    /**
     * Parse CBOR `attestationObject`, verify `rpIdHash`, `UP`, `AT`, and attested `credentialId`,
     * and extract the attested COSE public key (`ES256` P-256 or `Ed25519`).
     *
     * @return array{alg: string, public_key: string}
     */
    public function extractAttestedPublicKey(
        string $attestationObjectB64,
        string $expectedCredentialIdB64,
        string $expectedRpId,
    ): array {
        $rawBytes = self::base64UrlDecode($attestationObjectB64);
        $offset = 0;
        $decoded = self::decodeCbor($rawBytes, $offset);

        if (! is_array($decoded) || ! isset($decoded['authData']) || ! is_string($decoded['authData'])) {
            throw new TmaAuthException('invalid_attestation', 'WebAuthn attestationObject is missing authData.');
        }

        $authData = $decoded['authData'];

        if (strlen($authData) < 56) {
            throw new TmaAuthException('invalid_attestation', 'WebAuthn registration authData is truncated.');
        }

        if ($expectedRpId !== '' && ! hash_equals(substr($authData, 0, 32), hash('sha256', strtolower($expectedRpId), true))) {
            throw new TmaAuthException('rp_id_mismatch', 'WebAuthn registration rpIdHash mismatch.');
        }

        $flags = ord($authData[32]);

        if (($flags & 0x01) !== 0x01 || ($flags & 0x40) !== 0x40) {
            throw new TmaAuthException('invalid_attestation', 'WebAuthn registration requires User Presence and Attested Credential Data.');
        }

        $credIdLen = (ord($authData[53]) << 8) | ord($authData[54]);

        if ($credIdLen < 1 || strlen($authData) < 55 + $credIdLen + 1) {
            throw new TmaAuthException('invalid_attestation', 'WebAuthn attested credentialId length is invalid.');
        }

        $attestedCredId = substr($authData, 55, $credIdLen);
        $expectedCredBytes = self::base64UrlDecode($expectedCredentialIdB64);

        if (! hash_equals($expectedCredBytes, $attestedCredId)) {
            throw new TmaAuthException('credential_id_mismatch', 'WebAuthn attested credentialId does not match submitted credential_id.');
        }

        $coseBytes = substr($authData, 55 + $credIdLen);
        $coseOffset = 0;
        $coseKey = self::decodeCbor($coseBytes, $coseOffset);

        if (! is_array($coseKey)) {
            throw new TmaAuthException('invalid_public_key', 'WebAuthn COSE credentialPublicKey is malformed.');
        }

        $kty = $coseKey[1] ?? null;
        $coseAlg = $coseKey[3] ?? null;
        $crv = $coseKey[-1] ?? null;
        $x = $coseKey[-2] ?? null;
        $y = $coseKey[-3] ?? null;

        if ($kty === 2 && $coseAlg === -7 && $crv === 1 && is_string($x) && is_string($y) && strlen($x) === 32 && strlen($y) === 32) {
            $spkiDer = self::P256_SPKI_HEADER."\x04".$x.$y;

            return [
                'alg' => UserPasskey::ALG_ES256,
                'public_key' => $this->normalizePublicKey(UserPasskey::ALG_ES256, self::base64UrlEncode($spkiDer)),
            ];
        }

        if ($kty === 1 && $coseAlg === -8 && $crv === 6 && is_string($x) && strlen($x) === 32) {
            return [
                'alg' => UserPasskey::ALG_ED25519,
                'public_key' => $this->normalizePublicKey(UserPasskey::ALG_ED25519, self::base64UrlEncode($x)),
            ];
        }

        throw new TmaAuthException('unsupported_algorithm', 'Passkey authenticator returned an unsupported COSE key algorithm.');
    }

    /**
     * Normalize and validate a passkey public key into canonical storage format:
     * - `es256`: PEM SubjectPublicKeyInfo verified by OpenSSL EC P-256 (`prime256v1`) parser
     * - `ed25519`: base64url 32-byte raw public key
     */
    public function normalizePublicKey(string $alg, string $publicKey): string
    {
        $normalizedAlg = strtolower(trim($alg));
        $trimmedKey = trim($publicKey);

        if ($trimmedKey === '') {
            throw new TmaAuthException('invalid_public_key', 'Passkey public key cannot be empty.');
        }

        if ($normalizedAlg === UserPasskey::ALG_ES256) {
            if (str_contains($trimmedKey, '-----BEGIN PUBLIC KEY-----')) {
                $pem = preg_replace('/\r\n?/', "\n", $trimmedKey) ?? $trimmedKey;
            } else {
                $der = self::decodeFlexibleBase64($trimmedKey);
                $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n").'-----END PUBLIC KEY-----';
            }

            $keyResource = @openssl_pkey_get_public($pem);

            if (! $keyResource instanceof OpenSSLAsymmetricKey) {
                throw new TmaAuthException('invalid_public_key', 'Invalid ES256 SubjectPublicKeyInfo public key.');
            }

            $details = openssl_pkey_get_details($keyResource);

            if (
                ! is_array($details)
                || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_EC
                || ($details['ec']['curve_name'] ?? '') !== 'prime256v1'
            ) {
                throw new TmaAuthException('invalid_public_key', 'ES256 passkey requires an elliptic-curve P-256 public key.');
            }

            return trim($pem);
        }

        if ($normalizedAlg === UserPasskey::ALG_ED25519) {
            if (str_contains($trimmedKey, '-----BEGIN PUBLIC KEY-----')) {
                $stripped = preg_replace('/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/', '', $trimmedKey) ?? '';
                $rawBytes = base64_decode($stripped, true);

                if ($rawBytes === false) {
                    throw new TmaAuthException('invalid_public_key', 'Invalid PEM Ed25519 public key.');
                }
            } else {
                $rawBytes = self::decodeFlexibleBase64($trimmedKey);
            }

            if (strlen($rawBytes) === 44 && str_starts_with($rawBytes, self::ED25519_SPKI_PREFIX)) {
                $rawBytes = substr($rawBytes, 12, 32);
            }

            if (strlen($rawBytes) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                throw new TmaAuthException('invalid_public_key', 'Ed25519 passkey requires a 32-byte public key.');
            }

            return self::base64UrlEncode($rawBytes);
        }

        throw new TmaAuthException('unsupported_algorithm', "Unsupported passkey algorithm [{$alg}].");
    }

    /**
     * Verify a W3C WebAuthn assertion against a registered `UserPasskey` and return a self-contained
     * human signer proof suitable for sealing inside `AgentActionCall.signer_proof` and `DataBlock`.
     *
     * @param  array<string, mixed>  $assertion
     * @return array{
     *     webauthn_verified: bool,
     *     alg: string,
     *     pubkey: string,
     *     credential_id: string,
     *     sign_count: int,
     *     authenticator_data: string,
     *     client_data_hash: string,
     *     signed_data: string,
     *     signature: string,
     *     proof: string,
     *     envelope: string
     * }
     */
    public function verifyAssertion(
        int $userId,
        array $assertion,
        ?string $expectedOrigin = null,
        ?string $expectedRpId = null,
        ?int $expectedCallId = null,
    ): array {
        $expectedChallenge = $this->pullChallenge($userId, $expectedCallId);

        $credentialId = isset($assertion['credential_id']) && is_string($assertion['credential_id'])
            ? trim($assertion['credential_id'])
            : '';
        $clientDataB64 = isset($assertion['client_data_json']) && is_string($assertion['client_data_json'])
            ? trim($assertion['client_data_json'])
            : '';
        $authDataB64 = isset($assertion['authenticator_data']) && is_string($assertion['authenticator_data'])
            ? trim($assertion['authenticator_data'])
            : '';
        $signatureB64 = isset($assertion['signature']) && is_string($assertion['signature'])
            ? trim($assertion['signature'])
            : '';

        if ($credentialId === '' || $clientDataB64 === '' || $authDataB64 === '' || $signatureB64 === '') {
            throw new TmaAuthException('invalid_assertion', 'WebAuthn assertion is missing required fields.');
        }

        /** @var UserPasskey|null $passkey */
        $passkey = UserPasskey::query()
            ->where('user_id', $userId)
            ->where('credential_id', $credentialId)
            ->first();

        if (! $passkey instanceof UserPasskey) {
            throw new TmaAuthException('unknown_passkey', 'Passkey credential is not registered for this user.');
        }

        $clientDataRawBytes = self::base64UrlDecode($clientDataB64);
        $clientData = json_decode($clientDataRawBytes, true);

        if (! is_array($clientData) || ($clientData['type'] ?? null) !== 'webauthn.get') {
            throw new TmaAuthException('invalid_client_data', 'WebAuthn clientDataJSON type must be webauthn.get.');
        }

        $originHost = $this->assertValidOrigin($clientData['origin'] ?? null, $expectedOrigin);

        $receivedChallenge = is_string($clientData['challenge'] ?? null) ? rtrim($clientData['challenge'], '=') : '';

        if ($receivedChallenge === '' || ! hash_equals(rtrim($expectedChallenge, '='), $receivedChallenge)) {
            throw new TmaAuthException('challenge_mismatch', 'WebAuthn assertion challenge mismatch.');
        }

        $authDataBytes = self::base64UrlDecode($authDataB64);

        if (strlen($authDataBytes) < 37) {
            throw new TmaAuthException('invalid_authenticator_data', 'WebAuthn authenticatorData must be at least 37 bytes.');
        }

        $rpIdToVerify = $expectedRpId !== null && $expectedRpId !== '' ? strtolower($expectedRpId) : $originHost;

        if (! hash_equals(substr($authDataBytes, 0, 32), hash('sha256', $rpIdToVerify, true))) {
            throw new TmaAuthException('rp_id_mismatch', 'WebAuthn authenticatorData rpIdHash mismatch.');
        }

        $flags = ord($authDataBytes[32]);

        if (($flags & 0x01) !== 0x01) {
            throw new TmaAuthException('user_presence_required', 'WebAuthn authenticator did not assert User Presence.');
        }

        /** @var array{count: int}|false $unpacked */
        $unpacked = unpack('Ncount', substr($authDataBytes, 33, 4));
        $incomingSignCount = is_array($unpacked) ? (int) $unpacked['count'] : 0;

        if (($passkey->sign_count > 0 || $incomingSignCount > 0) && $incomingSignCount <= $passkey->sign_count) {
            throw new TmaAuthException('cloned_authenticator', 'WebAuthn signature counter did not advance monotonically.');
        }

        $clientDataHash = hash('sha256', $clientDataRawBytes, true);
        $signedData = $authDataBytes.$clientDataHash;
        $signatureBytes = self::base64UrlDecode($signatureB64);
        $encodedSignature = self::base64UrlEncode($signatureBytes);

        if (! $this->signer->verify($passkey->alg, $passkey->public_key, $signedData, $encodedSignature)) {
            throw new TmaAuthException('invalid_webauthn_signature', 'WebAuthn cryptographic signature verification failed.');
        }

        $updatedSignCount = $incomingSignCount > $passkey->sign_count ? $incomingSignCount : $passkey->sign_count;

        $passkey->update([
            'sign_count' => $updatedSignCount,
            'last_used_at' => now(),
        ]);

        $encodedAuthData = self::base64UrlEncode($authDataBytes);
        $hexClientDataHash = bin2hex($clientDataHash);
        $encodedSignedData = self::base64UrlEncode($signedData);

        return [
            'webauthn_verified' => true,
            'alg' => $passkey->alg,
            'pubkey' => $passkey->public_key,
            'credential_id' => $passkey->credential_id,
            'sign_count' => $updatedSignCount,
            'authenticator_data' => $encodedAuthData,
            'client_data_hash' => $hexClientDataHash,
            'signed_data' => $encodedSignedData,
            'signature' => $encodedSignature,
            'proof' => $encodedSignature,
            'envelope' => "webauthn:v1:{$passkey->alg}:{$passkey->credential_id}:{$hexClientDataHash}:{$encodedSignature}",
        ];
    }

    public static function base64UrlEncode(string $bytes): string
    {
        return LedgerSigner::base64UrlEncode($bytes);
    }

    public static function base64UrlDecode(string $encoded): string
    {
        $trimmed = trim($encoded);

        if ($trimmed === '') {
            throw new TmaAuthException('invalid_base64url', 'Malformed base64url value in WebAuthn payload.');
        }

        $decoded = LedgerSigner::base64UrlDecode($trimmed);

        if ($decoded === null) {
            throw new TmaAuthException('invalid_base64url', 'Malformed base64url value in WebAuthn payload.');
        }

        return $decoded;
    }

    public static function p1363ToDer(string $signatureBytes): string
    {
        return strlen($signatureBytes) === 64
            ? LedgerSigner::p1363ToDer($signatureBytes)
            : $signatureBytes;
    }

    private function pullChallenge(int $userId, ?int $expectedCallId = null): string
    {
        $stored = Cache::pull(self::challengeCacheKey($userId));

        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        if (! is_array($stored) || ! is_string($stored['challenge'] ?? null) || $stored['challenge'] === '') {
            throw new TmaAuthException('missing_webauthn_challenge', 'No active WebAuthn challenge found or challenge expired.');
        }

        $boundCallId = isset($stored['call_id']) && is_numeric($stored['call_id']) ? (int) $stored['call_id'] : null;

        if ($boundCallId !== $expectedCallId) {
            throw new TmaAuthException('challenge_call_mismatch', 'WebAuthn challenge must match the target action call.');
        }

        return $stored['challenge'];
    }

    private static function decodeCbor(string $bytes, int &$offset, int $depth = 0): mixed
    {
        if ($depth > 8 || $offset >= strlen($bytes)) {
            throw new TmaAuthException('invalid_attestation', 'Malformed CBOR data in WebAuthn payload.');
        }

        $initial = ord($bytes[$offset++]);
        $major = $initial >> 5;
        $info = $initial & 0x1F;

        if ($major === 7) {
            return match ($info) {
                20 => false,
                21 => true,
                22, 23 => null,
                25 => self::skipBytes($bytes, $offset, 2),
                26 => self::skipBytes($bytes, $offset, 4),
                27 => self::skipBytes($bytes, $offset, 8),
                default => null,
            };
        }

        $val = self::readCborUint($bytes, $offset, $info);

        return match ($major) {
            0 => $val,
            1 => -1 - $val,
            2, 3 => self::readCborSlice($bytes, $offset, $val),
            4 => self::readCborArray($bytes, $offset, $val, $depth + 1),
            5 => self::readCborMap($bytes, $offset, $val, $depth + 1),
            default => self::decodeCbor($bytes, $offset, $depth + 1),
        };
    }

    private static function readCborUint(string $bytes, int &$offset, int $info): int
    {
        if ($info < 24) {
            return $info;
        }

        $lengths = [24 => 1, 25 => 2, 26 => 4, 27 => 8];
        $need = $lengths[$info] ?? 0;

        if ($need === 0 || $offset + $need > strlen($bytes)) {
            throw new TmaAuthException('invalid_attestation', 'Truncated CBOR integer header.');
        }

        $slice = substr($bytes, $offset, $need);
        $offset += $need;

        /** @var array{v: int}|false $unpacked */
        $unpacked = match ($need) {
            1 => ['v' => ord($slice[0])],
            2 => unpack('nv', $slice),
            4 => unpack('Nv', $slice),
            default => unpack('Jv', $slice),
        };

        return is_array($unpacked) ? (int) $unpacked['v'] : 0;
    }

    private static function readCborSlice(string $bytes, int &$offset, int $len): string
    {
        if ($len < 0 || $len > 65536 || $offset + $len > strlen($bytes)) {
            throw new TmaAuthException('invalid_attestation', 'Truncated or oversized CBOR byte/text slice.');
        }

        $slice = substr($bytes, $offset, $len);
        $offset += $len;

        return $slice;
    }

    /**
     * @return list<mixed>
     */
    private static function readCborArray(string $bytes, int &$offset, int $count, int $depth): array
    {
        if ($count < 0 || $count > 512) {
            throw new TmaAuthException('invalid_attestation', 'Oversized CBOR array.');
        }

        $out = [];

        for ($i = 0; $i < $count; $i++) {
            $out[] = self::decodeCbor($bytes, $offset, $depth);
        }

        return $out;
    }

    /**
     * @return array<int|string, mixed>
     */
    private static function readCborMap(string $bytes, int &$offset, int $count, int $depth): array
    {
        if ($count < 0 || $count > 512) {
            throw new TmaAuthException('invalid_attestation', 'Oversized CBOR map.');
        }

        $out = [];

        for ($i = 0; $i < $count; $i++) {
            $key = self::decodeCbor($bytes, $offset, $depth);
            $val = self::decodeCbor($bytes, $offset, $depth);

            if (is_int($key) || is_string($key)) {
                $out[$key] = $val;
            }
        }

        return $out;
    }

    private static function skipBytes(string $bytes, int &$offset, int $len): float
    {
        if ($offset + $len > strlen($bytes)) {
            throw new TmaAuthException('invalid_attestation', 'Truncated CBOR float.');
        }

        $offset += $len;

        return 0.0;
    }

    private function assertValidOrigin(mixed $rawOrigin, ?string $expectedOrigin): string
    {
        $origin = is_string($rawOrigin) ? trim($rawOrigin) : '';
        $originHost = $origin !== '' ? parse_url($origin, PHP_URL_HOST) : null;

        if ($origin === '' || ! is_string($originHost) || $originHost === '') {
            throw new TmaAuthException('invalid_origin', 'WebAuthn clientDataJSON origin is missing or invalid.');
        }

        if ($expectedOrigin !== null && $expectedOrigin !== '' && ! hash_equals(rtrim($expectedOrigin, '/'), rtrim($origin, '/'))) {
            throw new TmaAuthException('origin_mismatch', 'WebAuthn origin mismatch.');
        }

        return strtolower($originHost);
    }

    private static function decodeFlexibleBase64(string $encoded): string
    {
        $normalized = strtr(rtrim(trim($encoded), '='), '-_', '+/');
        $remainder = strlen($normalized) % 4;

        if ($remainder === 1) {
            throw new TmaAuthException('invalid_public_key', 'Malformed base64 public key encoding.');
        }

        $padded = $remainder > 0 ? $normalized.str_repeat('=', 4 - $remainder) : $normalized;
        $decoded = base64_decode($padded, true);

        if ($decoded === false || $decoded === '') {
            throw new TmaAuthException('invalid_public_key', 'Unable to decode base64 public key.');
        }

        return $decoded;
    }

    private static function challengeCacheKey(int $userId): string
    {
        return "tma:webauthn:challenge:{$userId}";
    }
}
