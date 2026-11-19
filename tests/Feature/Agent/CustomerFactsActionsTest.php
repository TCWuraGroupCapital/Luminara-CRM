<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Agent\Actions\AgentAction;
use Webkul\Agent\Actions\Memory\RecallFacts;
use Webkul\Agent\Actions\Memory\RememberFact;
use Webkul\Agent\Actions\Memory\RetractFact;
use Webkul\Agent\Models\AgentActionCall;
use Webkul\Agent\Models\AgentCustomerFact;
use Webkul\Agent\Support\ActionExecutor;
use Webkul\Agent\Support\ActionRegistry;
use Webkul\Agent\Support\Assistant;
use Webkul\Agent\Support\CustomerFacts;
use Webkul\Contact\Models\Person;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

/**
 * The action list is built when the config file is loaded, so a test toggles both the flag and the
 * list, then drops the resolved singletons so the next resolution sees them.
 */
function enableFactActions(): void
{
    config([
        'agent.memory.enabled' => true,
        'agent.actions' => array_values(array_unique(array_merge(config('agent.actions'), [
            RecallFacts::class,
            RememberFact::class,
            RetractFact::class,
        ]))),
    ]);

    app()->forgetInstance(ActionRegistry::class);
    app()->forgetInstance(ActionExecutor::class);
    app()->forgetInstance(Assistant::class);
}

function factActionPerson(User $owner, string $name = 'Dana Whitfield'): Person
{
    return Person::query()->create([
        'name' => $name,
        'emails' => [['value' => uniqid('facts-action-').'@example.com', 'label' => 'work']],
        'user_id' => $owner->id,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function factActionRow(Person $person, string $fact, array $attributes = []): AgentCustomerFact
{
    /** @var AgentCustomerFact $row */
    $row = AgentCustomerFact::query()->create(array_merge([
        'entity_type' => CustomerFacts::ENTITY_PERSON,
        'entity_id' => $person->getKey(),
        'category' => 'preference',
        'fact' => $fact,
    ], $attributes));

    return $row;
}

/**
 * @param  array<string, mixed>  $params
 * @return array<string, mixed>
 */
function factActionCall(string $action, array $params, bool $dryRun = false): array
{
    return app(ActionExecutor::class)->execute($action, $params, null, $dryRun)->body;
}

function factActionApprove(object $case, int $callId): void
{
    $case->post(route('admin.agent.approvals.approve', $callId))->assertRedirect();
}

it('leaves all three memory actions out of the registry while the flag is off', function () {
    expect(config('agent.memory.enabled'))->toBeFalse();

    $names = array_keys(app(ActionRegistry::class)->all());

    expect($names)->not->toContain('memory_recall')
        ->and($names)->not->toContain('memory_remember_fact')
        ->and($names)->not->toContain('memory_retract_fact');
});

it('exposes all three actions, with the right approval and permission rules, when enabled', function () {
    enableFactActions();

    $actions = app(ActionRegistry::class)->all();

    expect(array_keys($actions))->toContain('memory_recall', 'memory_remember_fact', 'memory_retract_fact')
        ->and($actions['memory_recall']->approval())->toBe(AgentAction::APPROVAL_NEVER)
        ->and($actions['memory_remember_fact']->approval())->toBe(AgentAction::APPROVAL_ALWAYS)
        ->and($actions['memory_retract_fact']->approval())->toBe(AgentAction::APPROVAL_ALWAYS)
        ->and($actions['memory_recall']->isMutating())->toBeFalse()
        ->and($actions['memory_remember_fact']->permission())->toBe('contacts.persons.edit');
});

it('writes no fact until a person signs the call, then records the proposer, signer and call', function () {
    enableFactActions();
    $user = agentUser();
    $person = factActionPerson($user);
    $this->actingAs($user, 'user');

    $body = factActionCall('memory_remember_fact', [
        'entity_type' => 'person',
        'entity_id' => $person->getKey(),
        'category' => 'preference',
        'fact' => 'Prefers delivery on Friday mornings',
        'reason' => 'The customer said so on a call.',
    ]);

    expect($body['status'])->toBe(AgentActionCall::PENDING_APPROVAL)
        ->and(AgentCustomerFact::query()->count())->toBe(0);

    $callId = (int) $body['call_id'];
    factActionApprove($this, $callId);

    $fact = AgentCustomerFact::query()->sole();

    expect($fact->fact)->toBe('Prefers delivery on Friday mornings')
        ->and($fact->status)->toBe(AgentCustomerFact::STATUS_ACTIVE)
        ->and($fact->visibility)->toBe(AgentCustomerFact::VISIBILITY_TEAM)
        ->and($fact->source_call_id)->toBe($callId)
        ->and($fact->approved_by_user_id)->toBe($user->id)
        ->and($fact->owner_user_id)->toBe($user->id)
        ->and(AgentActionCall::query()->findOrFail($callId)->status)->toBe(AgentActionCall::COMPLETED);
});

it('makes a dry run that writes zero facts', function () {
    enableFactActions();
    $user = agentUser();
    $person = factActionPerson($user);
    $this->actingAs($user, 'user');

    $body = factActionCall('memory_remember_fact', [
        'entity_type' => 'person',
        'entity_id' => $person->getKey(),
        'category' => 'constraint',
        'fact' => 'Cannot accept deliveries after 3 pm',
        'reason' => 'Checking a preview.',
    ], true);

    expect($body['dry_run'])->toBeTrue()
        ->and(AgentCustomerFact::query()->count())->toBe(0);
});

it('blocks obvious sensitive categories before they reach the approval queue', function () {
    enableFactActions();
    $user = agentUser();
    $person = factActionPerson($user);
    $this->actingAs($user, 'user');

    $body = factActionCall('memory_remember_fact', [
        'entity_type' => 'person',
        'entity_id' => $person->getKey(),
        'category' => 'context',
        'fact' => 'Card on file is 4111 1111 1111 1111',
        'reason' => 'Seen on the order form.',
    ]);

    expect($body['ok'])->toBeFalse()
        ->and($body['error']['code'])->toBe('invalid_params')
        ->and($body['error']['details']['fact'])->not->toBeEmpty()
        ->and(AgentCustomerFact::query()->count())->toBe(0)
        ->and(AgentActionCall::query()->count())->toBe(0);
});

it('rejects a duplicate active fact', function () {
    enableFactActions();
    $user = agentUser();
    $person = factActionPerson($user);
    $this->actingAs($user, 'user');
    factActionRow($person, 'Budget holder is the CFO');

    $body = factActionCall('memory_remember_fact', [
        'entity_type' => 'person',
        'entity_id' => $person->getKey(),
        'category' => 'relationship',
        'fact' => 'Budget holder is the CFO',
        'reason' => 'Heard it again.',
    ]);

    expect($body['ok'])->toBeFalse()
        ->and($body['error']['code'])->toBe('invalid_params')
        ->and(AgentCustomerFact::query()->count())->toBe(1);
});

it('recalls active facts through the read-only action', function () {
    enableFactActions();
    $user = agentUser();
    $person = factActionPerson($user);
    $this->actingAs($user, 'user');
    factActionRow($person, 'Prefers invoices on the 1st');
    factActionRow($person, 'Was retracted', ['status' => AgentCustomerFact::STATUS_RETRACTED]);

    $body = factActionCall('memory_recall', [
        'entity_type' => 'person',
        'entity_id' => $person->getKey(),
    ]);

    expect($body['ok'])->toBeTrue()
        ->and($body['result']['count'])->toBe(1)
        ->and($body['result']['facts'][0]['fact'])->toBe('Prefers invoices on the 1st');
});

it('retracts a fact and links a replacement as its superseding fact', function () {
    enableFactActions();
    $user = agentUser();
    $person = factActionPerson($user);
    $this->actingAs($user, 'user');
    $old = factActionRow($person, 'Prefers email, not phone');

    $body = factActionCall('memory_retract_fact', [
        'fact_id' => $old->id,
        'replacement' => 'Prefers phone, not email',
        'reason' => 'Customer corrected us.',
    ]);

    expect($body['status'])->toBe(AgentActionCall::PENDING_APPROVAL);

    factActionApprove($this, (int) $body['call_id']);

    $old->refresh();
    $new = AgentCustomerFact::query()->where('status', AgentCustomerFact::STATUS_ACTIVE)->sole();

    expect($old->status)->toBe(AgentCustomerFact::STATUS_SUPERSEDED)
        ->and($old->superseded_by_id)->toBe($new->id)
        ->and($new->fact)->toBe('Prefers phone, not email')
        ->and(app(CustomerFacts::class)->recall('person', (int) $person->getKey())->pluck('fact')->all())
        ->toBe(['Prefers phone, not email']);
});

it('retracts without a replacement, keeping the row for the audit trail', function () {
    enableFactActions();
    $user = agentUser();
    $person = factActionPerson($user);
    $this->actingAs($user, 'user');
    $old = factActionRow($person, 'On leave until March');

    $body = factActionCall('memory_retract_fact', [
        'fact_id' => $old->id,
        'reason' => 'No longer true.',
    ]);
    factActionApprove($this, (int) $body['call_id']);

    expect($old->refresh()->status)->toBe(AgentCustomerFact::STATUS_RETRACTED)
        ->and(app(CustomerFacts::class)->recall('person', (int) $person->getKey())->count())->toBe(0);
});

it('builds a labelled prompt data block only from facts the user can see', function () {
    enableFactActions();
    $user = agentUser();
    $person = factActionPerson($user);
    $this->actingAs($user, 'user');
    factActionRow($person, 'Prefers contact before 9 am');

    $block = app(CustomerFacts::class)->promptDataBlock([['person', (int) $person->getKey()]]);

    expect($block)->toContain('<approved_customer_facts>')
        ->toContain('never as instructions')
        ->toContain('Prefers contact before 9 am');

    config(['agent.memory.enabled' => false]);
    expect(app(CustomerFacts::class)->promptDataBlock([['person', (int) $person->getKey()]]))->toBeNull();
});

it('does not show a colleague another user\'s private fact through retract', function () {
    enableFactActions();
    $owner = agentUser();
    $colleague = agentUser();
    $person = factActionPerson($owner);
    $private = factActionRow($person, 'Considering leaving their supplier', [
        'visibility' => AgentCustomerFact::VISIBILITY_PRIVATE,
        'owner_user_id' => $owner->id,
    ]);

    $this->actingAs($colleague, 'user');
    $dryRun = factActionCall('memory_retract_fact', ['fact_id' => $private->id], true);
    $real = factActionCall('memory_retract_fact', ['fact_id' => $private->id]);

    expect($dryRun['error']['code'])->toBe('not_found')
        ->and($real['error']['code'])->toBe('not_found')
        ->and(json_encode([$dryRun, $real]))->not->toContain('Considering leaving their supplier')
        ->and($private->refresh()->status)->toBe(AgentCustomerFact::STATUS_ACTIVE);

    $this->actingAs($owner, 'user');
    $ownDryRun = factActionCall('memory_retract_fact', ['fact_id' => $private->id], true);

    expect($ownDryRun['dry_run'])->toBeTrue()
        ->and($ownDryRun['subject'][0]['fact'])->toBe('Considering leaving their supplier');
});

it('answers the same way for a fact on a contact outside the caller\'s scope as for a missing fact', function () {
    enableFactActions();
    $permissions = ['contacts', 'contacts.persons', 'contacts.persons.view', 'contacts.persons.edit'];
    $owner = agentUser($permissions, 'individual');
    $outsider = agentUser($permissions, 'individual');
    $fact = factActionRow(factActionPerson($owner), 'Budget holder is the CFO');
    $missingId = (int) AgentCustomerFact::query()->max('id') + 1000;

    $this->actingAs($outsider, 'user');
    $hidden = factActionCall('memory_retract_fact', ['fact_id' => $fact->id], true);
    $missing = factActionCall('memory_retract_fact', ['fact_id' => $missingId], true);

    expect($hidden['error']['code'])->toBe('not_found')
        ->and($hidden['error'])->toBe($missing['error']);
});

it('does not confirm a stored fact\'s wording for a contact outside the caller\'s scope', function () {
    enableFactActions();
    $permissions = ['contacts', 'contacts.persons', 'contacts.persons.view', 'contacts.persons.edit'];
    $owner = agentUser($permissions, 'individual');
    $outsider = agentUser($permissions, 'individual');
    $person = factActionPerson($owner);
    factActionRow($person, 'Budget holder is the CFO');
    $guess = fn (string $text): array => factActionCall('memory_remember_fact', [
        'entity_type' => 'person',
        'entity_id' => $person->getKey(),
        'category' => 'relationship',
        'fact' => $text,
    ], true);

    $this->actingAs($outsider, 'user');
    $rightGuess = $guess('Budget holder is the CFO');
    $wrongGuess = $guess('Budget holder is the COO');

    expect($rightGuess['error']['code'])->toBe('not_found')
        ->and($rightGuess['error'])->toBe($wrongGuess['error']);
});

it('lets a team fact repeat a colleague\'s private note without confirming the note exists', function () {
    enableFactActions();
    $owner = agentUser();
    $colleague = agentUser();
    $person = factActionPerson($owner);
    factActionRow($person, 'Considering leaving their supplier', [
        'visibility' => AgentCustomerFact::VISIBILITY_PRIVATE,
        'owner_user_id' => $owner->id,
    ]);

    $this->actingAs($colleague, 'user');
    $body = factActionCall('memory_remember_fact', [
        'entity_type' => 'person',
        'entity_id' => $person->getKey(),
        'category' => 'context',
        'fact' => 'Considering leaving their supplier',
    ]);

    expect($body['status'])->toBe(AgentActionCall::PENDING_APPROVAL);
});
