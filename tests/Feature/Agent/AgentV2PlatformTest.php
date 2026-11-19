<?php

use Anthropic\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Webkul\Activity\Models\Activity;
use Webkul\Agent\Console\Commands\RunAgentActionCommand;
use Webkul\Agent\Http\Middleware\AuthenticateAgentToken;
use Webkul\Agent\Models\AgentActionCall;
use Webkul\Agent\Models\AgentPassport;
use Webkul\Agent\Models\AgentPreferenceSignal;
use Webkul\Agent\Support\ActionRegistry;
use Webkul\Agent\Support\AgentWebhookDispatcher;
use Webkul\Agent\Support\Assistant;
use Webkul\Agent\Support\PlaybookMemory;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Ledger\Support\LedgerVerifier;
use Webkul\Product\Models\Product;
use Webkul\Quote\Models\Quote;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

/**
 * Issue a scoped Agent Passport + Sanctum bearer header for tests.
 *
 * @param  array<string, mixed>  $attributes
 * @return array{passport: AgentPassport, headers: array<string, string>}
 */
function v2PassportHeaders(User $user, array $attributes = []): array
{
    $slug = $attributes['slug'] ?? 'bot-'.strtolower(substr(uniqid(), -6));
    $token = $user->createToken("passport:{$slug}", [AuthenticateAgentToken::ABILITY]);

    /** @var AgentPassport $passport */
    $passport = AgentPassport::query()->create(array_merge([
        'user_id' => $user->id,
        'token_id' => $token->accessToken->getKey(),
        'slug' => $slug,
        'name' => ucfirst($slug),
        'allowed_actions' => null,
        'daily_call_limit' => 1000,
        'auto_approve_value_ceiling' => null,
        'trust_mode' => AgentPassport::TRUST_STANDARD,
        'webhook_url' => null,
        'webhook_secret' => null,
        'status' => true,
    ], $attributes));

    return [
        'passport' => $passport,
        'headers' => ['Authorization' => 'Bearer '.$token->plainTextToken],
    ];
}

function v2Product(string $name = 'Luminara Telemetry Sensor', float $price = 500.0): Product
{
    return Product::query()->create([
        'name' => $name,
        'sku' => 'SKU-'.strtoupper(uniqid()),
        'description' => 'Precision industrial sensor module',
        'quantity' => 100,
        'price' => $price,
    ]);
}

/**
 * @param  array<int, array<string, mixed>>  $replies
 * @return ArrayObject<int, array<string, mixed>>
 */
function v2FakeAssistantModel(array $replies, ?callable $onRequest = null): ArrayObject
{
    config(['agent.assistant.api_key' => 'test-key']);

    $requests = new ArrayObject;

    $stack = HandlerStack::create(new MockHandler(array_map(
        fn (array $reply) => new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($reply + [
            'id' => uniqid('msg_'),
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5-5',
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ])),
        $replies
    )));

    $stack->push(Middleware::mapRequest(function ($request) use ($requests, $onRequest) {
        $decoded = json_decode((string) $request->getBody(), true);
        $requests[] = $decoded;

        if ($onRequest !== null) {
            $onRequest($decoded, count($requests));
        }

        return $request;
    }));

    app()->when(Assistant::class)->needs(Client::class)->give(fn () => new Client(
        apiKey: 'test-key',
        requestOptions: ['transporter' => new GuzzleClient(['handler' => $stack]), 'maxRetries' => 0],
    ));

    return $requests;
}

it('registers all 26 atomic CRM actions in the registry and public discovery endpoints', function () {
    $names = array_keys(app(ActionRegistry::class)->all());

    expect($names)->toHaveCount(26)
        ->toContain(
            'leads_search',
            'leads_get',
            'leads_create',
            'leads_update',
            'leads_move_stage',
            'leads_delete',
            'intake_capture',
            'contacts_search',
            'contacts_get',
            'contacts_upsert_person',
            'contacts_upsert_organization',
            'contacts_merge_persons',
            'products_search',
            'quotes_search',
            'quotes_get',
            'quotes_draft',
            'quotes_delete',
            'activities_list',
            'activities_log_note',
            'activities_schedule',
            'activities_complete',
            'briefing_today',
            'playbook_read',
            'playbook_propose_rule',
            'ledger_verify',
            'ledger_history',
        );

    $this->get('/llms.txt')
        ->assertOk()
        ->assertSee('Luminara CRM — Agent Discovery Guide')
        ->assertSee('Atomic Actions (26)')
        ->assertSee('intake_capture')
        ->assertSee('quotes_draft');

    $this->getJson('/.well-known/agent.json')
        ->assertOk()
        ->assertJsonPath('capabilities.dry_run', true)
        ->assertJsonPath('capabilities.edit_to_sign', true)
        ->assertJsonPath('capabilities.playbook_memory', true)
        ->assertJsonPath('capabilities.actions_count', 26);
});

it('simulates mutating and gated actions inside a rolled-back savepoint when dry_run is requested', function () {
    $user = agentUser();
    $headers = agentHeaders($user);
    $leadCountBefore = Lead::query()->count();
    $callCountBefore = AgentActionCall::query()->count();

    $this->postJson('/api/agent/actions/leads_create', [
        'title' => 'Dry run lead via JSON',
        'lead_value' => 8400,
        'person_name' => 'Nora Vance',
        'person_email' => 'nora.dryrun@example.com',
        'dry_run' => true,
    ], $headers)
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('dry_run', true)
        ->assertJsonPath('action', 'leads_create')
        ->assertJsonPath('would_require_approval', false)
        ->assertJsonPath('preview.lead.title', 'Dry run lead via JSON');

    $existingLead = agentLead($user, 'Protected lead');

    $this->postJson('/api/agent/actions/leads_delete', [
        'lead_id' => $existingLead->id,
    ], $headers + ['X-Luminara-Dry-Run' => '1'])
        ->assertOk()
        ->assertJsonPath('dry_run', true)
        ->assertJsonPath('would_require_approval', true)
        ->assertJsonPath('subject.0.id', $existingLead->id)
        ->assertJsonPath('preview.deleted_lead_id', $existingLead->id);

    expect(Lead::query()->where('title', 'Dry run lead via JSON')->exists())->toBeFalse()
        ->and(Person::query()->where('emails', 'like', '%nora.dryrun@example.com%')->exists())->toBeFalse()
        ->and(Lead::query()->whereKey($existingLead->id)->exists())->toBeTrue()
        ->and(Lead::query()->count())->toBe($leadCountBefore + 1)
        ->and(AgentActionCall::query()->count())->toBe($callCountBefore);
});

it('deterministically captures inbound leads from raw text, reuses existing contacts, and synthesizes fallback emails', function () {
    $user = agentUser();
    $headers = agentHeaders($user);

    $rawEmail = <<<'TXT'
From: "Elena Rostova" <elena@acmerobotics.io>
Subject: Fwd: Annual sensor fleet rollout
Company: Acme Robotics
Phone: +1 (415) 555-0194

Hi team, we are budgeting $45k for Q4 telemetry units across 3 warehouses.
TXT;

    $first = $this->postJson('/api/agent/actions/intake_capture', [
        'raw_text' => $rawEmail,
        'source_channel' => 'email_forward',
    ], $headers)->assertOk();

    $personId = $first->json('result.person.id');
    $leadId = $first->json('result.lead.id');
    $activityId = $first->json('result.activity_id');

    expect($first->json('result.person.name'))->toBe('Elena Rostova')
        ->and($first->json('result.person.email'))->toBe('elena@acmerobotics.io')
        ->and($first->json('result.lead.title'))->toBe('Annual sensor fleet rollout')
        ->and((float) $first->json('result.lead.lead_value'))->toBe(45000.0)
        ->and(Activity::query()->whereKey($activityId)->exists())->toBeTrue();

    // Second intake from same email reuses the person rather than duplicating contacts.
    $second = $this->postJson('/api/agent/actions/intake_capture', [
        'raw_text' => 'Follow-up from elena@acmerobotics.io requesting USD 12,500 add-on spare kit.',
    ], $headers)->assertOk();

    expect($second->json('result.person.id'))->toBe($personId)
        ->and($second->json('result.lead.id'))->not->toBe($leadId)
        ->and((float) $second->json('result.lead.lead_value'))->toBe(12500.0);

    // Raw text with no email synthesizes a deterministic @intake.luminara.invalid address.
    $noEmail = $this->postJson('/api/agent/actions/intake_capture', [
        'raw_text' => 'This is Marcus Chen from Vertex Logistics, call me at +1 650 555 0148 about $9,200 sensors.',
    ], $headers)->assertOk();

    expect($noEmail->json('result.person.name'))->toBe('Marcus Chen')
        ->and($noEmail->json('result.person.email'))->toEndWith('@intake.luminara.invalid')
        ->and((float) $noEmail->json('result.lead.lead_value'))->toBe(9200.0);
});

it('executes contact, product, quote, lead update, and activity actions while enforcing strict scope isolation', function () {
    $owner = agentUser(['leads', 'leads.view', 'leads.create', 'leads.edit', 'contacts', 'contacts.persons', 'contacts.persons.create', 'contacts.persons.edit', 'contacts.persons.delete', 'contacts.organizations', 'contacts.organizations.create', 'products', 'quotes', 'quotes.create', 'quotes.delete', 'activities', 'activities.create', 'activities.edit'], 'individual');
    $other = agentUser(null, 'individual');

    $ownerHeaders = agentHeaders($owner);
    $otherHeaders = agentHeaders($other);
    $product = v2Product('Industrial Gateway X1', 1200.0);

    // Organization + Person upserts & search
    $orgId = $this->postJson('/api/agent/actions/contacts_upsert_organization', [
        'name' => 'Helios Solar',
        'city' => 'Austin',
        'country' => 'US',
    ], $ownerHeaders)->assertOk()->json('result.organization.id');

    $primaryId = $this->postJson('/api/agent/actions/contacts_upsert_person', [
        'name' => 'Tariq Al-Mansoor',
        'email' => 'tariq@heliossolar.example',
        'phone' => '+15125550101',
        'organization_id' => $orgId,
    ], $ownerHeaders)->assertOk()->json('result.person.id');

    $duplicateId = $this->postJson('/api/agent/actions/contacts_upsert_person', [
        'name' => 'Tariq Mansoor (Dup)',
        'email' => 'tariq.alt@heliossolar.example',
    ], $ownerHeaders)->assertOk()->json('result.person.id');

    $this->postJson('/api/agent/actions/contacts_search', ['query' => 'Tariq'], $ownerHeaders)
        ->assertOk()
        ->assertJsonCount(2, 'result.persons');

    // Cross-user isolation: another individual-scoped user cannot read owner's contact.
    app('auth')->forgetGuards();
    $this->postJson('/api/agent/actions/contacts_get', ['person_id' => $primaryId], $otherHeaders)
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');

    // Lead creation + update + activities
    app('auth')->forgetGuards();
    $lead = agentLead($owner, 'Helios Phase 1');
    $lead->update(['person_id' => $duplicateId]);

    $this->postJson('/api/agent/actions/leads_update', [
        'lead_id' => $lead->id,
        'title' => 'Helios Phase 1 Expanded',
        'lead_value' => 14400,
    ], $ownerHeaders)
        ->assertOk()
        ->assertJsonPath('result.lead.title', 'Helios Phase 1 Expanded');

    $noteId = $this->postJson('/api/agent/actions/activities_log_note', [
        'comment' => 'Confirmed roof mounting specs.',
        'lead_id' => $lead->id,
        'person_id' => $duplicateId,
    ], $ownerHeaders)->assertOk()->json('result.activity.id');

    $schedId = $this->postJson('/api/agent/actions/activities_schedule', [
        'type' => 'call',
        'title' => 'Review BOM with Tariq',
        'schedule_from' => now()->addHour()->toIso8601String(),
        'lead_id' => $lead->id,
    ], $ownerHeaders)->assertOk()->json('result.activity.id');

    $this->postJson('/api/agent/actions/activities_complete', [
        'activity_id' => $schedId,
        'outcome_comment' => 'BOM signed off.',
    ], $ownerHeaders)->assertOk()->assertJsonPath('result.activity.is_done', true);

    $this->postJson('/api/agent/actions/activities_list', ['lead_id' => $lead->id], $ownerHeaders)
        ->assertOk()
        ->assertJsonCount(2, 'result.activities');

    // Products search + Quotes draft (<= 15% runs immediately; > 15% waits for approval)
    $this->postJson('/api/agent/actions/products_search', ['query' => 'Gateway X1'], $ownerHeaders)
        ->assertOk()
        ->assertJsonPath('result.products.0.id', $product->id);

    $autoQuote = $this->postJson('/api/agent/actions/quotes_draft', [
        'subject' => 'Helios Standard Quote',
        'person_id' => $duplicateId,
        'lead_id' => $lead->id,
        'discount_percent' => 10,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2],
        ],
    ], $ownerHeaders)->assertOk();

    $quoteId = $autoQuote->json('result.quote.id');
    expect((float) $autoQuote->json('result.quote.grand_total'))->toBe(2160.0);

    $this->postJson('/api/agent/actions/quotes_get', ['quote_id' => $quoteId], $ownerHeaders)
        ->assertOk()
        ->assertJsonPath('result.quote.subject', 'Helios Standard Quote');

    // Merge duplicate person into primary person (always requires human approval)
    $mergeCallId = $this->postJson('/api/agent/actions/contacts_merge_persons', [
        'primary_person_id' => $primaryId,
        'duplicate_person_id' => $duplicateId,
    ], $ownerHeaders)
        ->assertStatus(202)
        ->assertJsonPath('status', 'pending_approval')
        ->json('call_id');

    $this->actingAs(getDefaultAdmin(), 'user')
        ->postJson(route('admin.agent.approvals.approve', $mergeCallId))
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    expect(Person::query()->whereKey($duplicateId)->exists())->toBeFalse()
        ->and($lead->refresh()->person_id)->toBe($primaryId)
        ->and(Quote::query()->findOrFail($quoteId)->person_id)->toBe($primaryId)
        ->and(Person::query()->findOrFail($primaryId)->activities()->whereKey($noteId)->exists())->toBeTrue();

    // Quote delete also requires human approval
    auth()->guard('user')->logout();
    $deleteQuoteCallId = $this->postJson('/api/agent/actions/quotes_delete', [
        'quote_id' => $quoteId,
    ], $ownerHeaders)
        ->assertStatus(202)
        ->json('call_id');

    $this->actingAs(getDefaultAdmin(), 'user')
        ->postJson(route('admin.agent.approvals.approve', $deleteQuoteCallId))
        ->assertOk();

    expect(Quote::query()->whereKey($quoteId)->exists())->toBeFalse();
});

it('enforces Agent Passport scopes, daily call budgets, revocation, trust modes, and HMAC-signed webhooks', function () {
    Http::fake(['https://hooks.example.org/*' => Http::response(['received' => true], 200)]);

    $user = agentUser();
    $product = v2Product('Edge Node', 800.0);
    $personResp = $this->postJson('/api/agent/actions/contacts_upsert_person', [
        'name' => 'Sora Takahashi',
        'email' => 'sora@example.com',
    ], agentHeaders($user))->assertOk();
    $personId = $personResp->json('result.person.id');

    // 1. Scoped + daily-limited passport
    app('auth')->forgetGuards();
    ['passport' => $scopedPassport, 'headers' => $scopedHeaders] = v2PassportHeaders($user, [
        'slug' => 'scoped-bot',
        'allowed_actions' => ['leads_*', 'briefing_today'],
        'daily_call_limit' => 2,
    ]);

    $contextActions = collect($this->getJson('/api/agent/context', $scopedHeaders)->assertOk()->json('actions'))->keyBy('name');
    expect($contextActions['leads_search']['allowed'])->toBeTrue()
        ->and($contextActions['quotes_draft']['allowed'])->toBeFalse();

    $this->postJson('/api/agent/actions/quotes_draft', [
        'subject' => 'Denied by scope',
        'person_id' => $personId,
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ], $scopedHeaders)
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'passport_scope_denied');

    $this->postJson('/api/agent/actions/leads_search', [], $scopedHeaders)->assertOk();
    $this->postJson('/api/agent/actions/briefing_today', [], $scopedHeaders)->assertOk();
    $this->postJson('/api/agent/actions/leads_search', [], $scopedHeaders)
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'passport_budget_exceeded');

    // Pause / revoke passport
    $scopedPassport->update(['status' => false, 'daily_call_limit' => 100]);
    $this->postJson('/api/agent/actions/leads_search', [], $scopedHeaders)
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'passport_revoked');

    // 2. earned_auto trust mode skips conditional approval under ceiling, holds above ceiling
    app('auth')->forgetGuards();
    ['headers' => $earnedHeaders] = v2PassportHeaders($user, [
        'slug' => 'earned-bot',
        'trust_mode' => AgentPassport::TRUST_EARNED_AUTO,
        'auto_approve_value_ceiling' => 5000.00,
    ]);

    // 25% discount normally requires approval, but grand_total ($1,200) <= $5,000 ceiling -> auto-runs!
    $this->postJson('/api/agent/actions/quotes_draft', [
        'subject' => 'Under Ceiling Auto Quote',
        'person_id' => $personId,
        'discount_percent' => 25,
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ], $earnedHeaders)
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    // Grand total ($12,000) > $5,000 ceiling -> waits for human signature
    $this->postJson('/api/agent/actions/quotes_draft', [
        'subject' => 'Above Ceiling Gated Quote',
        'person_id' => $personId,
        'discount_percent' => 25,
        'items' => [['product_id' => $product->id, 'quantity' => 20]],
    ], $earnedHeaders)
        ->assertStatus(202)
        ->assertJsonPath('status', 'pending_approval');

    // 3. always_confirm trust mode + signed HMAC webhook on decision
    app('auth')->forgetGuards();
    ['passport' => $confirmPassport, 'headers' => $confirmHeaders] = v2PassportHeaders($user, [
        'slug' => 'confirm-bot',
        'trust_mode' => AgentPassport::TRUST_ALWAYS_CONFIRM,
        'webhook_url' => 'https://hooks.example.org/agent-callback',
        'webhook_secret' => 'top-secret-hmac-key',
    ]);

    $heldCallId = $this->postJson('/api/agent/actions/leads_create', [
        'title' => 'Confirm Mode Lead',
        'person_id' => $personId,
    ], $confirmHeaders)
        ->assertStatus(202)
        ->assertJsonPath('status', 'pending_approval')
        ->json('call_id');

    $this->actingAs(getDefaultAdmin(), 'user')
        ->postJson(route('admin.agent.approvals.approve', $heldCallId))
        ->assertOk();

    expect($confirmPassport->refresh()->approved_streak)->toBe(1);

    Http::assertSent(function (HttpClientRequest $request) use ($heldCallId) {
        $expectedSig = 'sha256='.hash_hmac('sha256', $request->body(), 'top-secret-hmac-key');

        return $request->url() === 'https://hooks.example.org/agent-callback'
            && $request->hasHeader('X-Luminara-Event', 'agent.call.decided')
            && $request->hasHeader('X-Luminara-Signature', $expectedSig)
            && ($request['call_id'] ?? null) === $heldCallId
            && ($request['status'] ?? null) === 'completed';
    });

    // SSRF guard rejects loopback and cloud metadata endpoints
    $dispatcher = app(AgentWebhookDispatcher::class);
    expect($dispatcher->isSafeUrl('http://127.0.0.1/hook'))->toBeFalse()
        ->and($dispatcher->isSafeUrl('http://169.254.169.254/latest/meta-data'))->toBeFalse()
        ->and($dispatcher->isSafeUrl('http://localhost:8080/hook'))->toBeFalse();
});

it('supports Edit-to-Sign, distills Playbook Memory rules, enforces append-only calls, and emits settled events', function () {
    $settledPayloads = [];
    Event::listen('agent.call.settled', function (AgentActionCall $call, ?array $proof) use (&$settledPayloads): void {
        $settledPayloads[] = ['id' => $call->id, 'status' => $call->status, 'proof' => $proof];
    });

    $user = agentUser();
    $headers = agentHeaders($user);
    $product = v2Product('Annual Platform Seat', 1000.0);

    $personId = $this->postJson('/api/agent/actions/contacts_upsert_person', [
        'name' => 'Claire Dubois',
        'email' => 'claire@atelier.example',
    ], $headers)->assertOk()->json('result.person.id');

    // 30% discount triggers conditional approval
    $callId = $this->postJson('/api/agent/actions/quotes_draft', [
        'subject' => 'Atelier 10-Seat Proposal',
        'person_id' => $personId,
        'discount_percent' => 30,
        'items' => [['product_id' => $product->id, 'quantity' => 10]],
        'reason' => 'Customer asked for 30% off.',
    ], $headers)
        ->assertStatus(202)
        ->json('call_id');

    // Human edits discount down to 10%, leaves note, attaches signer_proof, and distills into playbook
    $this->actingAs(getDefaultAdmin(), 'user')
        ->postJson(route('admin.agent.approvals.approve', $callId), [
            'edited_params' => [
                'subject' => 'Atelier 10-Seat Proposal',
                'person_id' => $personId,
                'discount_percent' => 10,
                'items' => [['product_id' => $product->id, 'quantity' => 10]],
            ],
            'feedback_note' => 'Cap multi-seat discounts at 10% without multi-year prepay.',
            'distill_rule' => true,
            'signer_proof' => ['method' => 'webauthn', 'credential_id' => 'cred_test_1'],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    /** @var AgentActionCall $call */
    $call = AgentActionCall::query()->findOrFail($callId);

    expect((float) $call->result['quote']['grand_total'])->toBe(9000.0)
        ->and($call->edited_params['discount_percent'])->toBe(10)
        ->and($call->human_feedback)->toContain('Cap multi-seat discounts at 10%')
        ->and($call->signer_proof)->toMatchArray(['method' => 'webauthn'])
        ->and(AgentPreferenceSignal::query()->where('call_id', $callId)->where('signal_type', 'edit_correction')->count())->toBe(1)
        ->and(app(PlaybookMemory::class)->promptSummary())->toContain('<business_playbook>')
        ->and(app(PlaybookMemory::class)->promptSummary())->toContain('Cap multi-seat discounts at 10%')
        ->and(collect($settledPayloads)->last())->toMatchArray(['id' => $callId, 'status' => 'completed']);

    // AgentActionCall rows are strictly append-only
    expect(fn () => $call->delete())->toThrow(LogicException::class);

    // playbook_propose_rule gates on human signature, then updates slot
    auth()->guard('user')->logout();
    $ruleCallId = $this->postJson('/api/agent/actions/playbook_propose_rule', [
        'slot' => 'communication_voice',
        'rule' => 'Keep outbound follow-ups under 90 words.',
    ], $headers)->assertStatus(202)->json('call_id');

    $this->actingAs(getDefaultAdmin(), 'user')
        ->postJson(route('admin.agent.approvals.approve', $ruleCallId))
        ->assertOk();

    expect(app(PlaybookMemory::class)->allBlocks()['communication_voice']['content'])
        ->toContain('Keep outbound follow-ups under 90 words.');
});

it('serves MCP v2 resources and prompts and streams incremental catch-up events', function () {
    $user = agentUser();
    $headers = agentHeaders($user);
    app(PlaybookMemory::class)->upsertBlock('business_identity', 'Luminara Industrial Telemetry B2B', $user->id);

    $this->postJson('/api/agent/actions/leads_create', [
        'title' => 'MCP Event Lead',
        'person_name' => 'Amina Yusuf',
        'person_email' => 'amina@example.com',
    ], $headers)->assertOk();

    $this->postJson('/api/agent/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => ['protocolVersion' => '2025-06-18'],
    ], $headers)
        ->assertOk()
        ->assertJsonStructure(['result' => ['capabilities' => ['tools', 'resources', 'prompts']]]);

    $this->postJson('/api/agent/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'resources/list'], $headers)
        ->assertOk()
        ->assertJsonCount(4, 'result.resources');

    foreach ([
        'luminara://briefing/today',
        'luminara://memory/playbook',
        'luminara://ledger/head',
        'luminara://context/schema',
    ] as $idx => $uri) {
        $read = $this->postJson('/api/agent/mcp', [
            'jsonrpc' => '2.0',
            'id' => 10 + $idx,
            'method' => 'resources/read',
            'params' => ['uri' => $uri],
        ], $headers)->assertOk();

        expect($read->json('result.contents.0.uri'))->toBe($uri)
            ->and($read->json('result.contents.0.text'))->toStartWith('<untrusted_crm_data>');
    }

    $this->postJson('/api/agent/mcp', ['jsonrpc' => '2.0', 'id' => 20, 'method' => 'prompts/list'], $headers)
        ->assertOk()
        ->assertJsonCount(4, 'result.prompts');

    $prompt = $this->postJson('/api/agent/mcp', [
        'jsonrpc' => '2.0',
        'id' => 21,
        'method' => 'prompts/get',
        'params' => ['name' => 'intake', 'arguments' => ['raw_text' => 'Jane Doe <jane@acme.io>']],
    ], $headers)->assertOk();

    expect($prompt->json('result.messages.0.content.text'))
        ->toContain('intake_capture')
        ->toContain('Luminara Industrial Telemetry B2B');

    $events = $this->getJson('/api/agent/events?since_id=0', $headers)
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($events->json('events'))->not->toBeEmpty()
        ->and($events->json('playbook_version'))->toBeGreaterThanOrEqual(1);
});

it('executes CLI passport issuance and luminara:act with deterministic exit codes 0, 2, and 3', function () {
    $user = agentUser();
    $lead = agentLead($user, 'CLI Gated Lead');

    $exitIssue = Artisan::call('luminara:passport', [
        'email' => $user->email,
        'slug' => 'cli-agent',
        '--scopes' => ['leads_search,leads_delete,briefing_today'],
        '--daily-limit' => 50,
        '--json' => true,
    ]);

    $issuedJson = json_decode(trim(Artisan::output()), true);
    expect($exitIssue)->toBe(0)
        ->and($issuedJson['ok'])->toBeTrue()
        ->and($issuedJson['passport']['slug'])->toBe('cli-agent')
        ->and($issuedJson['token'])->toBeString();

    // Exit 0 on dry-run & completed read
    $exitOk = Artisan::call('luminara:act', [
        'action' => 'briefing_today',
        '--as' => $user->email,
        '--passport' => 'cli-agent',
        '--json' => true,
    ]);
    expect($exitOk)->toBe(RunAgentActionCommand::EXIT_COMPLETED);

    // Exit 2 on pending_approval
    $exitPending = Artisan::call('luminara:act', [
        'action' => 'leads_delete',
        '--params' => json_encode(['lead_id' => $lead->id]),
        '--as' => $user->email,
        '--passport' => 'cli-agent',
        '--json' => true,
    ]);
    expect($exitPending)->toBe(RunAgentActionCommand::EXIT_PENDING_APPROVAL)
        ->and(Lead::query()->whereKey($lead->id)->exists())->toBeTrue();

    // Exit 3 on scope denial
    $exitDenied = Artisan::call('luminara:act', [
        'action' => 'leads_create',
        '--params' => json_encode(['title' => 'Blocked', 'person_name' => 'A', 'person_email' => 'a@example.com']),
        '--as' => $user->email,
        '--passport' => 'cli-agent',
        '--json' => true,
    ]);
    expect($exitDenied)->toBe(RunAgentActionCommand::EXIT_ERROR);
});

it('supports Assistant Stop mid-loop, Pencil Draft review stamping, Owner Briefing UI, and 10-locale key parity', function () {
    $user = agentUser();
    app(PlaybookMemory::class)->upsertBlock('active_priorities', 'Prioritize enterprise renewals.', $user->id);

    // Assistant injects playbook into system prompt & honors server-side Stop between steps
    $armStopOnce = true;
    Event::listen('lead.create.after', function () use ($user, &$armStopOnce): void {
        if ($armStopOnce) {
            $armStopOnce = false;
            Cache::put(Assistant::stopCacheKey($user->id), true, 60);
        }
    });

    $requests = v2FakeAssistantModel([
        [
            'stop_reason' => 'tool_use',
            'content' => [[
                'type' => 'tool_use',
                'id' => 'toolu_step1',
                'name' => 'leads_create',
                'input' => ['title' => 'Pencil Draft Lead', 'person_name' => 'Liam Cho', 'person_email' => 'liam@example.com'],
            ]],
        ],
        [
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'Should never be reached when stopped.']],
        ],
    ]);

    $reply = $this->actingAs($user, 'user')
        ->postJson(route('admin.agent.assistant.send'), ['message' => 'Create a draft lead then continue'])
        ->assertOk();

    expect($requests[0]['system'])->toContain('<business_playbook>')
        ->and($requests[0]['system'])->toContain('Prioritize enterprise renewals.')
        ->and($reply->json('reply'))->toBe(trans('agent::app.assistant.stopped'))
        ->and($requests)->toHaveCount(1);

    // Stop endpoint sets stop flag cleanly
    $this->actingAs($user, 'user')
        ->postJson(route('admin.agent.assistant.stop'))
        ->assertOk()
        ->assertJsonPath('stopped', true);
    Cache::forget(Assistant::stopCacheKey($user->id));

    // Unreviewed create appears in briefing until marked reviewed
    $draftCall = AgentActionCall::query()->where('user_id', $user->id)->where('action', 'leads_create')->latest('id')->firstOrFail();
    expect($draftCall->reviewed_at)->toBeNull();

    $this->actingAs($user, 'user')
        ->get(route('admin.agent.briefing.index'))
        ->assertOk()
        ->assertSee(trans('agent::app.briefing.title'))
        ->assertSee('leads_create');

    $this->actingAs($user, 'user')
        ->postJson(route('admin.agent.approvals.review', $draftCall->id))
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($draftCall->refresh()->reviewed_at)->not->toBeNull();

    // Owner Simple Mode passport issuance & playbook update
    $this->actingAs($user, 'user')
        ->postJson(route('admin.agent.briefing.passports.store'), [
            'slug' => 'ui-grokbot',
            'name' => 'UI Grokbot',
            'scopes' => 'leads_*,briefing_today',
            'daily_call_limit' => 500,
            'trust_mode' => 'standard',
        ])
        ->assertStatus(201)
        ->assertJsonPath('ok', true)
        ->assertJsonPath('issued.slug', 'ui-grokbot');

    $this->actingAs($user, 'user')
        ->postJson(route('admin.agent.briefing.playbook.update'), [
            'slot' => 'pricing_guardrails',
            'content' => 'Never exceed 15% discount without CFO approval.',
        ])
        ->assertOk()
        ->assertJsonPath('version', 1);

    // 10-locale translation parity check
    $locales = ['ar', 'en', 'es', 'fa', 'ja', 'ko', 'pt_BR', 'tr', 'vi', 'zh_CN'];
    $enKeys = array_keys(Arr::dot(require base_path('packages/Webkul/Agent/src/Resources/lang/en/app.php')));
    sort($enKeys);

    foreach ($locales as $locale) {
        $localePath = base_path("packages/Webkul/Agent/src/Resources/lang/{$locale}/app.php");
        $keys = array_keys(Arr::dot(require $localePath));
        sort($keys);

        expect($keys)->toBe($enKeys);
    }
});

it('handles unknown ledger_verify codes cleanly, populates ledger_history height, enforces MCP resource role ACL, and strips unverified web signer proofs', function () {
    DB::table('data_blocks')->delete();
    DB::table('ledger_epochs')->delete();
    DB::table('ledger_heads')->delete();

    $owner = agentUser();
    $ownerHeaders = agentHeaders($owner);

    // 1. Unknown receipt_code returns verified=false on both dry_run and live calls without TypeError
    $this->postJson('/api/agent/actions/ledger_verify', [
        'receipt_code' => 'LUM-00000000000000000000000000',
        'dry_run' => true,
    ], $ownerHeaders)
        ->assertOk()
        ->assertJsonPath('dry_run', true)
        ->assertJsonPath('preview.verified', false);

    $this->postJson('/api/agent/actions/ledger_verify', [
        'receipt_code' => 'LUM-00000000000000000000000000',
    ], $ownerHeaders)
        ->assertOk()
        ->assertJsonPath('result.verified', false);

    // 2. ledger_history maps block_height to positive integer height
    $leadRes = $this->postJson('/api/agent/actions/leads_create', [
        'title' => 'Height Verification Deal',
        'lead_value' => 4200,
        'person_name' => 'Nadia Vance',
        'person_email' => 'nadia.height@example.com',
    ], $ownerHeaders)->assertOk();
    $leadId = (int) $leadRes->json('result.lead.id');

    $historyRes = $this->postJson('/api/agent/actions/ledger_history', [
        'entity_type' => 'lead',
        'entity_id' => $leadId,
    ], $ownerHeaders)->assertOk();

    expect($historyRes->json('result.blocks.0.height'))->toBeInt()->toBeGreaterThan(0)
        ->and($historyRes->json('result.blocks.0.block_height'))->toBe($historyRes->json('result.blocks.0.height'));

    // 3. MCP resources/read and tools/list enforce Gate-1 Bouncer role permissions
    $noLeadsUser = agentUser(['contacts.persons']);
    $noLeadsHeaders = agentHeaders($noLeadsUser);

    $this->postJson('/api/agent/mcp', [
        'jsonrpc' => '2.0',
        'id' => 11,
        'method' => 'resources/read',
        'params' => ['uri' => 'luminara://briefing/today'],
    ], $noLeadsHeaders)
        ->assertOk()
        ->assertJsonPath('error.code', -32001);

    $mcpTools = $this->postJson('/api/agent/mcp', [
        'jsonrpc' => '2.0',
        'id' => 12,
        'method' => 'tools/list',
    ], $noLeadsHeaders)->assertOk()->json('result.tools');

    expect(array_column($mcpTools, 'name'))->not->toContain('briefing_today');

    // 4. Web ApprovalController strips unverified cryptographic fields from signer_proof
    $pendingDelete = $this->postJson('/api/agent/actions/leads_delete', [
        'lead_id' => $leadId,
        'reason' => 'Test forged web proof sanitization',
    ], $ownerHeaders)->assertStatus(202);
    $deleteCallId = (int) $pendingDelete->json('call_id');

    $this->actingAs($owner, 'user')
        ->postJson(route('admin.agent.approvals.approve', $deleteCallId), [
            'signer_proof' => [
                'method' => 'web',
                'alg' => 'es256',
                'pubkey' => 'forged-pubkey',
                'proof' => 'forged-proof',
                'webauthn_verified' => true,
            ],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    /** @var AgentActionCall $settledCall */
    $settledCall = AgentActionCall::query()->findOrFail($deleteCallId);
    expect($settledCall->signer_proof)->toBe(['method' => 'web'])
        ->and(app(LedgerVerifier::class)->verifyChain()['ok'])->toBeTrue();
});
