<?php

namespace Webkul\Agent\Actions\Contacts;

use Illuminate\Support\Facades\Event;
use Webkul\Agent\Exceptions\AgentAccessDeniedException;
use Webkul\Contact\Models\Organization;

class UpsertOrganization extends ContactAction
{
    public function name(): string
    {
        return 'contacts_upsert_organization';
    }

    public function summary(): string
    {
        return 'Create an organization or update an existing organization inside the caller\'s scope.';
    }

    public function permission(): string
    {
        return 'contacts.organizations.create';
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function subject(array $params): array
    {
        $existing = isset($params['organization_id'])
            ? $this->scopedOrganizations()->whereKey($params['organization_id'])->firstOrFail()
            : $this->scopedOrganizations()->where('name', trim((string) $params['name']))->first();

        if (! $existing instanceof Organization) {
            return [];
        }

        return [[
            'entity' => 'organization',
            'id' => $existing->getKey(),
            'name' => $existing->getAttribute('name'),
            'updated_at' => $existing->getAttribute('updated_at')?->toIso8601String(),
        ]];
    }

    public function handle(array $params): array
    {
        $existing = isset($params['organization_id'])
            ? $this->scopedOrganizations()->whereKey($params['organization_id'])->firstOrFail()
            : $this->scopedOrganizations()->where('name', trim((string) $params['name']))->first();

        $addressPayload = array_filter([
            'address' => $params['address'] ?? null,
            'city' => $params['city'] ?? null,
            'country' => $params['country'] ?? null,
        ], fn ($v) => $v !== null);

        if ($existing instanceof Organization) {
            if (! bouncer()->hasPermission('contacts.organizations.edit')) {
                throw new AgentAccessDeniedException('contacts.organizations.edit');
            }

            Event::dispatch('contacts.organization.update.before', $existing->getKey());

            /** @var Organization $updated */
            $updated = $this->organizationRepository->update([
                'entity_type' => 'organizations',
                'name' => trim((string) $params['name']),
                'address' => $addressPayload !== [] ? $addressPayload : $existing->getAttribute('address'),
                'user_id' => $existing->getAttribute('user_id') ?? auth()->guard('user')->id(),
            ], $existing->getKey());

            Event::dispatch('contacts.organization.update.after', $updated);

            return ['organization' => $this->presentOrganization($updated->refresh()), 'created' => false];
        }

        Event::dispatch('contacts.organization.create.before');

        /** @var Organization $created */
        $created = $this->organizationRepository->create([
            'entity_type' => 'organizations',
            'name' => trim((string) $params['name']),
            'address' => $addressPayload !== [] ? $addressPayload : null,
            'user_id' => auth()->guard('user')->id(),
        ]);

        Event::dispatch('contacts.organization.create.after', $created);

        return ['organization' => $this->presentOrganization($created->refresh()), 'created' => true];
    }
}
