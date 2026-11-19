<?php

namespace Webkul\Agent\Actions\Contacts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Event;
use Webkul\Agent\Actions\AgentAction;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\Contact\Repositories\PersonRepository;

abstract class ContactAction extends AgentAction
{
    public function __construct(
        protected PersonRepository $personRepository,
        protected OrganizationRepository $organizationRepository,
    ) {}

    /**
     * @return Builder<Person>
     */
    protected function scopedPersons(): Builder
    {
        return bouncer()->scopeToAuthorizedUsers($this->personRepository->getModel()->newQuery());
    }

    public static function findScopedByEmail(PersonRepository $personRepository, string $email): ?Person
    {
        $needle = strtolower(trim($email));

        if ($needle === '') {
            return null;
        }

        $candidates = bouncer()
            ->scopeToAuthorizedUsers($personRepository->getModel()->newQuery())
            ->where('emails', 'like', '%'.addcslashes($needle, '%_\\').'%')
            ->get();

        /** @var Person $candidate */
        foreach ($candidates as $candidate) {
            $emails = $candidate->getAttribute('emails');

            foreach (is_array($emails) ? $emails : [] as $entry) {
                if (is_array($entry) && strtolower((string) ($entry['value'] ?? '')) === $needle) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * @return Builder<Organization>
     */
    protected function scopedOrganizations(): Builder
    {
        return bouncer()->scopeToAuthorizedUsers($this->organizationRepository->getModel()->newQuery());
    }

    protected function resolveOrCreateScopedOrganization(string $name): int
    {
        $trimmed = trim($name);

        /** @var Organization|null $existing */
        $existing = $this->scopedOrganizations()->where('name', $trimmed)->first();

        if ($existing instanceof Organization) {
            return (int) $existing->getKey();
        }

        Event::dispatch('contacts.organization.create.before');

        /** @var Organization $created */
        $created = $this->organizationRepository->create([
            'entity_type' => 'organizations',
            'name' => $trimmed,
            'user_id' => auth()->guard('user')->id(),
        ]);

        Event::dispatch('contacts.organization.create.after', $created);

        return (int) $created->getKey();
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function snapshotPerson(Person $person): array
    {
        return [
            'id' => $person->getKey(),
            'name' => $person->getAttribute('name'),
            'email' => $this->primaryEmail($person),
            'phone' => $this->primaryPhone($person),
            'organization_id' => $person->getAttribute('organization_id'),
            'owner_id' => $person->getAttribute('user_id'),
            'updated_at' => $person->getAttribute('updated_at')?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentPerson(Person $person): array
    {
        $emails = $person->getAttribute('emails');
        $phones = $person->getAttribute('contact_numbers');

        return [
            'id' => $person->getKey(),
            'name' => $person->getAttribute('name'),
            'email' => $this->primaryEmail($person),
            'emails' => is_array($emails) ? $emails : [],
            'phone' => $this->primaryPhone($person),
            'contact_numbers' => is_array($phones) ? $phones : [],
            'job_title' => $person->getAttribute('job_title'),
            'organization_id' => $person->getAttribute('organization_id'),
            'organization' => $person->getRelationValue('organization')?->getAttribute('name'),
            'owner_id' => $person->getAttribute('user_id'),
            'created_at' => $person->getAttribute('created_at')?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentOrganization(Organization $organization): array
    {
        return [
            'id' => $organization->getKey(),
            'name' => $organization->getAttribute('name'),
            'address' => $organization->getAttribute('address'),
            'owner_id' => $organization->getAttribute('user_id'),
            'created_at' => $organization->getAttribute('created_at')?->toIso8601String(),
        ];
    }

    protected function primaryEmail(Person $person): ?string
    {
        $emails = $person->getAttribute('emails');
        $first = is_array($emails) ? ($emails[0] ?? null) : null;

        return is_array($first) && isset($first['value']) ? (string) $first['value'] : null;
    }

    protected function primaryPhone(Person $person): ?string
    {
        $phones = $person->getAttribute('contact_numbers');
        $first = is_array($phones) ? ($phones[0] ?? null) : null;

        return is_array($first) && isset($first['value']) ? (string) $first['value'] : null;
    }
}
