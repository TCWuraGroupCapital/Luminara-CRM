<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Ledger\Models\DataBlock;
use Webkul\Ledger\Models\LedgerEpoch;
use Webkul\Ledger\Support\LedgerAppender;
use Webkul\Ledger\Support\LedgerSigner;
use Webkul\Ledger\Support\LedgerVerifier;
use Webkul\Ledger\Support\MerkleTree;
use Webkul\User\Models\Group;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

beforeEach(function () {
    DB::table('data_blocks')->delete();
    DB::table('ledger_epochs')->delete();
    DB::table('ledger_heads')->delete();
});

it('records hash-chained CRM lifecycle blocks, preserves pre-delete tombstones, and enforces Eloquent immutability', function () {
    $admin = getDefaultAdmin();
    test()->actingAs($admin, 'user');

    $lead = agentLead($admin, 'Château Margaux Allocation');
    Event::dispatch('lead.create.after', $lead);

    $lead->update(['title' => 'Château Margaux 2026 Signed Allocation', 'lead_value' => 14500]);
    Event::dispatch('lead.update.after', $lead->fresh());

    $leadId = $lead->id;
    Event::dispatch('lead.delete.before', $leadId);
    $lead->delete();

    expect(DB::table('leads')->where('id', $leadId)->exists())->toBeFalse();

    $blocks = DataBlock::query()->orderBy('block_height')->get();

    expect($blocks)->toHaveCount(3)
        ->and($blocks[0]->block_height)->toBe(1)
        ->and($blocks[0]->prev_block_hash)->toBe(MerkleTree::EMPTY_ROOT)
        ->and($blocks[0]->event_name)->toBe('lead.created')
        ->and($blocks[0]->receipt_code)->toStartWith('LUM-')
        ->and(strlen($blocks[0]->receipt_code))->toBe(30)
        ->and($blocks[1]->block_height)->toBe(2)
        ->and($blocks[1]->prev_block_hash)->toBe($blocks[0]->block_hash)
        ->and($blocks[1]->event_name)->toBe('lead.updated')
        ->and($blocks[2]->block_height)->toBe(3)
        ->and($blocks[2]->prev_block_hash)->toBe($blocks[1]->block_hash)
        ->and($blocks[2]->event_name)->toBe('lead.tombstoned')
        ->and($blocks[2]->canonical_payload['_tombstone'])->toBeTrue()
        ->and($blocks[2]->canonical_payload['snapshot']['title'])->toBe('Château Margaux 2026 Signed Allocation');

    // Eloquent immutability must reject direct updates and deletes
    expect(fn () => $blocks[0]->update(['event_name' => 'lead.forged']))
        ->toThrow(LogicException::class)
        ->and(fn () => $blocks[0]->delete())
        ->toThrow(LogicException::class);
});

it('seals Merkle epochs, verifies intact chains via CLI, and fails with exit code 1 upon database tampering', function () {
    $admin = getDefaultAdmin();
    $appender = app(LedgerAppender::class);
    $verifier = app(LedgerVerifier::class);

    $b1 = $appender->append('lead.created', 'lead', 101, ['title' => 'Alpha Deal', 'api_key' => 'secret-1'], 'human', $admin->id);
    $b2 = $appender->append('quote.created', 'quote', 202, ['subject' => 'Q4 Supply', 'grand_total' => 9800], 'human', $admin->id);
    $b3 = $appender->append('agent.call.completed', 'agent_action_call', 303, ['action' => 'leads_create'], 'agent', $admin->id);

    expect($b1->canonical_payload['api_key'])->toBe('[REDACTED]');

    // Seal epoch #1 via CLI --json
    expect(Artisan::call('luminara:ledger-seal', ['--json' => true]))->toBe(0);
    $sealJson = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($sealJson['sealed'])->toBeTrue()
        ->and($sealJson['epoch']['start_height'])->toBe(1)
        ->and($sealJson['epoch']['end_height'])->toBe(3)
        ->and($sealJson['epoch']['block_count'])->toBe(3)
        ->and($sealJson['epoch']['anchor_reference'])->toStartWith('local:ed25519:');

    $epoch = LedgerEpoch::query()->firstOrFail();
    expect(fn () => $epoch->update(['block_count' => 99]))->toThrow(LogicException::class)
        ->and(fn () => $epoch->delete())->toThrow(LogicException::class);

    // Append a 4th block and seal epoch #2 to verify prev_epoch_root chaining
    $b4 = $appender->append('product.created', 'product', 404, ['sku' => 'PINOT-2026'], 'human', $admin->id);
    $epoch2 = $verifier->sealPendingEpoch();

    expect($epoch2)->not->toBeNull()
        ->and($epoch2->prev_epoch_root)->toBe($epoch->merkle_root)
        ->and($b4->fresh()->epoch_id)->toBe($epoch2->id);

    // Verify full chain via CLI --json -> exit 0
    expect(Artisan::call('luminara:ledger-verify', ['--json' => true]))->toBe(0);
    $verifyJson = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($verifyJson['ok'])->toBeTrue()
        ->and($verifyJson['checked_blocks'])->toBe(4)
        ->and($verifyJson['checked_epochs'])->toBe(2)
        ->and($verifyJson['head_height'])->toBe(4)
        ->and($verifyJson['errors'])->toBeEmpty();

    // Tamper directly in SQL bypassing Eloquent -> CLI must detect tamper and exit 1
    DB::table('data_blocks')
        ->where('id', $b2->id)
        ->update(['canonical_payload' => '{"grand_total":999999,"subject":"Tampered"}']);

    expect(Artisan::call('luminara:ledger-verify', ['--json' => true]))->toBe(1);
    $tamperedJson = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($tamperedJson['ok'])->toBeFalse()
        ->and($tamperedJson['errors'])->not->toBeEmpty();
});

it('serves PII-safe public receipts and Merkle inclusion proofs while granting entity summaries to authorized staff', function () {
    $admin = getDefaultAdmin();

    $person = Person::create([
        'name' => 'Confidential VIP Buyer',
        'emails' => [['value' => 'vip.secret@privatebank.example', 'label' => 'work']],
        'contact_numbers' => [['value' => '+41445550199', 'label' => 'mobile']],
        'user_id' => $admin->id,
    ]);

    Event::dispatch('contacts.person.create.after', $person);
    app(LedgerAppender::class)->append('lead.created', 'lead', 88, ['title' => 'Second Leaf'], 'human', $admin->id);
    app(LedgerVerifier::class)->sealPendingEpoch();

    $personBlock = DataBlock::query()->where('entity_type', 'person')->firstOrFail();

    // 1. Unauthenticated public HTML receipt page: shows Verified ✓ and receipt code, zero PII leaked
    $htmlResponse = test()->get('/receipts/'.$personBlock->receipt_code);
    $htmlResponse->assertSuccessful()
        ->assertSee($personBlock->receipt_code)
        ->assertSee('Verified')
        ->assertSee($personBlock->block_hash)
        ->assertDontSee('Confidential VIP Buyer')
        ->assertDontSee('vip.secret@privatebank.example')
        ->assertDontSee('+41445550199');

    // 2. Unauthenticated public JSON proof endpoint: verifies Merkle proof, omits canonical_payload / entity_summary
    $publicProof = test()->getJson('/api/ledger/blocks/'.$personBlock->receipt_code.'/proof')
        ->assertSuccessful()
        ->json();

    expect($publicProof['verified'])->toBeTrue()
        ->and($publicProof['hash_valid'])->toBeTrue()
        ->and($publicProof['signature_valid'])->toBeTrue()
        ->and($publicProof['merkle_valid'])->toBeTrue()
        ->and($publicProof['merkle_proof'])->toHaveCount(1)
        ->and(MerkleTree::verifyProof(
            $publicProof['block_hash'],
            $publicProof['merkle_proof'],
            $publicProof['epoch']['merkle_root']
        ))->toBeTrue()
        ->and($publicProof)->not->toHaveKey('entity_summary')
        ->and(json_encode($publicProof))->not->toContain('vip.secret@privatebank.example');

    // Lookup by 64-char block_hash works identically
    test()->getJson('/api/ledger/blocks/'.$personBlock->block_hash.'/proof')
        ->assertSuccessful()
        ->assertJsonPath('receipt_code', $personBlock->receipt_code);

    // 3. Authenticated admin with ledger permission receives entity_summary
    $staffProof = test()->actingAs($admin, 'user')
        ->getJson('/api/ledger/blocks/'.$personBlock->receipt_code.'/proof')
        ->assertSuccessful()
        ->json();

    expect($staffProof)->toHaveKey('entity_summary')
        ->and($staffProof['entity_summary']['label'])->toBe('Confidential VIP Buyer');

    // 4. Admin Ledger Explorer enforces settings.other_settings.ledger ACL
    $unprivilegedRole = Role::create([
        'name' => 'No Ledger Role '.bin2hex(random_bytes(3)),
        'description' => 'Basic rep',
        'permission_type' => 'custom',
        'permissions' => ['dashboard'],
    ]);

    $rep = User::create([
        'name' => 'Basic Rep',
        'email' => 'rep-'.bin2hex(random_bytes(4)).'@luminara.local',
        'password' => bcrypt('SecretPass!123'),
        'status' => 1,
        'view_permission' => 'individual',
        'role_id' => $unprivilegedRole->id,
    ]);

    test()->actingAs($rep, 'user')
        ->get(route('admin.ledger.index'))
        ->assertStatus(401);

    test()->actingAs($admin, 'user')
        ->get(route('admin.ledger.index'))
        ->assertSuccessful()
        ->assertSee($personBlock->receipt_code);
});

it('records agent.call.settled blocks with ES256 WebAuthn passkey signer proofs', function () {
    $admin = getDefaultAdmin();

    // RFC 7515 A.3 P-256 test vector
    $x = (string) LedgerSigner::base64UrlDecode('f83OJ3D2xF1Bg8vub9tLe1gHMzV76e8Tus9uPHvRVEU');
    $y = (string) LedgerSigner::base64UrlDecode('x_FEzRu9m36HLN_tue659LNpXW6pCyStikYjKIWI5a0');
    $d = (string) LedgerSigner::base64UrlDecode('jpsQnnGQmL-YBIffH1136cspYG6-0iY7X1fCE9-E9LI');
    $point = "\x04".$x.$y;

    $spkiDer = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00".$point;
    $sec1Der = "\x30\x77\x02\x01\x01\x04\x20".$d."\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\xa1\x44\x03\x42\x00".$point;

    $privPem = "-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($sec1Der), 64, "\n")."-----END EC PRIVATE KEY-----\n";
    $pubPem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spkiDer), 64, "\n")."-----END PUBLIC KEY-----\n";

    $signedChallenge = 'webauthn-assertion-call-777';
    $derSig = '';
    openssl_sign($signedChallenge, $derSig, openssl_pkey_get_private($privPem), OPENSSL_ALGO_SHA256);
    $p1363Proof = LedgerSigner::base64UrlEncode((string) LedgerSigner::derToP1363($derSig));

    $fakeCall = (object) [
        'id' => 777,
        'action' => 'quotes_draft',
        'status' => 'approved',
        'user_id' => $admin->id,
        'decided_by' => $admin->id,
        'agent_passport_id' => 12,
    ];

    Event::dispatch('agent.call.settled', [
        $fakeCall,
        [
            'alg' => 'es256',
            'pubkey' => $pubPem,
            'proof' => $p1363Proof,
            'message' => $signedChallenge,
        ],
    ]);

    $block = DataBlock::query()->where('entity_type', 'agent_action_call')->firstOrFail();

    expect($block->event_name)->toBe('agent.call.approved')
        ->and($block->actor_kind)->toBe('human')
        ->and($block->signer_alg)->toBe('es256')
        ->and($block->agent_passport_id)->toBe(12)
        ->and(app(LedgerVerifier::class)->verifyChain()['ok'])->toBeTrue();
});

it('rejects unverified external custom signers without poisoning the chain and scopes group/passport bearer proof reads', function () {
    $admin = getDefaultAdmin();

    // 1. Forged external customSigner falls back cleanly to server Ed25519 so verifyChain stays true
    $forgedBlock = app(LedgerAppender::class)->append(
        eventName: 'agent.call.completed',
        entityType: 'agent_action_call',
        entityId: 901,
        payload: ['title' => 'Forged Signer Attempt', 'user_id' => $admin->id],
        actorKind: 'human',
        actorId: $admin->id,
        customSigner: [
            'alg' => 'es256',
            'pubkey' => 'not-a-valid-pem',
            'proof' => 'Zm9yZ2VkLXByb29m',
            'message' => 'tampered-challenge',
        ],
    );

    expect($forgedBlock->signer_alg)->toBe('ed25519')
        ->and($forgedBlock->canonical_payload)->not->toHaveKey('signer_message_b64')
        ->and(app(LedgerVerifier::class)->verifyChain()['ok'])->toBeTrue();

    // 2. Group-scoped Bearer viewer (with null session guard) inspects teammate block without 500 crash
    $ledgerRole = Role::create([
        'name' => 'Group Ledger Auditor '.bin2hex(random_bytes(3)),
        'description' => 'Group ledger auditor',
        'permission_type' => 'custom',
        'permissions' => ['settings.other_settings.ledger'],
    ]);

    $teammate = User::create([
        'name' => 'Group Teammate',
        'email' => 'teammate-'.bin2hex(random_bytes(4)).'@luminara.local',
        'password' => bcrypt('SecretPass!123'),
        'status' => 1,
        'view_permission' => 'group',
        'role_id' => $ledgerRole->id,
    ]);

    $outsider = User::create([
        'name' => 'Outsider Auditor',
        'email' => 'outsider-'.bin2hex(random_bytes(4)).'@luminara.local',
        'password' => bcrypt('SecretPass!123'),
        'status' => 1,
        'view_permission' => 'group',
        'role_id' => $ledgerRole->id,
    ]);

    $sharedGroup = Group::create([
        'name' => 'EMEA Desk '.bin2hex(random_bytes(3)),
        'description' => 'Shared group',
    ]);
    $sharedGroup->users()->attach([$admin->id, $teammate->id]);

    auth()->guard('user')->logout();

    $teammateToken = $teammate->createToken('group-agent', ['agent'])->plainTextToken;
    $outsiderToken = $outsider->createToken('outsider-agent', ['agent'])->plainTextToken;
    $wrongAbilityToken = $admin->createToken('non-agent', ['other'])->plainTextToken;

    test()->getJson('/api/ledger/blocks/'.$forgedBlock->receipt_code.'/proof', [
        'Authorization' => 'Bearer '.$teammateToken,
    ])->assertOk()->assertJsonPath('entity_summary.label', 'Forged Signer Attempt');

    $outsiderJson = test()->getJson('/api/ledger/blocks/'.$forgedBlock->receipt_code.'/proof', [
        'Authorization' => 'Bearer '.$outsiderToken,
    ])->assertOk()->json();
    expect($outsiderJson)->not->toHaveKey('entity_summary');

    $wrongAbilityJson = test()->getJson('/api/ledger/blocks/'.$forgedBlock->receipt_code.'/proof', [
        'Authorization' => 'Bearer '.$wrongAbilityToken,
    ])->assertOk()->json();
    expect($wrongAbilityJson)->not->toHaveKey('entity_summary');

    // 3. Scoped or revoked Agent Passport cannot inspect entity_summary without ledger_verify/ledger_history
    $scopedToken = $admin->createToken('scoped-passport', ['agent']);
    DB::table('agent_passports')->insert([
        'user_id' => $admin->id,
        'token_id' => $scopedToken->accessToken->id,
        'slug' => 'leads-only-bot',
        'name' => 'Leads Only Bot',
        'allowed_actions' => json_encode(['leads_search']),
        'daily_call_limit' => 100,
        'trust_mode' => 'standard',
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $scopedJson = test()->getJson('/api/ledger/blocks/'.$forgedBlock->receipt_code.'/proof', [
        'Authorization' => 'Bearer '.$scopedToken->plainTextToken,
    ])->assertOk()->json();
    expect($scopedJson)->not->toHaveKey('entity_summary');
});

it('gates session proof payload summaries behind MFA completion, hides canonical_payload on admin JSON feeds, and tombstones deleted activities', function () {
    $admin = getDefaultAdmin();

    // 1. Activity creation + deletion emits pre-delete tombstone block
    test()->actingAs($admin, 'user');
    $activity = Activity::query()->create([
        'title' => 'Executive Compliance Call',
        'type' => 'call',
        'comment' => 'Confidential merger timeline',
        'is_done' => 0,
        'user_id' => $admin->id,
    ]);
    Event::dispatch('activity.create.after', $activity);
    $activityId = $activity->id;
    Event::dispatch('activity.delete.before', $activityId);
    $activity->delete();

    $tombstone = DataBlock::query()
        ->where('entity_type', 'activity')
        ->where('entity_id', (string) $activityId)
        ->where('event_name', 'activity.tombstoned')
        ->firstOrFail();

    expect($tombstone->canonical_payload['_tombstone'])->toBeTrue();

    // 2. Admin Ledger JSON feed hides canonical_payload across paginated blocks
    $adminJson = test()->actingAs($admin, 'user')
        ->getJson(route('admin.ledger.index'))
        ->assertOk()
        ->json();

    expect($adminJson['blocks']['data'])->not->toBeEmpty()
        ->and($adminJson['blocks']['data'][0])->not->toHaveKey('canonical_payload');

    // 3. Pre-MFA session cannot inspect entity_summary on /api/ledger/blocks/{code}/proof until MFA verified
    DB::table('user_mfa_settings')->updateOrInsert(
        ['user_id' => $admin->id],
        [
            'mfa_enabled' => true,
            'secret_encrypted' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'updated_at' => now(),
            'created_at' => now(),
        ]
    );

    $unverifiedProof = test()->actingAs($admin, 'user')
        ->withSession(['security.mfa_verified_user_id' => 0])
        ->getJson('/api/ledger/blocks/'.$tombstone->receipt_code.'/proof')
        ->assertOk()
        ->json();

    expect($unverifiedProof)->not->toHaveKey('entity_summary');

    $verifiedProof = test()->actingAs($admin, 'user')
        ->withSession(['security.mfa_verified_user_id' => $admin->id])
        ->getJson('/api/ledger/blocks/'.$tombstone->receipt_code.'/proof')
        ->assertOk()
        ->json();

    expect($verifiedProof)->toHaveKey('entity_summary');
});
