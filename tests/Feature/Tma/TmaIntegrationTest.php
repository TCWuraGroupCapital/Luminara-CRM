<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Webkul\Agent\Models\AgentActionCall;
use Webkul\Agent\Support\ActionExecutor;
use Webkul\Lead\Models\Lead;
use Webkul\Ledger\Models\LedgerEpoch;
use Webkul\Ledger\Support\LedgerSigner;
use Webkul\Tma\Models\TelegramIdentity;
use Webkul\Tma\Models\UserPasskey;
use Webkul\Tma\Support\TonAnchorDriver;
use Webkul\Tma\Support\WebAuthnVerifier;
use Webkul\User\Models\Group;

uses(DatabaseTransactions::class);

/**
 * @param  array<string, string>  $fields
 */
function tmaSignedInitData(array $fields, string $botToken): string
{
    $sorted = $fields;
    ksort($sorted, SORT_STRING);

    $lines = [];

    foreach ($sorted as $key => $value) {
        $lines[] = "{$key}={$value}";
    }

    $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
    $hash = hash_hmac('sha256', implode("\n", $lines), $secretKey);

    $query = [];

    foreach ($fields as $key => $value) {
        $query[] = rawurlencode($key).'='.rawurlencode($value);
    }

    $query[] = 'hash='.$hash;

    return implode('&', $query);
}

it('serves /tma with Telegram CSP frame-ancestors and without X-Frame-Options', function () {
    $response = $this->get('/tma');

    $response->assertSuccessful()
        ->assertSee('lum-tma', false)
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self' https://web.telegram.org https://*.telegram.org")
        ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(self)');

    expect($response->headers->has('X-Frame-Options'))->toBeFalse();
});

it('completes admin pairing, TMA session exchange, intake capture, and Passkey-signed call approval', function () {
    $botToken = '7555444333:AAIntegrationTestBotToken_LuminaraTma';
    config([
        'tma.bot_token' => $botToken,
        'tma.bot_username' => 'LuminaraCrmBot',
        'tma.init_data_ttl_seconds' => 300,
    ]);
    Cache::flush();

    $user = agentUser();

    // 1. Issue pairing code as authenticated CRM user on the admin pairing endpoint
    $pairRes = $this->actingAs($user, 'user')
        ->postJson(route('admin.tma.pairing.code'));

    $pairRes->assertSuccessful()
        ->assertJsonPath('ok', true);

    $code = (string) $pairRes->json('code');
    expect($code)->toHaveLength(8)
        ->and($pairRes->json('deep_link'))->toBe("https://t.me/LuminaraCrmBot?start={$code}");

    // 2. Exchange Telegram initData + pair_code at /api/tma/session for a 2h ['tma'] token
    $initData = tmaSignedInitData([
        'auth_date' => (string) time(),
        'query_id' => 'AAH99887766',
        'user' => (string) json_encode([
            'id' => 99887766,
            'first_name' => 'Amira',
            'username' => 'amira_ops',
            'language_code' => 'en',
        ]),
    ], $botToken);

    $sessionRes = $this->postJson('/api/tma/session', [
        'init_data' => $initData,
        'pair_code' => $code,
    ]);

    $sessionRes->assertSuccessful()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('linked', true)
        ->assertJsonPath('user.id', $user->id);

    $tmaToken = (string) $sessionRes->json('token');
    $headers = ['Authorization' => 'Bearer '.$tmaToken];

    expect(TelegramIdentity::query()->where('user_id', $user->id)->where('telegram_user_id', 99887766)->exists())
        ->toBeTrue();

    // Verify regular agent tokens without 'tma' ability cannot hit TMA endpoints
    $this->getJson('/api/tma/briefing', agentHeaders($user))
        ->assertStatus(401);

    // 3. Register an Ed25519 Passkey via TMA API
    $keyPair = sodium_crypto_sign_keypair();
    $secretKey = sodium_crypto_sign_secretkey($keyPair);
    $publicKey = sodium_crypto_sign_publickey($keyPair);

    $regChallengeRes = $this->postJson('/api/tma/passkeys/challenge', [], $headers)
        ->assertSuccessful();

    $regClientData = (string) json_encode([
        'type' => 'webauthn.create',
        'challenge' => $regChallengeRes->json('challenge'),
        'origin' => 'http://localhost',
    ]);

    $this->postJson('/api/tma/passkeys', [
        'credential_id' => 'tma-ed25519-passkey-1',
        'label' => 'Amira Biometric Key',
        'alg' => 'ed25519',
        'public_key' => WebAuthnVerifier::base64UrlEncode($publicKey),
        'client_data_json' => WebAuthnVerifier::base64UrlEncode($regClientData),
    ], $headers)->assertCreated()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('passkey.alg', 'ed25519');

    // 4. Quick intake capture from TMA
    $intakeRes = $this->postJson('/api/tma/intake', [
        'text' => 'Met Dana Okafor about a 15000 fleet telemetry expansion, email dana.fleet@example.com',
    ], $headers);

    $intakeRes->assertSuccessful()
        ->assertJsonPath('ok', true);

    expect(AgentActionCall::query()
        ->where('user_id', $user->id)
        ->where('action', 'intake_capture')
        ->exists())->toBeTrue();

    // 5. Create a pending destructive call (`leads_delete`), inspect via /api/tma/briefing, and sign with WebAuthn Passkey
    $leadToDelete = agentLead($user, 'Obsolete duplicate deal');
    auth()->guard('user')->setUser($user);
    $pendingOutcome = app(ActionExecutor::class)->execute('leads_delete', [
        'lead_id' => $leadToDelete->id,
        'reason' => 'Confirmed duplicate lead during mobile triage',
    ]);
    $callId = (int) $pendingOutcome->body['call_id'];

    $briefingRes = $this->getJson('/api/tma/briefing', $headers)
        ->assertSuccessful()
        ->assertJsonPath('ok', true);

    $pendingIds = array_column($briefingRes->json('pending_slips') ?? [], 'id');
    expect($pendingIds)->toContain($callId);

    // Challenge + sign assertion (bound 1:1 to $callId)
    $signChallengeRes = $this->postJson('/api/tma/passkeys/challenge', ['call_id' => $callId], $headers)
        ->assertSuccessful();

    $assertClientData = (string) json_encode([
        'type' => 'webauthn.get',
        'challenge' => $signChallengeRes->json('challenge'),
        'origin' => 'http://localhost',
    ]);
    $authData = hash('sha256', 'localhost', true).chr(0x05).pack('N', 1);
    $signature = sodium_crypto_sign_detached(
        $authData.hash('sha256', $assertClientData, true),
        $secretKey
    );

    $decideRes = $this->postJson("/api/tma/calls/{$callId}/decide", [
        'approve' => true,
        'webauthn_assertion' => [
            'credential_id' => 'tma-ed25519-passkey-1',
            'client_data_json' => WebAuthnVerifier::base64UrlEncode($assertClientData),
            'authenticator_data' => WebAuthnVerifier::base64UrlEncode($authData),
            'signature' => WebAuthnVerifier::base64UrlEncode($signature),
        ],
    ], $headers);

    $decideRes->assertSuccessful()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('signer_proof.webauthn_verified', true)
        ->assertJsonPath('signer_proof.credential_id', 'tma-ed25519-passkey-1');

    expect(Lead::query()->whereKey($leadToDelete->id)->exists())->toBeFalse()
        ->and(UserPasskey::query()->where('credential_id', 'tma-ed25519-passkey-1')->value('sign_count'))->toBe(1);
});

it('guards the Telegram Bot webhook with secret token and handles /start pairing and message intake', function () {
    config([
        'tma.webhook_secret' => 'super-secret-tg-webhook-token-2026',
    ]);
    Cache::flush();

    // 1. Reject invalid or missing secret header
    $this->postJson('/api/tma/bot/webhook', [
        'message' => ['from' => ['id' => 554433], 'chat' => ['id' => 554433], 'text' => 'Hello'],
    ])->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_webhook_secret');

    $this->postJson('/api/tma/bot/webhook', [
        'message' => ['from' => ['id' => 554433], 'chat' => ['id' => 554433], 'text' => 'Hello'],
    ], ['X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret'])
        ->assertStatus(401);

    // 2. Pair via `/start <CODE>`
    $owner = agentUser();
    $pairRes = $this->actingAs($owner, 'user')
        ->postJson(route('admin.tma.pairing.code'))
        ->assertSuccessful();
    $pairCode = (string) $pairRes->json('code');

    $webhookHeaders = ['X-Telegram-Bot-Api-Secret-Token' => 'super-secret-tg-webhook-token-2026'];

    $startRes = $this->postJson('/api/tma/bot/webhook', [
        'update_id' => 1001,
        'message' => [
            'message_id' => 77,
            'from' => ['id' => 55443322, 'first_name' => 'Kenji', 'username' => 'kenji_sales'],
            'chat' => ['id' => 55443322],
            'text' => "/start {$pairCode}",
        ],
    ], $webhookHeaders);

    $startRes->assertSuccessful()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('paired', true)
        ->assertJsonPath('user_id', $owner->id)
        ->assertJsonPath('reply_markup.inline_keyboard.0.0.web_app.url', url('/tma'));

    // 3. Send natural-language intake message from paired Telegram chat
    $msgRes = $this->postJson('/api/tma/bot/webhook', [
        'update_id' => 1002,
        'message' => [
            'message_id' => 78,
            'from' => ['id' => 55443322, 'first_name' => 'Kenji', 'username' => 'kenji_sales'],
            'chat' => ['id' => 55443322],
            'text' => 'Call Sora Takahashi tomorrow regarding 9200 warehouse sensor quote',
        ],
    ], $webhookHeaders);

    $msgRes->assertSuccessful()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('linked', true)
        ->assertJsonPath('action', 'intake_capture')
        ->assertJsonPath('reply_markup.inline_keyboard.0.0.web_app.url', url('/tma'));

    expect($msgRes->json('call_id'))->toBeInt();
});

it('registers and executes TonAnchorDriver in both staged mode and gas-sponsored relayer mode', function () {
    expect(config('ledger.drivers.ton'))->toBe(TonAnchorDriver::class);

    $driver = app(TonAnchorDriver::class);
    expect($driver->name())->toBe('ton');

    $merkleRoot = str_repeat('a1b2c3d4', 8);
    $epoch = (new LedgerEpoch)->forceFill([
        'id' => 7,
        'start_height' => 1,
        'end_height' => 25,
        'merkle_root' => $merkleRoot,
        'server_signature' => 'ed25519-epoch-sig',
    ]);

    // 1. Staged mode when no relayer URL is configured
    config([
        'tma.ton_relayer_url' => '',
        'tma.ton_contract_address' => '',
    ]);

    expect($driver->anchor($epoch))->toBe('ton:staged:'.substr($merkleRoot, 0, 24));

    // 2. Relayer mode when TON_ANCHOR_RELAYER_URL is configured
    Http::fake([
        'https://ton-relayer.luminara.test/*' => Http::response([
            'ok' => true,
            'tx_hash' => '9f8e7d6c5b4a3210',
        ], 200),
    ]);

    config([
        'tma.ton_relayer_url' => 'https://ton-relayer.luminara.test/v1/anchor',
        'tma.ton_contract_address' => 'EQD_LuminaraAnchorContract_Test',
    ]);

    $proof = $driver->anchor($epoch);
    expect($proof)->toBe('ton:tx:9f8e7d6c5b4a3210');

    Http::assertSent(function (HttpClientRequest $request) use ($merkleRoot): bool {
        return $request->url() === 'https://ton-relayer.luminara.test/v1/anchor'
            && $request['epoch_id'] === 7
            && $request['start_height'] === 1
            && $request['end_height'] === 25
            && $request['merkle_root'] === $merkleRoot
            && $request['commitment'] === 'ton:merkle:'.$merkleRoot
            && $request['contract'] === 'EQD_LuminaraAnchorContract_Test';
    });
});

it('blocks TMA self-approval without agent_approvals ACL and allows group-scoped managers to decide teammate calls', function () {
    // 1. Rep with leads.delete but without agent_approvals cannot self-approve via TMA
    $rep = agentUser(['leads', 'leads.delete'], 'individual');
    $repLead = agentLead($rep, 'Rep Self-Approval Guard Lead');

    auth()->guard('user')->setUser($rep);
    $repPending = app(ActionExecutor::class)->execute('leads_delete', [
        'lead_id' => $repLead->id,
        'reason' => 'Rep attempting self-approval on TMA',
    ]);
    $repCallId = (int) $repPending->body['call_id'];
    auth()->guard('user')->logout();

    $repTmaHeaders = ['Authorization' => 'Bearer '.$rep->createToken('tma-rep', ['tma'])->plainTextToken];

    $this->postJson("/api/tma/calls/{$repCallId}/decide", [
        'approve' => true,
    ], $repTmaHeaders)
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'forbidden');

    expect(Lead::query()->whereKey($repLead->id)->exists())->toBeTrue();

    // 2. Group-scoped manager with agent_approvals can view and approve teammate's call, but not outsider's call
    $manager = agentUser([
        'leads',
        'leads.delete',
        ActionExecutor::APPROVAL_PERMISSION,
    ], 'group');

    $outsider = agentUser(['leads', 'leads.delete'], 'individual');
    $outsiderLead = agentLead($outsider, 'Outsider Lead');
    auth()->guard('user')->setUser($outsider);
    $outsiderPending = app(ActionExecutor::class)->execute('leads_delete', [
        'lead_id' => $outsiderLead->id,
        'reason' => 'Outsider pending delete',
    ]);
    $outsiderCallId = (int) $outsiderPending->body['call_id'];
    auth()->guard('user')->logout();

    $group = Group::create([
        'name' => 'APAC Triage '.bin2hex(random_bytes(3)),
        'description' => 'Shared TMA approval group',
    ]);
    $group->users()->attach([$manager->id, $rep->id]);

    $managerTmaHeaders = ['Authorization' => 'Bearer '.$manager->createToken('tma-mgr', ['tma'])->plainTextToken];

    $briefingSlips = $this->getJson('/api/tma/briefing', $managerTmaHeaders)
        ->assertOk()
        ->json('pending_slips');
    $visibleIds = array_column($briefingSlips ?? [], 'id');

    expect($visibleIds)->toContain($repCallId)
        ->and($visibleIds)->not->toContain($outsiderCallId);

    $this->postJson("/api/tma/calls/{$outsiderCallId}/decide", [
        'approve' => true,
    ], $managerTmaHeaders)->assertStatus(404);

    $this->postJson("/api/tma/calls/{$repCallId}/decide", [
        'approve' => true,
    ], $managerTmaHeaders)
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    expect(Lead::query()->whereKey($repLead->id)->exists())->toBeFalse()
        ->and(Lead::query()->whereKey($outsiderLead->id)->exists())->toBeTrue();
});

it('enforces passkey_required on enrolled accounts, rejects call-mismatched WebAuthn challenges, and parses CBOR attestationObject keys', function () {
    Cache::flush();
    $user = agentUser();
    $headers = ['Authorization' => 'Bearer '.$user->createToken('tma-hardening', ['tma'])->plainTextToken];

    // 1. Enroll an Ed25519 passkey using ONLY CBOR attestation_object (no public_key or alg sent — Safari/Firefox/iOS path)
    $edKeyPair = sodium_crypto_sign_keypair();
    $edSecret = sodium_crypto_sign_secretkey($edKeyPair);
    $edPublic = sodium_crypto_sign_publickey($edKeyPair);
    $edRawCredId = random_bytes(16);
    $edCredIdB64 = WebAuthnVerifier::base64UrlEncode($edRawCredId);

    $coseEd25519 = "\xa4\x01\x01\x03\x27\x20\x06\x21\x58\x20".$edPublic;
    $authDataEd = hash('sha256', 'localhost', true).chr(0x45).pack('N', 0).str_repeat("\x00", 16).pack('n', strlen($edRawCredId)).$edRawCredId.$coseEd25519;
    $attestationEd = "\xa2\x63fmt\x64none\x68authData\x58".chr(strlen($authDataEd)).$authDataEd;

    $regChallenge1 = $this->postJson('/api/tma/passkeys/challenge', [], $headers)->assertOk()->json('challenge');
    $regClientData1 = (string) json_encode([
        'type' => 'webauthn.create',
        'challenge' => $regChallenge1,
        'origin' => 'http://localhost',
    ]);

    $this->postJson('/api/tma/passkeys', [
        'credential_id' => $edCredIdB64,
        'label' => 'Safari Attested Ed25519',
        'attestation_object' => WebAuthnVerifier::base64UrlEncode($attestationEd),
        'client_data_json' => WebAuthnVerifier::base64UrlEncode($regClientData1),
    ], $headers)->assertCreated()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('passkey.alg', 'ed25519');

    // 2. Enroll an ES256 (P-256) passkey via desktop admin pairing endpoint using CBOR attestation_object
    $ecX = (string) LedgerSigner::base64UrlDecode('f83OJ3D2xF1Bg8vub9tLe1gHMzV76e8Tus9uPHvRVEU');
    $ecY = (string) LedgerSigner::base64UrlDecode('x_FEzRu9m36HLN_tue659LNpXW6pCyStikYjKIWI5a0');
    $ecRawCredId = random_bytes(16);
    $ecCredIdB64 = WebAuthnVerifier::base64UrlEncode($ecRawCredId);

    $coseEs256 = "\xa5\x01\x02\x03\x26\x20\x01\x21\x58\x20".$ecX."\x22\x58\x20".$ecY;
    $authDataEc = hash('sha256', 'localhost', true).chr(0x45).pack('N', 0).str_repeat("\x00", 16).pack('n', strlen($ecRawCredId)).$ecRawCredId.$coseEs256;
    $attestationEc = "\xa2\x63fmt\x64none\x68authData\x58".chr(strlen($authDataEc)).$authDataEc;

    $desktopChal = $this->actingAs($user, 'user')
        ->postJson(route('admin.tma.passkeys.challenge'))
        ->assertOk()
        ->json('challenge');

    $desktopClientData = (string) json_encode([
        'type' => 'webauthn.create',
        'challenge' => $desktopChal,
        'origin' => 'http://localhost',
    ]);

    $this->actingAs($user, 'user')
        ->postJson(route('admin.tma.passkeys.store'), [
            'credential_id' => $ecCredIdB64,
            'label' => 'Desktop TouchID ES256',
            'attestation_object' => WebAuthnVerifier::base64UrlEncode($attestationEc),
            'client_data_json' => WebAuthnVerifier::base64UrlEncode($desktopClientData),
        ])->assertCreated()
        ->assertJsonPath('passkey.alg', 'es256');

    auth()->guard('user')->logout();

    // 3. Create two pending calls and verify:
    //    (a) Approving WITHOUT webauthn_assertion fails with 422 passkey_required
    //    (b) Signing callB using a challenge bound to callA fails with 422 challenge_call_mismatch
    $leadA = agentLead($user, 'Call Bound Lead A');
    $leadB = agentLead($user, 'Call Bound Lead B');

    auth()->guard('user')->setUser($user);
    $callIdA = (int) app(ActionExecutor::class)->execute('leads_delete', ['lead_id' => $leadA->id])->body['call_id'];
    $callIdB = (int) app(ActionExecutor::class)->execute('leads_delete', ['lead_id' => $leadB->id])->body['call_id'];
    auth()->guard('user')->logout();

    $this->postJson("/api/tma/calls/{$callIdA}/decide", [
        'approve' => true,
    ], $headers)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'passkey_required');

    // Issue challenge bound to callIdA, attempt to use it on callIdB -> 422 challenge_call_mismatch
    $boundChalA = $this->postJson('/api/tma/passkeys/challenge', ['call_id' => $callIdA], $headers)
        ->assertOk()
        ->json('challenge');

    $mismatchClientData = (string) json_encode([
        'type' => 'webauthn.get',
        'challenge' => $boundChalA,
        'origin' => 'http://localhost',
    ]);
    $assertAuthData = hash('sha256', 'localhost', true).chr(0x05).pack('N', 1);
    $mismatchSig = sodium_crypto_sign_detached(
        $assertAuthData.hash('sha256', $mismatchClientData, true),
        $edSecret
    );

    $this->postJson("/api/tma/calls/{$callIdB}/decide", [
        'approve' => true,
        'webauthn_assertion' => [
            'credential_id' => $edCredIdB64,
            'client_data_json' => WebAuthnVerifier::base64UrlEncode($mismatchClientData),
            'authenticator_data' => WebAuthnVerifier::base64UrlEncode($assertAuthData),
            'signature' => WebAuthnVerifier::base64UrlEncode($mismatchSig),
        ],
    ], $headers)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'challenge_call_mismatch');

    expect(Lead::query()->whereKey($leadA->id)->exists())->toBeTrue()
        ->and(Lead::query()->whereKey($leadB->id)->exists())->toBeTrue();
});
