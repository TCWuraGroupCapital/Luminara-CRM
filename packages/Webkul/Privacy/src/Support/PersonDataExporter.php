<?php

namespace Webkul\Privacy\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Everything the CRM holds about one person, for an access or portability request
 * (Australian Privacy Principle 12, GDPR articles 15 and 20). Read straight from the tables so
 * the export does not depend on display formatting.
 */
class PersonDataExporter
{
    /**
     * @return array<string, mixed>|null null when the person does not exist
     */
    public function export(int $personId): ?array
    {
        $person = DB::table('persons')->where('id', $personId)->first();

        if ($person === null) {
            return null;
        }

        return [
            'generated_at' => now()->utc()->toIso8601String(),
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'emails' => $this->json($person->emails),
                'contact_numbers' => $this->json($person->contact_numbers),
                'job_title' => $person->job_title ?? null,
                'organization' => $person->organization_id
                    ? DB::table('organizations')->where('id', $person->organization_id)->value('name')
                    : null,
                'erased_at' => $person->erased_at ?? null,
                'created_at' => $person->created_at,
                'updated_at' => $person->updated_at,
                'custom_fields' => $this->customFields($personId),
            ],
            'tags' => DB::table('person_tags')
                ->join('tags', 'tags.id', '=', 'person_tags.tag_id')
                ->where('person_tags.person_id', $personId)
                ->pluck('tags.name')
                ->all(),
            'leads' => DB::table('leads')
                ->leftJoin('lead_pipeline_stages', 'lead_pipeline_stages.id', '=', 'leads.lead_pipeline_stage_id')
                ->where('leads.person_id', $personId)
                ->orderBy('leads.id')
                ->get(['leads.id', 'leads.title', 'leads.description', 'leads.lead_value', 'leads.status', 'lead_pipeline_stages.name as stage', 'leads.created_at', 'leads.closed_at'])
                ->map(fn ($row) => (array) $row)
                ->all(),
            'activities' => DB::table('activities')
                ->whereIn('id', $this->activityIds($personId))
                ->orderBy('id')
                ->get(['id', 'type', 'title', 'comment', 'location', 'schedule_from', 'schedule_to', 'is_done', 'created_at'])
                ->map(fn ($row) => (array) $row)
                ->all(),
            'emails' => $this->emails($personId),
            'quotes' => DB::table('quotes')
                ->where('person_id', $personId)
                ->orderBy('id')
                ->get(['id', 'subject', 'description', 'billing_address', 'shipping_address', 'grand_total', 'expired_at', 'created_at'])
                ->map(fn ($row) => array_merge((array) $row, [
                    'billing_address' => $this->json($row->billing_address),
                    'shipping_address' => $this->json($row->shipping_address),
                ]))
                ->all(),
            'customer_facts' => Schema::hasTable('agent_customer_facts')
                ? DB::table('agent_customer_facts')
                    ->where('entity_type', 'person')
                    ->where('entity_id', $personId)
                    ->orderBy('id')
                    ->get(['category', 'fact', 'status', 'valid_until', 'created_at'])
                    ->map(fn ($row) => (array) $row)
                    ->all()
                : [],
            'ledger_entries' => Schema::hasTable('data_blocks')
                ? DB::table('data_blocks')->where('entity_type', 'person')->where('entity_id', (string) $personId)->count()
                : 0,
        ];
    }

    /**
     * Activities linked to the person directly or as a participant.
     *
     * @return array<int, int>
     */
    public function activityIds(int $personId): array
    {
        return DB::table('person_activities')->where('person_id', $personId)->pluck('activity_id')
            ->merge(DB::table('activity_participants')->where('person_id', $personId)->pluck('activity_id'))
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The person's emails and every reply threaded under them.
     *
     * @return array<int, int>
     */
    public function emailIds(int $personId): array
    {
        $ids = DB::table('emails')->where('person_id', $personId)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $frontier = $ids;

        while ($frontier !== []) {
            $children = DB::table('emails')
                ->whereIn('parent_id', $frontier)
                ->whereNotIn('id', $ids)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function emails(int $personId): array
    {
        $ids = $this->emailIds($personId);

        $attachments = DB::table('email_attachments')
            ->whereIn('email_id', $ids)
            ->get(['email_id', 'name', 'size', 'content_type'])
            ->groupBy('email_id');

        return DB::table('emails')
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get(['id', 'subject', 'from', 'reply_to', 'cc', 'bcc', 'reply', 'created_at'])
            ->map(fn ($row) => [
                'id' => $row->id,
                'subject' => $row->subject,
                'from' => $this->json($row->from),
                'to' => $this->json($row->reply_to),
                'cc' => $this->json($row->cc),
                'bcc' => $this->json($row->bcc),
                'body' => $row->reply,
                'created_at' => $row->created_at,
                'attachments' => ($attachments[$row->id] ?? collect())
                    ->map(static fn ($attachment) => ['name' => $attachment->name, 'size' => $attachment->size, 'type' => $attachment->content_type])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function customFields(int $personId): array
    {
        return DB::table('attribute_values')
            ->join('attributes', 'attributes.id', '=', 'attribute_values.attribute_id')
            ->where('attribute_values.entity_type', 'persons')
            ->where('attribute_values.entity_id', $personId)
            ->get(['attributes.code', 'text_value', 'boolean_value', 'integer_value', 'float_value', 'datetime_value', 'date_value', 'json_value'])
            ->mapWithKeys(fn ($row) => [
                $row->code => $row->text_value ?? $row->boolean_value ?? $row->integer_value ?? $row->float_value
                    ?? $row->datetime_value ?? $row->date_value ?? $this->json($row->json_value),
            ])
            ->all();
    }

    private function json(mixed $value): mixed
    {
        return is_string($value) ? json_decode($value, true) : $value;
    }
}
