<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Webkul\Tma\Exceptions\TmaAuthException;
use Webkul\Tma\Models\UserPasskey;
use Webkul\Tma\Support\TelegramInitDataValidator;
use Webkul\Tma\Support\WebAuthnVerifier;

uses(TestCase::class, DatabaseTransactions::class);

/**
 * Build a spec-compliant signed Telegram Mini App `initData` query string.
 *
 * @param  array<string, string>  $fields
 */
function buildSignedTelegramInitData(array $fields, string $botToken): string
{
    $sorted = $fields;
    ksort($sorted, SORT_STRING);

    $lines = [];

    foreach ($sorted as $key => $value) {
        $lines[] = "{$key}={$value}";
    }

    $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
    $hash = hash_hmac('sha256', implode("\n", $lines), $secretKey);

    $encoded = [];

    foreach ($fields as $key => $value) {
        $encoded[] = rawurlencode($key).'='.rawurlencode($value);
    }

    $encoded[] = 'hash='.$hash;

    return implode('&', $encoded);
}

/**
 * Generate a fresh OpenSSL P-256 keypair across Linux, macOS, and Windows environments.
 *
 * @return array{private: OpenSSLAsymmetricKey, public_pem: string}
 */
function createEs256TestKeyPair(): array
{
    $options = [
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ];

    $key = openssl_pkey_new($options);

    if ($key === false) {
        $tmpCnf = tempnam(sys_get_temp_dir(), 'ossl_');
        file_put_contents($tmpCnf, "[req]\ndistinguished_name = dn\n[dn]\n");
        $key = openssl_pkey_new($options + ['config' => $tmpCnf]);
        @unlink($tmpCnf);
    }

    expect($key)->toBeInstanceOf(OpenSSLAsymmetricKey::class);

    $details = openssl_pkey_get_details($key);

    return [
        'private' => $key,
        'public_pem' => trim((string) ($details['key'] ?? '')),
    ];
}

/**
 * Convert an ASN.1 DER ECDSA signature (`SEQUENCE { r INTEGER, s INTEGER }`) into raw 64-byte IEEE P1363 `r || s`.
 */
function derToP1363Signature(string $der): string
{
    $rLen = ord($der[3]);
    $rBytes = ltrim(substr($der, 4, $rLen), "\x00");
    $sLen = ord($der[5 + $rLen]);
    $sBytes = ltrim(substr($der, 6 + $rLen, $sLen), "\x00");

    return str_pad($rBytes, 32, "\x00", STR_PAD_LEFT).str_pad($sBytes, 32, "\x00", STR_PAD_LEFT);
}

it('validates official Telegram WebAppData HMAC vectors and rejects wrong HMAC key direction', function () {
    $botToken = '7123456789:AAExampleTelegramBotSecretToken_XYZ12';
    config([
        'tma.bot_token' => $botToken,
        'tma.init_data_ttl_seconds' => 300,
    ]);

    $now = 1_763_200_000;
    $userJson = json_encode([
        'id' => 42424242,
        'first_name' => 'Nadia + Co.',
        'username' => 'nadia.crm',
        'language_code' => 'en',
    ], JSON_UNESCAPED_SLASHES);

    $initData = buildSignedTelegramInitData([
        'query_id' => 'AAHdF6IQAAAAAN0XohDhrOrc',
        'user' => (string) $userJson,
        'auth_date' => (string) ($now - 15),
    ], $botToken);

    $validator = new TelegramInitDataValidator;
    $result = $validator->validate($initData, $now);

    expect($result['auth_date'])->toBe($now - 15)
        ->and($result['query_id'])->toBe('AAHdF6IQAAAAAN0XohDhrOrc')
        ->and($result['user']['id'])->toBe(42424242)
        ->and($result['user']['first_name'])->toBe('Nadia + Co.')
        ->and($result['user']['username'])->toBe('nadia.crm');

    // Verify reversed HMAC key/message order ("WebAppData" as message instead of key) is strictly rejected.
    $wrongSecret = hash_hmac('sha256', 'WebAppData', $botToken, true);
    $checkString = "auth_date={$now}\nuser={$userJson}";
    $wrongHash = hash_hmac('sha256', $checkString, $wrongSecret);
    $wrongInitData = 'auth_date='.$now.'&user='.rawurlencode((string) $userJson).'&hash='.$wrongHash;

    expect(fn () => $validator->validate($wrongInitData, $now))
        ->toThrow(TmaAuthException::class, 'Telegram initData signature verification failed.');
});

it('rejects tampered fields, expired auth_date, and replayed Telegram initData', function () {
    $botToken = '7999888777:AAValidBotTokenForReplayAndTamperTests';
    config([
        'tma.bot_token' => $botToken,
        'tma.init_data_ttl_seconds' => 300,
    ]);
    Cache::flush();

    $now = 1_763_210_000;
    $userJson = (string) json_encode(['id' => 88001122, 'first_name' => 'Dana', 'username' => 'dana']);
    $validator = new TelegramInitDataValidator;

    // 1. Tampered field after signing
    $validInit = buildSignedTelegramInitData([
        'auth_date' => (string) $now,
        'user' => $userJson,
    ], $botToken);
    $tampered = str_replace('Dana', 'Mallory', $validInit);

    try {
        $validator->validate($tampered, $now);
        $this->fail('Expected tampered initData to fail.');
    } catch (TmaAuthException $e) {
        expect($e->errorCode)->toBe('invalid_signature');
    }

    // 2. Expired auth_date (> 300s old)
    $expiredInit = buildSignedTelegramInitData([
        'auth_date' => (string) ($now - 305),
        'user' => $userJson,
    ], $botToken);

    try {
        $validator->validate($expiredInit, $now);
        $this->fail('Expected expired initData to fail.');
    } catch (TmaAuthException $e) {
        expect($e->errorCode)->toBe('expired_init_data');
    }

    // 3. Replay attack (first call succeeds, second identical call fails)
    $firstPass = $validator->validate($validInit, $now);
    expect($firstPass['user']['id'])->toBe(88001122);

    try {
        $validator->validate($validInit, $now);
        $this->fail('Expected replayed initData to fail.');
    } catch (TmaAuthException $e) {
        expect($e->errorCode)->toBe('replayed_init_data');
    }
});

it('verifies real OpenSSL P-256 (ES256 IEEE P1363 r||s) and Sodium Ed25519 WebAuthn assertions and blocks counter replay', function () {
    $user = agentUser();
    $verifier = new WebAuthnVerifier;

    // --- Part A: ES256 with 64-byte IEEE P1363 (r || s) signature ---
    $es256 = createEs256TestKeyPair();
    $esPasskey = UserPasskey::query()->create([
        'user_id' => $user->id,
        'credential_id' => 'cred-es256-unit',
        'label' => 'iPhone Touch ID',
        'alg' => UserPasskey::ALG_ES256,
        'public_key' => $verifier->normalizePublicKey('es256', $es256['public_pem']),
        'sign_count' => 0,
    ]);

    $challenge1 = $verifier->issueChallenge($user->id);
    $clientData1 = (string) json_encode([
        'type' => 'webauthn.get',
        'challenge' => $challenge1,
        'origin' => 'https://crm.luminara.test',
    ]);
    $authData1 = hash('sha256', 'crm.luminara.test', true).chr(0x05).pack('N', 1);
    $signedPayload1 = $authData1.hash('sha256', $clientData1, true);

    $derSig = '';
    openssl_sign($signedPayload1, $derSig, $es256['private'], OPENSSL_ALGO_SHA256);
    $p1363Sig = derToP1363Signature($derSig);
    expect(strlen($p1363Sig))->toBe(64);

    $esProof = $verifier->verifyAssertion($user->id, [
        'credential_id' => $esPasskey->credential_id,
        'client_data_json' => WebAuthnVerifier::base64UrlEncode($clientData1),
        'authenticator_data' => WebAuthnVerifier::base64UrlEncode($authData1),
        'signature' => WebAuthnVerifier::base64UrlEncode($p1363Sig),
    ]);

    expect($esProof['webauthn_verified'])->toBeTrue()
        ->and($esProof['alg'])->toBe('es256')
        ->and($esProof['sign_count'])->toBe(1)
        ->and($esPasskey->fresh()->sign_count)->toBe(1);

    // Non-monotonic signCount (replaying counter 1) must fail with cloned_authenticator
    $challengeReplay = $verifier->issueChallenge($user->id);
    $clientDataReplay = (string) json_encode([
        'type' => 'webauthn.get',
        'challenge' => $challengeReplay,
        'origin' => 'https://crm.luminara.test',
    ]);
    $signedReplay = $authData1.hash('sha256', $clientDataReplay, true);
    openssl_sign($signedReplay, $derSigReplay, $es256['private'], OPENSSL_ALGO_SHA256);

    try {
        $verifier->verifyAssertion($user->id, [
            'credential_id' => $esPasskey->credential_id,
            'client_data_json' => WebAuthnVerifier::base64UrlEncode($clientDataReplay),
            'authenticator_data' => WebAuthnVerifier::base64UrlEncode($authData1),
            'signature' => WebAuthnVerifier::base64UrlEncode($derSigReplay),
        ]);
        $this->fail('Expected non-monotonic signCount to fail.');
    } catch (TmaAuthException $e) {
        expect($e->errorCode)->toBe('cloned_authenticator');
    }

    // --- Part B: Ed25519 via Sodium ---
    $edKeyPair = sodium_crypto_sign_keypair();
    $edSecret = sodium_crypto_sign_secretkey($edKeyPair);
    $edPublic = sodium_crypto_sign_publickey($edKeyPair);

    $edPasskey = UserPasskey::query()->create([
        'user_id' => $user->id,
        'credential_id' => 'cred-ed25519-unit',
        'label' => 'YubiKey Bio',
        'alg' => UserPasskey::ALG_ED25519,
        'public_key' => $verifier->normalizePublicKey('ed25519', WebAuthnVerifier::base64UrlEncode($edPublic)),
        'sign_count' => 0,
    ]);

    $challenge2 = $verifier->issueChallenge($user->id);
    $clientData2 = (string) json_encode([
        'type' => 'webauthn.get',
        'challenge' => $challenge2,
        'origin' => 'https://crm.luminara.test',
    ]);
    $authData2 = hash('sha256', 'crm.luminara.test', true).chr(0x01).pack('N', 5);
    $signedPayload2 = $authData2.hash('sha256', $clientData2, true);
    $edSignature = sodium_crypto_sign_detached($signedPayload2, $edSecret);

    $edProof = $verifier->verifyAssertion($user->id, [
        'credential_id' => $edPasskey->credential_id,
        'client_data_json' => WebAuthnVerifier::base64UrlEncode($clientData2),
        'authenticator_data' => WebAuthnVerifier::base64UrlEncode($authData2),
        'signature' => WebAuthnVerifier::base64UrlEncode($edSignature),
    ]);

    expect($edProof['webauthn_verified'])->toBeTrue()
        ->and($edProof['alg'])->toBe('ed25519')
        ->and($edProof['credential_id'])->toBe('cred-ed25519-unit')
        ->and($edProof['sign_count'])->toBe(5);
});

it('ships identical translation keys across all 10 supported locales', function () {
    $locales = ['ar', 'en', 'es', 'fa', 'ja', 'ko', 'pt_BR', 'tr', 'vi', 'zh_CN'];
    $basePath = base_path('packages/Webkul/Tma/src/Resources/lang');
    $englishKeys = array_keys(Arr::dot(require "{$basePath}/en/app.php"));
    sort($englishKeys);

    foreach ($locales as $locale) {
        $file = "{$basePath}/{$locale}/app.php";
        expect(is_file($file))->toBeTrue("Missing locale file: {$locale}/app.php");

        $localeKeys = array_keys(Arr::dot(require $file));
        sort($localeKeys);

        expect($localeKeys)->toBe($englishKeys, "Translation key mismatch in locale [{$locale}]");
    }
});
