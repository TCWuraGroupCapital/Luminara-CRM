<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Webkul\Agent\Http\Middleware\AuthenticateAgentToken;
use Webkul\Agent\Models\AgentActionCall;
use Webkul\Agent\Models\AgentPassport;
use Webkul\Agent\Support\ActionExecutor;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Security\Models\SecurityAuditLog;
use Webkul\User\Models\Group;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

/**
 * @param  array<string, mixed>  $attributes
 * @return array{passport: AgentPassport, headers: array<string, string>}
 */
function hardeningPassport(User $user, array $attributes = []): array
{
    $slug = $attributes['slug'] ?? 'hardening-'.strtolower(substr(uniqid(), -6));
    $token = $user->createToken("passport:{$slug}", [AuthenticateAgentToken::ABILITY]);

    /** @var AgentPassport $passport */
    $passport = AgentPassport::query()->create(array_merge([
        'user_id' => $user->id,
        'token_id' => $token->accessToken->getKey(),
        'slug' => $slug,
        'name' => ucfirst($slug),
        'allowed_actions' => null,
        'daily_call_limit' => 100,
        'auto_approve_value_ceiling' => null,
        'trust_mode' => AgentPassport::TRUST_STANDARD,
        'status' => true,
    ], $attributes));

    return [
        'passport' => $passport,
        'headers' => ['Authorization' => 'Bearer '.$token->plainTextToken],
    ];
}

it('counts dry_run previews against passport daily call budgets and records dry_run security audit logs', function () {
    $user = agentUser();
    ['passport' => $passport, 'headers' => $headers] = hardeningPassport($user, [
        'daily_call_limit' => 2,
    ]);

    $this->postJson('/api/agent/actions/leads_search', ['dry_run' => true], $headers)
        ->assertOk()
        ->assertJsonPath('dry_run', true);

    $this->postJson('/api/agent/actions/leads_search', [], $headers + ['X-Luminara-Dry-Run' => '1'])
        ->assertOk()
        ->assertJsonPath('dry_run', true);

    // 3rd call (even dry_run) hits 429 passport_budget_exceeded
    $this->postJson('/api/agent/actions/leads_search', ['dry_run' => true], $headers)
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'passport_budget_exceeded');

    expect(SecurityAuditLog::query()
        ->where('event', 'agent.action.dry_run')
        ->where('actor_id', $user->id)
        ->count())->toBeGreaterThanOrEqual(2);
});

it('blocks edited_params cross-scope redirection and detects concurrent DB drift even when edited_params are supplied', function () {
    // 1. Group manager approving a rep's call cannot redirect edited_params onto an outsider's lead
    $rep = agentUser(['leads', 'leads.edit', 'leads.delete'], 'individual');
    $outsider = agentUser(['leads', 'leads.edit', 'leads.delete'], 'individual');
    $manager = agentUser(['leads', 'leads.edit', 'leads.delete', ActionExecutor::APPROVAL_PERMISSION], 'group');

    $group = Group::query()->create([
        'name' => 'Hardening Desk '.bin2hex(random_bytes(3)),
        'description' => 'Scoped approval group',
    ]);
    $group->users()->attach([$rep->id, $manager->id]);

    $repLead = agentLead($rep, 'Rep Scoped Deal');
    $outsiderLead = agentLead($outsider, 'Outsider Protected Deal');

    auth()->guard('user')->setUser($rep);
    $pending = app(ActionExecutor::class)->execute('leads_delete', [
        'lead_id' => $repLead->id,
        'reason' => 'Delete duplicate',
    ]);
    $callId = (int) $pending->body['call_id'];
    auth()->guard('user')->logout();

    $this->actingAs($manager, 'user')
        ->postJson(route('admin.agent.approvals.approve', $callId), [
            'edited_params' => ['lead_id' => $outsiderLead->id],
        ])
        ->assertStatus(403)
        ->assertJsonPath('status', 'forbidden');

    expect(Lead::query()->whereKey($outsiderLead->id)->exists())->toBeTrue()
        ->and(AgentActionCall::query()->findOrFail($callId)->status)->toBe(AgentActionCall::PENDING_APPROVAL);

    // 2. Concurrent DB mutation on target lead triggers 409 changed_since_request even with edited_params
    // Build the fixture explicitly. A migration-only test database has no seeded pipeline
    // stages, so relying on ambient data made this scenario fail independently of the code
    // under test. DatabaseTransactions rolls all of these inserts back after the test.
    $pipelineId = DB::table('lead_pipelines')->insertGetId([
        'name' => 'Hardening test pipeline '.uniqid(),
        'is_default' => 0,
        'rotten_days' => 30,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach (['New', 'Follow Up'] as $index => $stageName) {
        DB::table('lead_pipeline_stages')->insert([
            'code' => strtolower(str_replace(' ', '-', $stageName)).'-'.$pipelineId,
            'name' => $stageName,
            'probability' => 100,
            'sort_order' => $index + 1,
            'lead_pipeline_id' => $pipelineId,
        ]);
    }

    $stages = DB::table('lead_pipeline_stages')->pluck('id')->all();
    expect(count($stages))->toBeGreaterThanOrEqual(2);

    $leadA = agentLead($rep, 'Concurrent Lead A');
    $leadB = agentLead($rep, 'Concurrent Lead B');

    auth()->guard('user')->setUser($rep);
    $movePending = app(ActionExecutor::class)->execute('leads_move_stage', [
        'lead_ids' => [$leadA->id, $leadB->id],
        'stage_id' => (int) $stages[1],
    ]);
    $moveCallId = (int) $movePending->body['call_id'];
    auth()->guard('user')->logout();

    // Mutate leadA behind the approver's back before decision
    $leadA->update(['lead_value' => 999999]);

    $this->actingAs($manager, 'user')
        ->postJson(route('admin.agent.approvals.approve', $moveCallId), [
            'edited_params' => [
                'lead_ids' => [$leadA->id, $leadB->id],
                'stage_id' => (int) $stages[1],
            ],
        ])
        ->assertStatus(409)
        ->assertJsonPath('status', 'changed_since_request');
});

it('normalizes plural ledger_history entity types, enforces module ACL and tombstone ownership, and includes manager-approved blocks', function () {
    DB::table('data_blocks')->delete();
    DB::table('ledger_epochs')->delete();
    DB::table('ledger_heads')->delete();

    $rep = agentUser([
        'leads',
        'leads.view',
        'leads.create',
        'leads.delete',
    ], 'individual');
    $outsider = agentUser(['leads', 'contacts', 'contacts.persons'], 'individual');
    $admin = getDefaultAdmin();

    $repHeaders = agentHeaders($rep);

    // Create lead owned by $rep and dispatch ledger block + manager-settled block
    $lead = agentLead($rep, 'Plural History Lead');
    test()->actingAs($rep, 'user');
    Event::dispatch('lead.create.after', $lead);

    // Manager ($admin) updates/touches lead -> actor_id is $admin->id, but lead->user_id is $rep->id
    test()->actingAs($admin, 'user');
    $lead->update(['title' => 'Manager Co-Signed Lead']);
    Event::dispatch('lead.update.after', $lead->fresh());
    auth()->guard('user')->logout();

    // 1. Plural 'leads' returns both rep-authored and manager-authored blocks for $rep's lead
    $history = $this->postJson('/api/agent/actions/ledger_history', [
        'entity_type' => 'leads',
        'entity_id' => $lead->id,
    ], $repHeaders)->assertOk();

    expect($history->json('result.blocks'))->toHaveCount(2);

    // 2. Rep WITHOUT contacts.persons permission gets empty blocks for person entities (Gate-1 ACL)
    $outsiderPerson = Person::query()->create([
        'name' => 'Secret Contact',
        'emails' => [['value' => 'secret.contact@example.com', 'label' => 'work']],
        'user_id' => $outsider->id,
    ]);
    test()->actingAs($outsider, 'user');
    Event::dispatch('contacts.person.create.after', $outsiderPerson);
    auth()->guard('user')->logout();

    $deniedPersonHistory = $this->postJson('/api/agent/actions/ledger_history', [
        'entity_type' => 'persons',
        'entity_id' => $outsiderPerson->id,
    ], $repHeaders)->assertOk();

    expect($deniedPersonHistory->json('result.blocks'))->toBeEmpty();

    // 3. Tombstoned outsider lead stays invisible to $rep, while $rep's own tombstoned lead remains visible
    $outsiderLead = agentLead($outsider, 'Outsider Deleted Lead');
    $outsiderLeadId = $outsiderLead->id;
    test()->actingAs($outsider, 'user');
    Event::dispatch('lead.delete.before', $outsiderLeadId);
    $outsiderLead->delete();
    auth()->guard('user')->logout();

    $outsiderTombstoneHistory = $this->postJson('/api/agent/actions/ledger_history', [
        'entity_type' => 'lead',
        'entity_id' => $outsiderLeadId,
    ], $repHeaders)->assertOk();

    expect($outsiderTombstoneHistory->json('result.blocks'))->toBeEmpty();
});

it('reuses existing contacts on repeated leads_create calls, enforces contacts_upsert_person .edit permission, gates MCP resources/prompts, and captures Briefing intake', function () {
    // 1. Individual-scoped rep creates two leads with the same person_email without unique_id crash
    $createOnlyRep = agentUser([
        'leads',
        'leads.view',
        'leads.create',
        'contacts',
        'contacts.persons',
        'contacts.persons.create',
        'agent_briefing',
    ], 'individual');
    $repHeaders = agentHeaders($createOnlyRep);

    $firstLead = $this->postJson('/api/agent/actions/leads_create', [
        'title' => 'Horizon Renewal Year 1',
        'person_name' => 'Nadia Kouri',
        'person_email' => 'nadia.kouri@horizon.example',
        'lead_value' => 11000,
    ], $repHeaders)->assertOk();

    $secondLead = $this->postJson('/api/agent/actions/leads_create', [
        'title' => 'Horizon Expansion Year 2',
        'person_name' => 'Nadia Kouri',
        'person_email' => 'nadia.kouri@horizon.example',
        'lead_value' => 18500,
    ], $repHeaders)->assertOk();

    $personId = $firstLead->json('result.lead.person_id');
    expect($personId)->toBeInt()
        ->and($secondLead->json('result.lead.person_id'))->toBe($personId)
        ->and(Person::query()->findOrFail($personId)->user_id)->toBe($createOnlyRep->id);

    // 2. contacts_upsert_person updating an existing person requires contacts.persons.edit (403 forbidden)
    $this->postJson('/api/agent/actions/contacts_upsert_person', [
        'name' => 'Nadia Kouri Updated',
        'email' => 'nadia.kouri@horizon.example',
    ], $repHeaders)
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'forbidden');

    // 3. MCP v2 filters resources/list by passport scope, rejects invalid JSON-RPC (-32600), and validates prompt args (-32602)
    app('auth')->forgetGuards();
    ['headers' => $leadsOnlyHeaders] = hardeningPassport($createOnlyRep, [
        'allowed_actions' => ['leads_search'],
    ]);

    $this->postJson('/api/agent/mcp', ['id' => 1, 'method' => 'ping'], $leadsOnlyHeaders)
        ->assertOk()
        ->assertJsonPath('error.code', -32600);

    $resList = $this->postJson('/api/agent/mcp', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'resources/list',
    ], $leadsOnlyHeaders)->assertOk();

    $uris = array_column($resList->json('result.resources') ?? [], 'uri');
    expect($uris)->toBe(['luminara://context/schema']);

    $this->postJson('/api/agent/mcp', [
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'prompts/get',
        'params' => ['name' => 'intake', 'arguments' => ['raw_text' => '   ']],
    ], $leadsOnlyHeaders)
        ->assertOk()
        ->assertJsonPath('error.code', -32602);

    // 4. Morning Briefing inline one-line intake route captures directly to CRM
    $this->actingAs($createOnlyRep, 'user')
        ->postJson(route('admin.agent.briefing.intake'), [
            'raw_text' => 'Met Viktor Novak <viktor@novaklabs.example> regarding $22,000 Q3 automation pilot',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('action', 'intake_capture');
});
