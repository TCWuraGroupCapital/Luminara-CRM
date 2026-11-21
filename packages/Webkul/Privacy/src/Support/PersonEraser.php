<?php

namespace Webkul\Privacy\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Webkul\Ledger\Support\LedgerAppender;
use Webkul\Ledger\Support\LedgerRedactor;
use Webkul\Security\Support\SecurityAuditRecorder;

/**
 * Carries out an erasure request (Australian Privacy Principle 11.2, GDPR article 17) for one
 * person, keeping sales records the business may be required to retain.
 *
 * Removed: the person's name, emails, phone numbers, job title and custom field values; their
 * emails and attachments; activities that belong only to them (activities shared with a deal
 * keep the deal's history with their text blanked); approved customer facts; tags; their name
 * in the titles of their deals; copies of their details in agent call logs; and every ledger
 * payload that mentions them (redacted with a signed record, so the chain still verifies).
 *
 * Kept: deals and quotes, now linked to "Erased person #id".
 *
 * Writes go straight to the tables, not through the repositories, so no new ledger copies of
 * the data being erased are made; one non-personal `person.erased` block records the request.
 */
class PersonEraser
{
    public const DISK = 'local';

    public function __construct(
        private PersonDataExporter $exporter,
        private LedgerRedactor $redactor,
        private LedgerAppender $appender,
    ) {}

    public static function placeholder(int $personId): string
    {
        return "Erased person #{$personId}";
    }

    /**
     * @return array<string, int|string|null> counts of what was removed, with no personal data
     */
    public function erase(int $personId, ?int $actorId = null): array
    {
        $attachmentPaths = [];

        $summary = DB::transaction(function () use ($personId, $actorId, &$attachmentPaths): array {
            $person = DB::table('persons')->where('id', $personId)->lockForUpdate()->first();

            if ($person === null) {
                throw new RuntimeException("Person #{$personId} does not exist.");
            }

            if (($person->erased_at ?? null) !== null) {
                throw new RuntimeException("Person #{$personId} was already erased.");
            }

            $name = (string) $person->name;
            $terms = array_merge([$name], $this->values($person->emails), $this->values($person->contact_numbers));
            $placeholder = self::placeholder($personId);

            $emailIds = $this->exporter->emailIds($personId);
            $attachmentPaths = DB::table('email_attachments')->whereIn('email_id', $emailIds)->pluck('path')->all();
            DB::table('email_attachments')->whereIn('email_id', $emailIds)->delete();
            $emails = DB::table('emails')->whereIn('id', $emailIds)->delete();

            $activityIds = $this->exporter->activityIds($personId);
            [$activitiesDeleted, $activitiesBlanked] = $this->eraseActivities($personId);

            $customFields = DB::table('attribute_values')->where('entity_type', 'persons')->where('entity_id', $personId)->delete();
            DB::table('person_tags')->where('person_id', $personId)->delete();

            $facts = Schema::hasTable('agent_customer_facts')
                ? DB::table('agent_customer_facts')->where('entity_type', 'person')->where('entity_id', $personId)->delete()
                : 0;

            $leadsRenamed = $this->scrubLeadTitles($personId, $name, $placeholder);
            $agentCalls = $this->scrubAgentCalls($terms);

            DB::table('persons')->where('id', $personId)->update([
                'name' => $placeholder,
                'emails' => json_encode([]),
                'contact_numbers' => json_encode([]),
                'job_title' => null,
                'unique_id' => 'erased-'.$personId,
                'erased_at' => now(),
                'updated_at' => now(),
            ]);

            $redaction = Schema::hasTable('data_blocks')
                ? $this->redactor->redact(
                    $this->redactor->findMentions('person', (string) $personId, $terms, ['activity' => $activityIds]),
                    'person',
                    (string) $personId,
                    'Erasure request for a person',
                    $actorId,
                )
                : null;

            $summary = [
                'emails' => $emails,
                'attachments' => count($attachmentPaths),
                'activities_deleted' => $activitiesDeleted,
                'activities_blanked' => $activitiesBlanked,
                'custom_fields' => $customFields,
                'customer_facts' => $facts,
                'lead_titles_scrubbed' => $leadsRenamed,
                'agent_calls_scrubbed' => $agentCalls,
                'ledger_blocks_redacted' => $redaction === null ? 0 : count((array) ($redaction->canonical_payload['redacted'] ?? [])),
                'redaction_receipt' => $redaction?->receipt_code,
            ];

            if (Schema::hasTable('data_blocks')) {
                $this->appender->append('person.erased', 'person', (string) $personId, ['erased' => $summary], 'human', $actorId);
            }

            return $summary;
        });

        Storage::disk(self::DISK)->delete(array_values(array_filter($attachmentPaths, 'is_string')));

        SecurityAuditRecorder::record('privacy.person_erased', 'person', (string) $personId, $summary);

        return $summary;
    }

    /**
     * Activities that belong only to this person are deleted, and so are email activities (copies
     * of emails that are being deleted anyway; the timeline also expects their sender and
     * recipients to be present). Any other activity that is also part of a deal's history stays,
     * with its wording removed and the person unlinked.
     *
     * @return array{0: int, 1: int}
     */
    private function eraseActivities(int $personId): array
    {
        $ids = $this->exporter->activityIds($personId);

        $emailActivities = DB::table('activities')->whereIn('id', $ids)->where('type', 'email')->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        $shared = DB::table('lead_activities')
            ->whereIn('activity_id', array_diff($ids, $emailActivities))
            ->pluck('activity_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->all();

        $own = array_values(array_diff($ids, $shared));

        DB::table('person_activities')->where('person_id', $personId)->delete();
        DB::table('activity_participants')->where('person_id', $personId)->delete();

        $blanked = DB::table('activities')->whereIn('id', $shared)->update([
            'title' => self::placeholder($personId),
            'comment' => null,
            'location' => null,
            'additional' => null,
            'updated_at' => now(),
        ]);

        $deleted = DB::table('activities')->whereIn('id', $own)->delete();

        return [$deleted, $blanked];
    }

    private function scrubLeadTitles(int $personId, string $name, string $placeholder): int
    {
        if (mb_strlen(trim($name)) < 2) {
            return 0;
        }

        $changed = 0;

        DB::table('leads')->where('person_id', $personId)->orderBy('id')->each(function ($lead) use ($name, $placeholder, &$changed): void {
            $title = str_ireplace($name, $placeholder, (string) $lead->title);
            $description = $lead->description === null ? null : str_ireplace($name, $placeholder, (string) $lead->description);

            if ($title !== $lead->title || $description !== $lead->description) {
                DB::table('leads')->where('id', $lead->id)->update(['title' => $title, 'description' => $description]);
                $changed++;
            }
        });

        return $changed;
    }

    /**
     * Agent call logs keep what an agent sent and got back, which can include the person's
     * name or email. Matching rows keep their action, status and approval trail.
     *
     * @param  array<int, string>  $terms
     */
    private function scrubAgentCalls(array $terms): int
    {
        $terms = array_values(array_filter(array_map(static fn ($term) => mb_strtolower(trim((string) $term)), $terms), static fn ($term) => mb_strlen($term) >= 4));

        if ($terms === [] || ! Schema::hasTable('agent_action_calls')) {
            return 0;
        }

        $columns = array_values(array_filter(['params', 'subject', 'result'], static fn ($column) => Schema::hasColumn('agent_action_calls', $column)));
        $redacted = json_encode(['redacted' => true]);

        return DB::table('agent_action_calls')
            ->where(function ($query) use ($terms, $columns): void {
                foreach ($columns as $column) {
                    foreach ($terms as $term) {
                        $query->orWhereRaw("LOWER(CAST({$column} AS CHAR)) LIKE ?", ['%'.addcslashes($term, '%_\\').'%']);
                    }
                }
            })
            ->update(array_fill_keys($columns, $redacted));
    }

    /**
     * @return array<int, string>
     */
    private function values(mixed $json): array
    {
        $items = is_string($json) ? json_decode($json, true) : $json;

        return collect(is_array($items) ? $items : [])
            ->map(static fn ($item) => is_array($item) ? ($item['value'] ?? null) : $item)
            ->filter(static fn ($value) => is_string($value) && trim($value) !== '')
            ->values()
            ->all();
    }
}
