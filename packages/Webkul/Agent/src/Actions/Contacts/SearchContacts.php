<?php

namespace Webkul\Agent\Actions\Contacts;

use Illuminate\Database\Eloquent\Builder;
use Webkul\Contact\Models\Person;

class SearchContacts extends ContactAction
{
    public function name(): string
    {
        return 'contacts_search';
    }

    public function summary(): string
    {
        return 'Search contacts (persons) inside the caller\'s scope by name, email, or phone number.';
    }

    public function permission(): string
    {
        return 'contacts.persons';
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function handle(array $params): array
    {
        $persons = $this->scopedPersons()
            ->with('organization')
            ->when($params['query'] ?? null, function (Builder $query, string $words) {
                $escaped = '%'.addcslashes($words, '%_\\').'%';

                $query->where(function (Builder $sub) use ($escaped) {
                    $sub->where('name', 'like', $escaped)
                        ->orWhere('emails', 'like', $escaped)
                        ->orWhere('contact_numbers', 'like', $escaped);
                });
            })
            ->latest('id')
            ->limit($params['limit'] ?? 20)
            ->get();

        return [
            'persons' => $persons->map(fn (Person $person) => $this->presentPerson($person))->all(),
        ];
    }
}
