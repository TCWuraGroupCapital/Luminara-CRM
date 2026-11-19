<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Agent\Models\AgentCustomerFact;
use Webkul\Agent\Support\CustomerFacts;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

function factsPerson(User $owner, string $name = 'Dana Whitfield'): Person
{
    return Person::query()->create([
        'name' => $name,
        'emails' => [['value' => uniqid('facts-').'@example.com', 'label' => 'work']],
        'user_id' => $owner->id,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function storedFact(Person|Organization $entity, string $fact, array $attributes = []): AgentCustomerFact
{
    return AgentCustomerFact::query()->create(array_merge([
        'entity_type' => $entity instanceof Person ? CustomerFacts::ENTITY_PERSON : CustomerFacts::ENTITY_ORGANIZATION,
        'entity_id' => $entity->getKey(),
        'category' => 'preference',
        'fact' => $fact,
    ], $attributes));
}

/**
 * @return array<int, string>
 */
function recalledFacts(Person|Organization $entity, ?string $query = null): array
{
    $type = $entity instanceof Person ? CustomerFacts::ENTITY_PERSON : CustomerFacts::ENTITY_ORGANIZATION;

    return app(CustomerFacts::class)->recall($type, (int) $entity->getKey(), $query)->pluck('fact')->all();
}

it('recalls a contact\'s active team facts, newest first', function () {
    $user = agentUser();
    $person = factsPerson($user);
    storedFact($person, 'Prefers invoices on the 1st of the month');
    storedFact($person, 'Accounts payable contact is Sam');
    $this->actingAs($user, 'user');

    expect(recalledFacts($person))->toBe([
        'Accounts payable contact is Sam',
        'Prefers invoices on the 1st of the month',
    ]);
});

it('refuses to recall facts for a contact outside the caller\'s data scope', function () {
    $owner = agentUser(['contacts', 'contacts.persons', 'contacts.persons.view'], 'individual');
    $outsider = agentUser(['contacts', 'contacts.persons', 'contacts.persons.view'], 'individual');
    $person = factsPerson($owner);
    storedFact($person, 'Budget holder is the CFO');
    $this->actingAs($outsider, 'user');

    expect(fn () => recalledFacts($person))->toThrow(ModelNotFoundException::class);
});

it('shows a private fact only to the user who owns it', function () {
    $owner = agentUser();
    $colleague = agentUser();
    $person = factsPerson($owner);
    storedFact($person, 'Shared with the whole team');
    storedFact($person, 'Owner\'s private note', [
        'visibility' => AgentCustomerFact::VISIBILITY_PRIVATE,
        'owner_user_id' => $owner->id,
    ]);

    $this->actingAs($colleague, 'user');
    expect(recalledFacts($person))->toBe(['Shared with the whole team']);

    $this->actingAs($owner, 'user');
    expect(recalledFacts($person))->toBe(['Owner\'s private note', 'Shared with the whole team']);
});

it('leaves out expired, retracted and superseded facts', function () {
    $user = agentUser();
    $person = factsPerson($user);
    storedFact($person, 'Still valid today', ['valid_until' => now()->toDateString()]);
    storedFact($person, 'Expired yesterday', ['valid_until' => now()->subDay()->toDateString()]);
    storedFact($person, 'Was retracted', ['status' => AgentCustomerFact::STATUS_RETRACTED]);
    storedFact($person, 'Was superseded', ['status' => AgentCustomerFact::STATUS_SUPERSEDED]);
    $this->actingAs($user, 'user');

    expect(recalledFacts($person))->toBe(['Still valid today']);
});

it('narrows recall to facts containing every search word', function () {
    $user = agentUser();
    $person = factsPerson($user);
    storedFact($person, 'Wants delivery on Friday mornings');
    storedFact($person, 'Friday is their stocktake day');
    storedFact($person, 'Pays by bank transfer');
    $this->actingAs($user, 'user');

    expect(recalledFacts($person, 'friday delivery'))->toBe(['Wants delivery on Friday mornings'])
        ->and(recalledFacts($person, 'friday'))->toHaveCount(2)
        ->and(recalledFacts($person, '   '))->toHaveCount(3);
});

it('treats wildcard characters in a search as plain text', function () {
    $user = agentUser();
    $person = factsPerson($user);
    storedFact($person, 'Expects a 10% loyalty discount');
    storedFact($person, 'Orders 1000 units each quarter');
    $this->actingAs($user, 'user');

    expect(recalledFacts($person, '10%'))->toBe(['Expects a 10% loyalty discount'])
        ->and(recalledFacts($person, '1_00'))->toBe([]);
});

it('keeps person and organisation facts apart', function () {
    $user = agentUser();
    $person = factsPerson($user);
    $organization = Organization::query()->create(['name' => 'Whitfield Foods '.uniqid(), 'user_id' => $user->id]);
    storedFact($person, 'Person fact');
    storedFact($organization, 'Organisation fact');
    $this->actingAs($user, 'user');

    expect(recalledFacts($person))->toBe(['Person fact'])
        ->and(recalledFacts($organization))->toBe(['Organisation fact']);
});

it('returns at most the recall limit', function () {
    $user = agentUser();
    $person = factsPerson($user);

    foreach (range(1, CustomerFacts::RECALL_LIMIT + 1) as $number) {
        storedFact($person, "Fact number {$number}");
    }

    $this->actingAs($user, 'user');

    expect(recalledFacts($person))->toHaveCount(CustomerFacts::RECALL_LIMIT)
        ->not->toContain('Fact number 1');
});

it('rejects an unknown entity type', function () {
    $this->actingAs(agentUser(), 'user');

    expect(fn () => app(CustomerFacts::class)->recall('lead', 1))->toThrow(InvalidArgumentException::class);
});

it('presents a fact as flat data for an agent', function () {
    $user = agentUser();
    $person = factsPerson($user);
    $fact = storedFact($person, 'On leave until March', [
        'category' => 'context',
        'valid_until' => '2027-03-01',
        'approved_by_user_id' => $user->id,
    ])->refresh();

    expect(app(CustomerFacts::class)->present($fact))->toMatchArray([
        'id' => $fact->id,
        'entity_type' => 'person',
        'entity_id' => $person->id,
        'category' => 'context',
        'fact' => 'On leave until March',
        'visibility' => 'team',
        'valid_until' => '2027-03-01',
        'approved_by_user_id' => $user->id,
    ]);
});
