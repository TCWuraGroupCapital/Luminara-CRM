<?php

use Anthropic\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Agent\Models\AgentActionCall;
use Webkul\Agent\Models\AgentCustomerFact;
use Webkul\Agent\Support\Assistant;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

uses(DatabaseTransactions::class);

/**
 * Answers the assistant's model requests with the given Messages API bodies, in order, and records
 * each request sent. Nothing leaves the machine.
 *
 * @param  array<int, array<string, mixed>>  $replies
 * @return ArrayObject<int, array<string, mixed>> The decoded request bodies, filled as they are sent.
 */
function fakeModel(array $replies): ArrayObject
{
    config(['agent.assistant.api_key' => 'test-key']);

    $requests = new ArrayObject;

    $stack = HandlerStack::create(new MockHandler(array_map(
        fn (array $reply) => new Response(200, ['Content-Type' => 'application/json'], json_encode($reply + [
            'id' => uniqid('msg_'),
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5-5',
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ])),
        $replies
    )));

    $stack->push(Middleware::mapRequest(function ($request) use ($requests) {
        $requests[] = json_decode((string) $request->getBody(), true);

        return $request;
    }));

    app()->when(Assistant::class)->needs(Client::class)->give(fn () => new Client(
        apiKey: 'test-key',
        requestOptions: ['transporter' => new GuzzleClient(['handler' => $stack]), 'maxRetries' => 0],
    ));

    return $requests;
}

function toolUse(string $name, array $input): array
{
    return [
        'stop_reason' => 'tool_use',
        'content' => [['type' => 'tool_use', 'id' => uniqid('toolu_'), 'name' => $name, 'input' => $input]],
    ];
}

function finalText(string $text): array
{
    return ['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => $text]]];
}

it('shows the assistant page and says when no model is configured', function () {
    config(['agent.assistant.api_key' => null]);

    $this->actingAs(agentUser(), 'user')
        ->get(route('admin.agent.assistant.index'))
        ->assertOk()
        ->assertSee('v-agent-assistant', false)
        ->assertSee(trans('agent::app.assistant.errors.not-configured'), false);
});

it('tells the user they are talking to an AI model, and which one, before they write', function () {
    config(['agent.assistant.model' => 'claude-opus-5-5']);

    $this->actingAs(agentUser(), 'user')
        ->get(route('admin.agent.assistant.index'))
        ->assertOk()
        ->assertSee(trans('agent::app.assistant.disclosure', ['model' => 'claude-opus-5-5']), false);
});

it('runs a tool as the signed-in user and answers with what happened', function () {
    $user = agentUser();

    $requests = fakeModel([
        toolUse('leads_create', ['title' => 'Fleet renewal', 'person_name' => 'Dana Okafor', 'person_email' => 'dana@example.com']),
        finalText('Created the lead Fleet renewal.'),
    ]);

    $this->actingAs($user, 'user')
        ->postJson(route('admin.agent.assistant.send'), ['message' => 'Create a lead for Dana Okafor, fleet renewal'])
        ->assertOk()
        ->assertJsonPath('reply', 'Created the lead Fleet renewal.')
        ->assertJsonPath('calls.0.action', 'leads_create')
        ->assertJsonPath('calls.0.status', 'completed')
        ->assertJsonPath('approvals_url', null);

    $call = AgentActionCall::query()->where('user_id', $user->id)->sole();

    /**
     * The tool result the model reads back is fenced as data, like over MCP.
     */
    $toolResult = collect($requests[1]['messages'])->last()['content'][0];

    expect(Lead::query()->where('title', 'Fleet renewal')->where('user_id', $user->id)->count())->toBe(1)
        ->and($call->token_id)->toBeNull()
        ->and($toolResult['type'])->toBe('tool_result')
        ->and($toolResult['content'])->toContain('<untrusted_crm_data>');
});

it('leaves a destructive change waiting for a person and links to the approvals', function () {
    $user = agentUser();
    $lead = agentLead($user);

    fakeModel([
        toolUse('leads_delete', ['lead_id' => $lead->id]),
        finalText('A person must approve the deletion under Agent Approvals.'),
    ]);

    $this->actingAs($user, 'user')
        ->postJson(route('admin.agent.assistant.send'), ['message' => 'Delete that lead'])
        ->assertOk()
        ->assertJsonPath('calls.0.status', 'pending_approval')
        ->assertJsonPath('approvals_url', route('admin.agent.approvals.index'));

    expect(Lead::query()->whereKey($lead->id)->exists())->toBeTrue();
});

it('offers the model only the actions the user may call', function () {
    $requests = fakeModel([finalText('I can search and read leads.')]);

    $this->actingAs(agentUser(['agent_assistant', 'leads']), 'user')
        ->postJson(route('admin.agent.assistant.send'), ['message' => 'What can you do?'])
        ->assertOk();

    expect(array_column($requests[0]['tools'], 'name'))
        ->toContain('leads_search')
        ->not->toContain('leads_delete')
        ->not->toContain('leads_create');
});

it('keeps the conversation for the next message and clears it on a new one', function () {
    $user = agentUser();

    $requests = fakeModel([finalText('Hello.'), finalText('Still here.'), finalText('Fresh start.')]);

    $this->actingAs($user, 'user');

    $this->postJson(route('admin.agent.assistant.send'), ['message' => 'Hi'])->assertOk();
    $this->postJson(route('admin.agent.assistant.send'), ['message' => 'Again'])->assertOk();
    $this->postJson(route('admin.agent.assistant.reset'))->assertOk();
    $this->postJson(route('admin.agent.assistant.send'), ['message' => 'New'])->assertOk();

    expect(count($requests[1]['messages']))->toBe(3)
        ->and(count($requests[2]['messages']))->toBe(1);
});

it('tells the user when no model is configured', function () {
    config(['agent.assistant.api_key' => null]);

    $this->actingAs(agentUser(), 'user')
        ->postJson(route('admin.agent.assistant.send'), ['message' => 'Hi'])
        ->assertStatus(503)
        ->assertJsonPath('message', trans('agent::app.assistant.errors.not-configured'));
});

it('refuses a user whose role lacks the assistant permission', function () {
    $this->actingAs(agentUser(['leads']), 'user')
        ->postJson(route('admin.agent.assistant.send'), ['message' => 'Hi'])
        ->assertStatus(401);
});

it('sends no refusal fallback to a service other than Anthropic', function () {
    config(['agent.assistant.base_url' => 'https://models.example.invalid']);

    $requests = fakeModel([finalText('Hi.')]);

    $this->actingAs(agentUser(), 'user')
        ->postJson(route('admin.agent.assistant.send'), ['message' => 'Hi'])
        ->assertOk();

    expect($requests[0])->not->toHaveKey('fallbacks')
        ->and($requests[0]['model'])->toBe('claude-opus-5-5');
});

it('puts approved customer facts in front of the model as labelled data, never as instructions', function () {
    config(['agent.memory.enabled' => true]);

    $user = agentUser();
    $person = Person::query()->create([
        'name' => 'Dana Whitfield',
        'emails' => [['value' => uniqid('facts-assistant-').'@example.com', 'label' => 'work']],
        'user_id' => $user->id,
    ]);

    AgentCustomerFact::query()->create([
        'entity_type' => 'person',
        'entity_id' => $person->getKey(),
        'category' => 'preference',
        'fact' => 'Prefers contact before 9 am',
    ]);

    $requests = fakeModel([
        toolUse('contacts_get', ['person_id' => $person->getKey()]),
        finalText('Dana prefers contact before 9 am.'),
    ]);

    $this->actingAs($user, 'user')
        ->postJson(route('admin.agent.assistant.send'), ['message' => 'What do we know about Dana?'])
        ->assertOk();

    $secondRequest = $requests[1];
    $lastMessage = collect($secondRequest['messages'])->last();

    expect(json_encode($lastMessage['content']))->toContain('<approved_customer_facts>')
        ->toContain('Prefers contact before 9 am')
        ->and($secondRequest['system'])->not->toContain('Prefers contact before 9 am');
});
