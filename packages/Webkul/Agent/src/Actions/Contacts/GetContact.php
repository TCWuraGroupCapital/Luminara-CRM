<?php

namespace Webkul\Agent\Actions\Contacts;

use Webkul\Agent\Support\CustomerFacts;
use Webkul\Lead\Models\Lead;

class GetContact extends ContactAction
{
    public function name(): string
    {
        return 'contacts_get';
    }

    public function summary(): string
    {
        return 'Read one contact (person) inside the caller\'s scope, including linked leads.';
    }

    public function permission(): string
    {
        return 'contacts.persons.view';
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return [
            'person_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function handle(array $params): array
    {
        $person = $this->scopedPersons()
            ->with('organization')
            ->whereKey($params['person_id'])
            ->firstOrFail();

        $leads = bouncer()
            ->scopeToAuthorizedUsers($person->leads()->getQuery())
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (Lead $lead): array => [
                'id' => $lead->getKey(),
                'title' => $lead->getAttribute('title'),
                'lead_value' => $lead->getAttribute('lead_value') !== null ? (float) $lead->getAttribute('lead_value') : null,
            ])
            ->all();

        $personData = $this->presentPerson($person) + ['leads' => $leads];

        if (config('agent.memory.enabled')) {
            $facts = app(CustomerFacts::class);
            $personData['customer_facts'] = $facts
                ->recall(CustomerFacts::ENTITY_PERSON, (int) $person->getKey())
                ->map(fn ($fact): array => $facts->present($fact))
                ->all();
        }

        return ['person' => $personData];
    }
}
