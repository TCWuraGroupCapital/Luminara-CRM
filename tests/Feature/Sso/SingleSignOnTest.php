<?php

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Webkul\Security\Listeners\SecurityEventSubscriber;
use Webkul\Sso\Http\Controllers\SsoController;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

const SSO_ISSUER = 'https://idp.example.test';
const SSO_CLIENT = 'crm-client-id';
const SSO_DOMAIN = 'sso-test.example';

/**
 * A stand-in for Google: its own RSA key, discovery document, key set and token endpoint. The
 * token endpoint answers with whatever ID token the test has prepared.
 */
function fakeProvider(): object
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);
    $rsa = openssl_pkey_get_details($key)['rsa'];
    $b64 = static fn (string $bin): string => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

    $provider = new class
    {
        public string $pem = '';

        public ?string $idToken = null;

        public int $tokenCalls = 0;

        /** @param array<string, mixed> $overrides */
        public function sign(array $overrides = [], ?string $pem = null): string
        {
            $claims = array_merge([
                'iss' => SSO_ISSUER,
                'aud' => SSO_CLIENT,
                'sub' => '1234567890',
                'email_verified' => true,
                'hd' => SSO_DOMAIN,
                'iat' => time(),
                'exp' => time() + 300,
            ], $overrides);

            return JWT::encode($claims, $pem ?? $this->pem, 'RS256', 'test-key');
        }
    };
    $provider->pem = $pem;

    Http::fake([
        SSO_ISSUER.'/.well-known/openid-configuration' => Http::response([
            'issuer' => SSO_ISSUER,
            'authorization_endpoint' => SSO_ISSUER.'/authorize',
            'token_endpoint' => SSO_ISSUER.'/token',
            'jwks_uri' => SSO_ISSUER.'/jwks',
        ]),
        SSO_ISSUER.'/jwks' => Http::response(['keys' => [[
            'kty' => 'RSA', 'kid' => 'test-key', 'use' => 'sig', 'alg' => 'RS256',
            'n' => $b64($rsa['n']), 'e' => $b64($rsa['e']),
        ]]]),
        SSO_ISSUER.'/token' => function () use ($provider) {
            $provider->tokenCalls++;

            return Http::response(['access_token' => 'at', 'id_token' => $provider->idToken, 'token_type' => 'Bearer']);
        },
    ]);

    return $provider;
}

function ssoSettings(array $overrides = []): void
{
    $values = array_merge([
        'enabled' => '1',
        'issuer' => SSO_ISSUER,
        'client_id' => SSO_CLIENT,
        'client_secret' => 'client-secret-for-tests',
        'domains' => SSO_DOMAIN,
        'required' => '0',
        'trust_mfa' => '0',
    ], $overrides);

    foreach ($values as $name => $value) {
        DB::table('core_config')->where('code', 'general.sso.oidc.'.$name)->delete();
        DB::table('core_config')->insert(['code' => 'general.sso.oidc.'.$name, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
    }
}

function ssoUser(string $local = 'dana'): User
{
    $user = agentUser();
    $user->forceFill(['email' => $local.'.'.uniqid().'@'.SSO_DOMAIN, 'password' => bcrypt('Correct-Horse-9')])->save();

    return $user;
}

/**
 * @return array{state: string, nonce: string}
 */
function startSignIn(string $email): array
{
    test()->post(route('admin.sso.start'), ['email' => $email])
        ->assertOk()
        ->assertSee(SSO_ISSUER.'/authorize?', false)
        ->assertSee('code_challenge_method=S256', false);

    return session(SsoController::SESSION_KEY);
}

beforeEach(function () {
    Cache::flush();
    config(['sso.enabled' => true, 'sso.allow_private_issuers' => true]);
    ssoSettings();
});

it('signs an existing user in through the provider and trusts the provider\'s MFA claim', function () {
    $provider = fakeProvider();
    $user = ssoUser();

    $pending = startSignIn($user->email);
    $provider->idToken = $provider->sign(['email' => $user->email, 'nonce' => $pending['nonce'], 'amr' => ['pwd', 'mfa']]);

    $this->get(route('admin.sso.callback', ['state' => $pending['state'], 'code' => 'one-time-code']))
        ->assertRedirect(route('admin.dashboard.index'));

    $this->assertAuthenticatedAs($user, 'user');

    expect(session(SecurityEventSubscriber::MFA_SESSION_KEY))->toBe($user->id)
        ->and($provider->tokenCalls)->toBe(1)
        ->and(DB::table('security_audit_logs')->where('event', 'auth.sso_login')->where('auditable_id', (string) $user->id)->exists())->toBeTrue();

    Http::assertSent(fn ($request) => $request->url() === SSO_ISSUER.'/token'
        && $request['code_verifier'] !== null
        && $request['client_secret'] === 'client-secret-for-tests');
});

it('leaves the CRM\'s own two-factor step in place when the provider does not report one', function () {
    $provider = fakeProvider();
    $user = ssoUser();

    $pending = startSignIn($user->email);
    $provider->idToken = $provider->sign(['email' => $user->email, 'nonce' => $pending['nonce']]);

    $this->get(route('admin.sso.callback', ['state' => $pending['state'], 'code' => 'c']))->assertRedirect();

    $this->assertAuthenticatedAs($user, 'user');
    expect(session(SecurityEventSubscriber::MFA_SESSION_KEY))->toBeNull();
});

it('refuses tokens that are forged, for another app, replayed or from a personal account', function (string $case) {
    $provider = fakeProvider();
    $user = ssoUser();
    $pending = startSignIn($user->email);
    $base = ['email' => $user->email, 'nonce' => $pending['nonce']];

    $provider->idToken = match ($case) {
        'other-key' => (function () use ($provider, $base) {
            openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]), $otherPem);

            return $provider->sign($base, $otherPem);
        })(),
        'other-audience' => $provider->sign(array_merge($base, ['aud' => 'someone-else'])),
        'other-issuer' => $provider->sign(array_merge($base, ['iss' => 'https://evil.example.test'])),
        'wrong-nonce' => $provider->sign(array_merge($base, ['nonce' => 'not-the-nonce'])),
        'expired' => $provider->sign(array_merge($base, ['exp' => time() - 3600])),
        'unverified-email' => $provider->sign(array_merge($base, ['email_verified' => false])),
        'personal-account' => $provider->sign(array_merge($base, ['hd' => 'gmail.com'])),
        'unknown-user' => $provider->sign(array_merge($base, ['email' => 'nobody@'.SSO_DOMAIN])),
    };

    $this->get(route('admin.sso.callback', ['state' => $pending['state'], 'code' => 'c']))
        ->assertRedirect(route('admin.sso.create'))
        ->assertSessionHas('error');

    $this->assertGuest('user');
})->with(['other-key', 'other-audience', 'other-issuer', 'wrong-nonce', 'expired', 'unverified-email', 'personal-account', 'unknown-user']);

it('refuses a wrong or reused state', function () {
    $provider = fakeProvider();
    $user = ssoUser();
    $pending = startSignIn($user->email);
    $provider->idToken = $provider->sign(['email' => $user->email, 'nonce' => $pending['nonce']]);

    $this->get(route('admin.sso.callback', ['state' => 'forged', 'code' => 'c']))->assertRedirect(route('admin.sso.create'));
    $this->assertGuest('user');

    // The pending sign-in was consumed by the failed attempt, so the genuine state no longer works either.
    $this->get(route('admin.sso.callback', ['state' => $pending['state'], 'code' => 'c']))->assertRedirect(route('admin.sso.create'));
    $this->assertGuest('user');

    expect($provider->tokenCalls)->toBe(0);
});

it('only starts for addresses in the configured domains', function () {
    fakeProvider();

    $this->post(route('admin.sso.start'), ['email' => 'someone@elsewhere.example'])
        ->assertRedirect(route('admin.sso.create'))
        ->assertSessionHas('error', trans('sso::app.errors.not-configured'));

    ssoSettings(['enabled' => '0']);

    $this->post(route('admin.sso.start'), ['email' => 'dana@'.SSO_DOMAIN])->assertRedirect(route('admin.sso.create'));
});

it('refuses password sign-in for required domains but not for other addresses', function () {
    fakeProvider();
    $user = ssoUser();
    ssoSettings(['required' => '1']);

    $this->post(route('admin.session.store'), ['email' => $user->email, 'password' => 'Correct-Horse-9'])
        ->assertRedirect(route('admin.sso.create'))
        ->assertSessionHas('warning', trans('sso::app.errors.use-sso'));

    $this->assertGuest('user');

    ssoSettings(['required' => '0']);

    $this->post(route('admin.session.store'), ['email' => $user->email, 'password' => 'Correct-Horse-9']);

    $this->assertAuthenticatedAs($user, 'user');
});

it('shows the sign-in link and pages only when single sign-on is switched on', function () {
    $this->get(route('admin.session.create'))->assertSee(route('admin.sso.create'), false);
    $this->get(route('admin.sso.create'))->assertOk()->assertSee(trans('sso::app.start.title'));

    config(['sso.enabled' => false]);

    $this->get(route('admin.session.create'))->assertDontSee(route('admin.sso.create'), false);
    $this->get(route('admin.sso.create'))->assertNotFound();
});
