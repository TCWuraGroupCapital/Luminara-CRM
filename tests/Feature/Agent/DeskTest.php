<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Agent\Models\AgentActionCall;
use Webkul\Agent\Support\DeskData;
use Webkul\Lead\Models\Lead;

uses(DatabaseTransactions::class);

/**
 * A delete the agent asked for through its token, waiting for a person.
 */
function deskPendingDelete(Lead $lead, string $reason = 'Duplicate of the original.'): int
{
    return test()->postJson('/api/agent/actions/leads_delete', ['lead_id' => $lead->id, 'reason' => $reason], agentHeaders($lead->user))
        ->assertStatus(202)
        ->json('call_id');
}

it('shows the desk with the pipeline, the waiting calls and the assistant disclosure', function () {
    $user = agentUser();
    $lead = agentLead($user, 'Desk duplicate lead');
    deskPendingDelete($lead);
    config(['agent.assistant.model' => 'claude-opus-5-5']);

    $this->actingAs($user, 'user')
        ->get(route('admin.agent.desk.index'))
        ->assertOk()
        ->assertSee('v-agent-desk', false)
        ->assertSee(trans('agent::app.desk.model-title'))
        ->assertSee(trans('agent::app.assistant.disclosure', ['model' => 'claude-opus-5-5']), false)
        ->assertSee('Desk duplicate lead');
});

it('reports each stage with totals, its largest deals, and every deal a waiting call touches', function () {
    $user = agentUser();
    $lead = agentLead($user, 'Small but pinned');
    $lead->update(['lead_value' => 1]);
    $callId = deskPendingDelete($lead);

    // Push the pinned lead out of the stage's top deals by value.
    foreach (range(1, DeskData::DEALS_PER_STAGE + 1) as $i) {
        agentLead($user, "Bigger deal {$i}")->update(['lead_value' => 50000 + $i]);
    }

    $data = $this->actingAs($user, 'user')->getJson(route('admin.agent.desk.data'))->assertOk()->json();

    $stage = collect($data['stages'])->firstWhere('id', $lead->lead_pipeline_stage_id);
    $dealIds = array_column($stage['deals'], 'id');

    expect($stage['count'])->toBeGreaterThanOrEqual(DeskData::DEALS_PER_STAGE + 2)
        ->and($dealIds)->toContain($lead->id)
        ->and(count($dealIds))->toBe(DeskData::DEALS_PER_STAGE + 1)
        ->and(collect($data['proposals'])->firstWhere('id', $callId))
        ->toMatchArray(['type' => 'remove', 'deal_ids' => [$lead->id], 'reason' => 'Duplicate of the original.'])
        ->and($data['can'])->toBe(['approve' => true, 'move' => true, 'view' => true]);
});

it('lists what the agent did for this user today, and nobody else\'s calls', function () {
    $user = agentUser();
    $other = agentUser();
    $lead = agentLead($user, 'Read me');
    $this->postJson('/api/agent/actions/leads_get', ['lead_id' => $lead->id], agentHeaders($user))->assertOk();
    app('auth')->forgetGuards(); // a test keeps the last authenticated user between requests; real requests don't
    $this->postJson('/api/agent/actions/leads_search', ['query' => 'nothing'], agentHeaders($other))->assertOk();

    $activity = $this->actingAs($user, 'user')->getJson(route('admin.agent.desk.data'))->json('activity');

    expect($activity)->toHaveCount(1)
        ->and($activity[0])->toMatchArray(['action' => 'leads_get', 'status' => 'completed', 'lead_id' => $lead->id]);
});

it('hides waiting calls from a person who cannot approve them', function () {
    $owner = agentUser();
    deskPendingDelete(agentLead($owner));
    $viewer = agentUser(['agent_desk', 'leads', 'leads.view']);

    $data = $this->actingAs($viewer, 'user')->getJson(route('admin.agent.desk.data'))->assertOk()->json();

    expect($data['proposals'])->toBe([])
        ->and($data['can'])->toBe(['approve' => false, 'move' => false, 'view' => true]);
});

it('refuses the desk to a role without the desk permission', function () {
    $this->actingAs(agentUser(['leads', 'leads.view']), 'user')
        ->get(route('admin.agent.desk.index'))
        ->assertStatus(401);
});

it('answers a decision made from the desk as JSON, with the outcome in plain words', function () {
    $user = agentUser();
    $approved = agentLead($user, 'Approve me');
    $rejected = agentLead($user, 'Reject me');
    $first = deskPendingDelete($approved);
    $second = deskPendingDelete($rejected);

    $this->actingAs($user, 'user')
        ->postJson(route('admin.agent.approvals.approve', $first))
        ->assertOk()
        ->assertJson(['status' => 'completed', 'message' => trans('agent::app.approvals.outcome.completed')]);

    $this->actingAs($user, 'user')
        ->postJson(route('admin.agent.approvals.reject', $second))
        ->assertOk()
        ->assertJson(['status' => 'rejected']);

    $this->actingAs($user, 'user')
        ->postJson(route('admin.agent.approvals.approve', $second))
        ->assertStatus(409)
        ->assertJson(['status' => 'already_decided']);

    expect(Lead::query()->whereKey($approved->id)->exists())->toBeFalse()
        ->and(Lead::query()->whereKey($rejected->id)->exists())->toBeTrue()
        ->and(AgentActionCall::query()->find($second)->status)->toBe(AgentActionCall::REJECTED);
});

it('still redirects a decision made from the approvals page', function () {
    $user = agentUser();
    $call = deskPendingDelete(agentLead($user));

    $this->actingAs($user, 'user')
        ->post(route('admin.agent.approvals.reject', $call))
        ->assertRedirect(route('admin.agent.approvals.index'));
});
