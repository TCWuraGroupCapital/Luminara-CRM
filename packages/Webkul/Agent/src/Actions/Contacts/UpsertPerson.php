<?php

namespace Webkul\Agent\Actions\Contacts;

use Illuminate\Support\Facades\Event;
use Webkul\Agent\Exceptions\AgentAccessDeniedException;
use Webkul\Contact\Models\Person;

class UpsertPerson extends ContactAction
{
    public function name(): string
    {
        return 'contacts_upsert_person';
    }

    public function summary(): string
    {
        return 'Create a contact or update an existing contact matched by person_id or email inside the caller\'s scope.';
    }

    public function permission(): string
    {
        return 'contacts.persons.create';
    }

    public function rules(): array
    {
        return [
            'person_id' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'organization_id' => ['nullable', 'integer', 'min:1'],
            'organization_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function subject(array $params): array
    {
        $email = strtolower(trim((string) $params['email']));
        $existing = isset($params['person_id'])
            ? $this->scopedPersons()->whereKey($params['person_id'])->firstOrFail()
            : self::findScopedByEmail($this->personRepository, $email);

        return $existing instanceof Person ? [$this->snapshotPerson($existing)] : [];
    }

    public function handle(array $params): array
    {
        $email = strtolower(trim((string) $params['email']));

        $existing = isset($params['person_id'])
            ? $this->scopedPersons()->whereKey($params['person_id'])->firstOrFail()
            : self::findScopedByEmail($this->personRepository, $email);

        if ($existing instanceof Person && ! bouncer()->hasPermission('contacts.persons.edit')) {
            throw new AgentAccessDeniedException('contacts.persons.edit');
        }

        $organizationId = null;

        if (isset($params['organization_id'])) {
            $organizationId = $this->scopedOrganizations()->whereKey($params['organization_id'])->firstOrFail()->getKey();
        } elseif (! empty($params['organization_name'])) {
            $organizationId = $this->resolveOrCreateScopedOrganization((string) $params['organization_name']);
        }

        if ($existing instanceof Person) {
            $existingEmails = $existing->getAttribute('emails');
            $existingPhones = $existing->getAttribute('contact_numbers');
            $emails = $this->mergeContactList(is_array($existingEmails) ? $existingEmails : [], $email);
            $phones = ! empty($params['phone'])
                ? $this->mergeContactList(is_array($existingPhones) ? $existingPhones : [], trim((string) $params['phone']))
                : (is_array($existingPhones) ? $existingPhones : []);

            Event::dispatch('contacts.person.update.before', $existing->getKey());

            /** @var Person $updated */
            $updated = $this->personRepository->update(array_filter([
                'entity_type' => 'persons',
                'name' => $params['name'],
                'emails' => $emails,
                'contact_numbers' => $phones,
                'job_title' => $params['job_title'] ?? $existing->getAttribute('job_title'),
                'organization_id' => $organizationId ?? $existing->getAttribute('organization_id'),
                'user_id' => $existing->getAttribute('user_id') ?? auth()->guard('user')->id(),
            ], fn ($v) => $v !== null), $existing->getKey());

            Event::dispatch('contacts.person.update.after', $updated);

            return ['person' => $this->presentPerson($updated->refresh()), 'created' => false];
        }

        Event::dispatch('contacts.person.create.before');

        /** @var Person $created */
        $created = $this->personRepository->create(array_filter([
            'entity_type' => 'persons',
            'name' => $params['name'],
            'emails' => [['value' => $email, 'label' => 'work']],
            'contact_numbers' => ! empty($params['phone'])
                ? [['value' => trim((string) $params['phone']), 'label' => 'work']]
                : null,
            'job_title' => $params['job_title'] ?? null,
            'organization_id' => $organizationId,
            'user_id' => auth()->guard('user')->id(),
        ], fn ($v) => $v !== null));

        Event::dispatch('contacts.person.create.after', $created);

        return ['person' => $this->presentPerson($created->refresh()), 'created' => true];
    }

    /**
     * @param  array<int, mixed>  $existing
     * @return array<int, array{value: string, label: string}>
     */
    private function mergeContactList(array $existing, string $value): array
    {
        $normalized = [['value' => $value, 'label' => 'work']];

        foreach ($existing as $entry) {
            if (! is_array($entry) || empty($entry['value'])) {
                continue;
            }

            if (strcasecmp((string) $entry['value'], $value) === 0) {
                continue;
            }

            $normalized[] = [
                'value' => (string) $entry['value'],
                'label' => (string) ($entry['label'] ?? 'work'),
            ];
        }

        return $normalized;
    }
}
