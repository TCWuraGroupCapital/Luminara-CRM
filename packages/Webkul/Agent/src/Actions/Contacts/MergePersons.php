<?php

namespace Webkul\Agent\Actions\Contacts;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Quote\Models\Quote;

class MergePersons extends ContactAction
{
    public function name(): string
    {
        return 'contacts_merge_persons';
    }

    public function summary(): string
    {
        return 'Merge a duplicate contact into a primary contact, transferring leads, quotes, and activities before deleting the duplicate. Always waits for human approval.';
    }

    public function permission(): string
    {
        return 'contacts.persons.delete';
    }

    public function approval(): string
    {
        return self::APPROVAL_ALWAYS;
    }

    public function rules(): array
    {
        return [
            'primary_person_id' => ['required', 'integer', 'min:1'],
            'duplicate_person_id' => ['required', 'integer', 'min:1', 'different:primary_person_id'],
        ];
    }

    public function subject(array $params): array
    {
        $primary = $this->scopedPersons()->whereKey($params['primary_person_id'])->firstOrFail();
        $duplicate = $this->scopedPersons()->whereKey($params['duplicate_person_id'])->firstOrFail();

        return [
            $this->snapshotPerson($primary) + ['role' => 'primary'],
            $this->snapshotPerson($duplicate) + ['role' => 'duplicate_to_delete'],
        ];
    }

    public function handle(array $params): array
    {
        /** @var Person $primary */
        $primary = $this->scopedPersons()->whereKey($params['primary_person_id'])->firstOrFail();

        /** @var Person $duplicate */
        $duplicate = $this->scopedPersons()->whereKey($params['duplicate_person_id'])->firstOrFail();

        $duplicateId = $duplicate->getKey();
        $authorizedIds = bouncer()->getAuthorizedUserIds();

        if ($authorizedIds !== null) {
            $hasOutOfScopeLead = Lead::query()
                ->where('person_id', $duplicateId)
                ->where(fn ($q) => $q->whereNull('user_id')->orWhereNotIn('user_id', $authorizedIds))
                ->exists();
            $hasOutOfScopeQuote = Quote::query()
                ->where('person_id', $duplicateId)
                ->where(fn ($q) => $q->whereNull('user_id')->orWhereNotIn('user_id', $authorizedIds))
                ->exists();

            if ($hasOutOfScopeLead || $hasOutOfScopeQuote) {
                throw (new ModelNotFoundException)->setModel(Person::class, [$duplicateId]);
            }
        }

        Lead::query()
            ->where('person_id', $duplicateId)
            ->when($authorizedIds !== null, fn ($q) => $q->whereIn('user_id', $authorizedIds))
            ->update(['person_id' => $primary->getKey()]);
        Quote::query()
            ->where('person_id', $duplicateId)
            ->when($authorizedIds !== null, fn ($q) => $q->whereIn('user_id', $authorizedIds))
            ->update(['person_id' => $primary->getKey()]);

        $activityIds = $duplicate->activities()->pluck('activities.id')->all();

        if ($activityIds !== []) {
            $primary->activities()->syncWithoutDetaching($activityIds);
            $duplicate->activities()->detach();
        }

        $tagIds = $duplicate->tags()->pluck('tags.id')->all();

        if ($tagIds !== []) {
            $primary->tags()->syncWithoutDetaching($tagIds);
            $duplicate->tags()->detach();
        }

        $primaryEmails = $primary->getAttribute('emails');
        $duplicateEmails = $duplicate->getAttribute('emails');
        $primaryPhones = $primary->getAttribute('contact_numbers');
        $duplicatePhones = $duplicate->getAttribute('contact_numbers');

        $emails = $this->combineEntries(is_array($primaryEmails) ? $primaryEmails : [], is_array($duplicateEmails) ? $duplicateEmails : []);
        $phones = $this->combineEntries(is_array($primaryPhones) ? $primaryPhones : [], is_array($duplicatePhones) ? $duplicatePhones : []);

        Event::dispatch('contacts.person.update.before', $primary->getKey());

        /** @var Person $updatedPrimary */
        $updatedPrimary = $this->personRepository->update([
            'entity_type' => 'persons',
            'name' => $primary->getAttribute('name'),
            'emails' => $emails,
            'contact_numbers' => $phones,
            'job_title' => $primary->getAttribute('job_title') ?: $duplicate->getAttribute('job_title'),
            'organization_id' => $primary->getAttribute('organization_id') ?: $duplicate->getAttribute('organization_id'),
            'user_id' => $primary->getAttribute('user_id') ?? $duplicate->getAttribute('user_id') ?? auth()->guard('user')->id(),
        ], $primary->getKey());

        Event::dispatch('contacts.person.update.after', $updatedPrimary);

        Event::dispatch('contacts.person.delete.before', $duplicateId);

        DB::table('attribute_values')
            ->where('entity_type', 'persons')
            ->where('entity_id', $duplicateId)
            ->delete();

        $this->personRepository->delete($duplicateId);

        Event::dispatch('contacts.person.delete.after', $duplicateId);

        return [
            'person' => $this->presentPerson($primary->refresh()),
            'merged_duplicate_id' => $duplicateId,
        ];
    }

    /**
     * @param  array<int, mixed>  $first
     * @param  array<int, mixed>  $second
     * @return array<int, array{value: string, label: string}>
     */
    private function combineEntries(array $first, array $second): array
    {
        $seen = [];
        $merged = [];

        foreach (array_merge($first, $second) as $entry) {
            if (! is_array($entry) || empty($entry['value'])) {
                continue;
            }

            $key = strtolower(trim((string) $entry['value']));

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $merged[] = [
                'value' => trim((string) $entry['value']),
                'label' => (string) ($entry['label'] ?? 'work'),
            ];
        }

        return $merged;
    }
}
