<?php

namespace Webkul\Gourwest\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;
use Webkul\Gourwest\Models\CustomerProfile;
use Webkul\Gourwest\Repositories\CustomerProfileRepository;
use Webkul\Security\Support\SecurityAuditRecorder;

class GoldenRecordResolver
{
    public function __construct(
        protected CustomerProfileRepository $profileRepository
    ) {}

    public static function normalizeEmail(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $normalized = strtolower(trim($raw));

        return $normalized !== '' && str_contains($normalized, '@') ? $normalized : null;
    }

    public static function normalizePhone(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        return strlen($digits) >= 8 ? $digits : null;
    }

    /**
     * Detect cross-border duplicate clusters across active Golden Record candidates.
     *
     * @param  array<int, int>|null  $authorizedUserIds
     * @return list<array{
     *     primary_profile_id: int,
     *     primary_golden_uid: string,
     *     primary_member_number: string,
     *     customer_name: string,
     *     regions: list<string>,
     *     matched_keys: list<string>,
     *     secondary_profile_ids: list<int>,
     *     secondary_member_numbers: list<string>,
     *     combined_points: int,
     *     combined_spend_aud: float,
     *     projected_tier: string
     * }>
     */
    public function detectDuplicates(?array $authorizedUserIds = null): array
    {
        /** @var Collection<int, CustomerProfile> $profiles */
        $profiles = $this->profileRepository
            ->scopedQuery($authorizedUserIds)
            ->where('is_primary_golden', true)
            ->whereNull('merged_into_profile_id')
            ->orderBy('id')
            ->get();

        return $this->buildDuplicateClusters($profiles);
    }

    /**
     * Atomically merge detected cross-region duplicate profiles into single Golden Records.
     *
     * @param  array<int, int>|null  $authorizedUserIds
     * @return array{
     *     clusters_merged: int,
     *     profiles_merged: int,
     *     points_consolidated: int,
     *     merged_records: list<array<string, mixed>>
     * }
     */
    public function mergeDuplicates(?array $authorizedUserIds = null): array
    {
        $detected = $this->detectDuplicates($authorizedUserIds);

        if ($detected === []) {
            return [
                'clusters_merged' => 0,
                'profiles_merged' => 0,
                'points_consolidated' => 0,
                'merged_records' => [],
            ];
        }

        return DB::transaction(function () use ($detected): array {
            $clustersMerged = 0;
            $profilesMerged = 0;
            $pointsConsolidated = 0;
            $mergedRecords = [];

            foreach ($detected as $cluster) {
                $allIds = array_merge([$cluster['primary_profile_id']], $cluster['secondary_profile_ids']);

                /** @var Collection<int, CustomerProfile> $locked */
                $locked = CustomerProfile::query()
                    ->with('person')
                    ->whereIn('id', $allIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                /** @var CustomerProfile|null $primary */
                $primary = $locked->get($cluster['primary_profile_id']);

                if ($primary === null || ! $primary->is_primary_golden || $primary->merged_into_profile_id !== null) {
                    continue;
                }

                $activeSecondaries = [];

                foreach ($cluster['secondary_profile_ids'] as $secId) {
                    /** @var CustomerProfile|null $sec */
                    $sec = $locked->get($secId);

                    if ($sec !== null && $sec->is_primary_golden && $sec->merged_into_profile_id === null) {
                        $activeSecondaries[] = $sec;
                    }
                }

                if ($activeSecondaries === []) {
                    continue;
                }

                $totalPoints = (int) $primary->loyalty_points;
                $totalSpend = (float) $primary->lifetime_spend_aud;
                $addedPoints = 0;
                $mergedRegions = [$primary->region_code];
                $mergedMemberNumbers = [];
                $strictestConsent = $primary->consent_status;

                foreach ($activeSecondaries as $secondary) {
                    $secPoints = max(0, (int) $secondary->loyalty_points);
                    $addedPoints += $secPoints;
                    $totalPoints += $secPoints;
                    $totalSpend += (float) $secondary->lifetime_spend_aud;
                    $mergedRegions[] = $secondary->region_code;
                    $mergedMemberNumbers[] = $secondary->loyalty_member_number;

                    if ($secondary->consent_status === 'restricted') {
                        $strictestConsent = 'restricted';
                    } elseif ($secondary->consent_status === 'pending' && $strictestConsent === 'verified') {
                        $strictestConsent = 'pending';
                    }

                    $this->consolidatePersonContacts($primary->person, $secondary->person);

                    $secondary->update([
                        'is_primary_golden' => false,
                        'merged_into_profile_id' => $primary->id,
                        'golden_record_uid' => $primary->golden_record_uid,
                        'loyalty_points' => 0,
                        'last_synced_at' => now(),
                    ]);

                    $profilesMerged++;
                }

                $newTier = RegionCatalog::tierForPoints($totalPoints);

                $primary->update([
                    'loyalty_points' => $totalPoints,
                    'lifetime_spend_aud' => round($totalSpend, 2),
                    'loyalty_tier' => $newTier,
                    'consent_status' => $strictestConsent,
                    'is_primary_golden' => true,
                    'last_synced_at' => now(),
                ]);

                $clustersMerged++;
                $pointsConsolidated += $addedPoints;

                $auditSummary = [
                    'golden_record_uid' => $primary->golden_record_uid,
                    'primary_profile_id' => $primary->id,
                    'secondary_profile_ids' => array_map(static fn (CustomerProfile $p): int => $p->id, $activeSecondaries),
                    'secondary_member_numbers' => $mergedMemberNumbers,
                    'regions_unified' => array_values(array_unique($mergedRegions)),
                    'matched_keys' => $cluster['matched_keys'],
                    'points_added' => $addedPoints,
                    'new_loyalty_points' => $totalPoints,
                    'new_loyalty_tier' => $newTier,
                    'new_lifetime_spend_aud' => round($totalSpend, 2),
                ];

                SecurityAuditRecorder::record(
                    event: 'gourwest.golden_record.merged',
                    auditableType: 'gourwest_customer_profile',
                    auditableId: $primary->id,
                    metadata: $auditSummary
                );

                $mergedRecords[] = $auditSummary;
            }

            return [
                'clusters_merged' => $clustersMerged,
                'profiles_merged' => $profilesMerged,
                'points_consolidated' => $pointsConsolidated,
                'merged_records' => $mergedRecords,
            ];
        });
    }

    /**
     * @param  Collection<int, CustomerProfile>  $profiles
     * @return list<array{
     *     primary_profile_id: int,
     *     primary_golden_uid: string,
     *     primary_member_number: string,
     *     customer_name: string,
     *     regions: list<string>,
     *     matched_keys: list<string>,
     *     secondary_profile_ids: list<int>,
     *     secondary_member_numbers: list<string>,
     *     combined_points: int,
     *     combined_spend_aud: float,
     *     projected_tier: string
     * }>
     */
    private function buildDuplicateClusters(Collection $profiles): array
    {
        if ($profiles->count() < 2) {
            return [];
        }

        $parent = [];
        $keyOwners = [];
        $profileKeys = [];

        foreach ($profiles as $profile) {
            $parent[$profile->id] = $profile->id;
            $keys = $this->extractIdentityKeys($profile->person);
            $profileKeys[$profile->id] = $keys;

            foreach ($keys as $identityKey) {
                if (isset($keyOwners[$identityKey])) {
                    $this->union($parent, $keyOwners[$identityKey], $profile->id);
                } else {
                    $keyOwners[$identityKey] = $profile->id;
                }
            }
        }

        $byRoot = [];

        foreach ($profiles as $profile) {
            $root = $this->find($parent, $profile->id);
            $byRoot[$root][] = $profile;
        }

        $clusters = [];

        foreach ($byRoot as $group) {
            if (count($group) < 2) {
                continue;
            }

            usort($group, static function (CustomerProfile $a, CustomerProfile $b): int {
                $timeA = $a->created_at !== null ? $a->created_at->getTimestamp() : 0;
                $timeB = $b->created_at !== null ? $b->created_at->getTimestamp() : 0;

                return $timeA !== $timeB ? ($timeA <=> $timeB) : ($a->id <=> $b->id);
            });

            $primary = $group[0];
            $secondaries = array_slice($group, 1);

            $keyFrequency = [];

            foreach ($group as $member) {
                foreach ($profileKeys[$member->id] ?? [] as $k) {
                    $keyFrequency[$k] = ($keyFrequency[$k] ?? 0) + 1;
                }
            }

            $matchedKeys = array_keys(array_filter($keyFrequency, static fn (int $cnt): bool => $cnt >= 2));

            $combinedPoints = 0;
            $combinedSpend = 0.0;
            $regions = [];

            foreach ($group as $member) {
                $combinedPoints += max(0, (int) $member->loyalty_points);
                $combinedSpend += (float) $member->lifetime_spend_aud;
                $regions[] = $member->region_code;
            }

            $primaryPersonName = $primary->person?->getAttribute('name');

            $clusters[] = [
                'primary_profile_id' => $primary->id,
                'primary_golden_uid' => $primary->golden_record_uid,
                'primary_member_number' => $primary->loyalty_member_number,
                'customer_name' => is_string($primaryPersonName) && $primaryPersonName !== '' ? $primaryPersonName : $primary->loyalty_member_number,
                'regions' => array_values(array_unique($regions)),
                'matched_keys' => $matchedKeys,
                'secondary_profile_ids' => array_map(static fn (CustomerProfile $p): int => $p->id, $secondaries),
                'secondary_member_numbers' => array_map(static fn (CustomerProfile $p): string => $p->loyalty_member_number, $secondaries),
                'combined_points' => $combinedPoints,
                'combined_spend_aud' => round($combinedSpend, 2),
                'projected_tier' => RegionCatalog::tierForPoints($combinedPoints),
            ];
        }

        return $clusters;
    }

    /**
     * @return list<string>
     */
    private function extractIdentityKeys(?Person $person): array
    {
        if ($person === null) {
            return [];
        }

        $keys = [];
        $rawEmails = $person->getAttribute('emails');
        $emails = is_array($rawEmails) ? $rawEmails : [];

        foreach ($emails as $entry) {
            $raw = is_array($entry) ? ($entry['value'] ?? null) : (is_string($entry) ? $entry : null);
            $normalized = self::normalizeEmail(is_string($raw) ? $raw : null);

            if ($normalized !== null) {
                $keys[] = 'email:'.$normalized;
            }
        }

        $rawPhones = $person->getAttribute('contact_numbers');
        $phones = is_array($rawPhones) ? $rawPhones : [];

        foreach ($phones as $entry) {
            $raw = is_array($entry) ? ($entry['value'] ?? null) : (is_string($entry) ? $entry : null);
            $normalized = self::normalizePhone(is_string($raw) ? $raw : null);

            if ($normalized !== null) {
                $keys[] = 'phone:'.$normalized;
            }
        }

        return array_values(array_unique($keys));
    }

    private function consolidatePersonContacts(?Person $primaryPerson, ?Person $secondaryPerson): void
    {
        if ($primaryPerson === null || $secondaryPerson === null || $primaryPerson->getKey() === $secondaryPerson->getKey()) {
            return;
        }

        $rawPrimaryEmails = $primaryPerson->getAttribute('emails');
        $primaryEmails = is_array($rawPrimaryEmails) ? $rawPrimaryEmails : [];
        $existingEmails = [];

        foreach ($primaryEmails as $item) {
            $val = is_array($item) ? ($item['value'] ?? null) : null;
            $norm = self::normalizeEmail(is_string($val) ? $val : null);

            if ($norm !== null) {
                $existingEmails[$norm] = true;
            }
        }

        $rawSecondaryEmails = $secondaryPerson->getAttribute('emails');
        $secondaryEmails = is_array($rawSecondaryEmails) ? $rawSecondaryEmails : [];
        $changed = false;

        foreach ($secondaryEmails as $item) {
            if (! is_array($item)) {
                continue;
            }

            $val = $item['value'] ?? null;
            $norm = self::normalizeEmail(is_string($val) ? $val : null);

            if ($norm !== null && ! isset($existingEmails[$norm])) {
                $primaryEmails[] = [
                    'value' => $norm,
                    'label' => isset($item['label']) ? (string) $item['label'] : 'work',
                ];
                $existingEmails[$norm] = true;
                $changed = true;
            }
        }

        $rawPrimaryPhones = $primaryPerson->getAttribute('contact_numbers');
        $primaryPhones = is_array($rawPrimaryPhones) ? $rawPrimaryPhones : [];
        $existingPhones = [];

        foreach ($primaryPhones as $item) {
            $val = is_array($item) ? ($item['value'] ?? null) : null;
            $norm = self::normalizePhone(is_string($val) ? $val : null);

            if ($norm !== null) {
                $existingPhones[$norm] = true;
            }
        }

        $rawSecondaryPhones = $secondaryPerson->getAttribute('contact_numbers');
        $secondaryPhones = is_array($rawSecondaryPhones) ? $rawSecondaryPhones : [];

        foreach ($secondaryPhones as $item) {
            if (! is_array($item)) {
                continue;
            }

            $val = $item['value'] ?? null;
            $norm = self::normalizePhone(is_string($val) ? $val : null);

            if ($norm !== null && ! isset($existingPhones[$norm]) && is_string($val)) {
                $primaryPhones[] = [
                    'value' => $val,
                    'label' => isset($item['label']) ? (string) $item['label'] : 'mobile',
                ];
                $existingPhones[$norm] = true;
                $changed = true;
            }
        }

        if ($changed) {
            $primaryPerson->update([
                'emails' => $primaryEmails,
                'contact_numbers' => $primaryPhones,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $parent
     */
    private function find(array &$parent, int $node): int
    {
        if ($parent[$node] !== $node) {
            $parent[$node] = $this->find($parent, $parent[$node]);
        }

        return $parent[$node];
    }

    /**
     * @param  array<int, int>  $parent
     */
    private function union(array &$parent, int $a, int $b): void
    {
        $rootA = $this->find($parent, $a);
        $rootB = $this->find($parent, $b);

        if ($rootA !== $rootB) {
            if ($rootA < $rootB) {
                $parent[$rootB] = $rootA;
            } else {
                $parent[$rootA] = $rootB;
            }
        }
    }
}
