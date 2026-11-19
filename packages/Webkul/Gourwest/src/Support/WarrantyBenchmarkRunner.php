<?php

namespace Webkul\Gourwest\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Webkul\Gourwest\Models\CustomerProfile;
use Webkul\Gourwest\Models\Integration;
use Webkul\Gourwest\Models\ServiceTicket;
use Webkul\Gourwest\Models\SyncEvent;
use Webkul\Security\Support\Totp;

class WarrantyBenchmarkRunner
{
    /**
     * Time unified retrieval queries against whatever customer volume the database currently holds.
     *
     * `five_million_ready` only audits that the required indexes exist; `capacity_verified` is true
     * solely when the latency target was met with at least the warranty record count actually loaded.
     *
     * @return array{
     *     samples: int,
     *     min_ms: float,
     *     p50_ms: float,
     *     p95_ms: float,
     *     p99_ms: float,
     *     max_ms: float,
     *     mean_ms: float,
     *     sla_target_ms: float,
     *     passes_3s_sla: bool,
     *     uptime_target_percent: float,
     *     capacity_target_records: int,
     *     dataset_records: int,
     *     capacity_verified: bool,
     *     five_million_ready: bool,
     *     mfa_ready: bool,
     *     privacy_regimes_covered: int,
     *     privacy_regimes_ready: bool,
     *     health_probe_ready: bool,
     *     index_audit: array<string, array{table: string, exists: bool, index_count: int, key_indexes: list<string>, ready: bool}>,
     *     measured_at: string
     * }
     */
    public function run(int $samples = 25): array
    {
        $regions = RegionCatalog::codes();
        $tiers = ['Platinum', 'Gold', 'Silver', 'Bronze'];
        $datasetRecords = CustomerProfile::query()->count();
        $minId = (int) CustomerProfile::query()->min('id');
        $maxId = (int) CustomerProfile::query()->max('id');

        $durationsMs = [];

        for ($i = 0; $i < $samples; $i++) {
            $region = $regions[$i % count($regions)];
            $tier = $tiers[$i % count($tiers)];
            $mode = $i % 5;

            // Pick a different random customer per sample (outside the timed section) so the
            // measurement covers the whole table rather than one repeatedly cached row.
            $sampleProfile = $maxId > 0
                ? CustomerProfile::query()
                    ->select(['golden_record_uid', 'loyalty_member_number'])
                    ->where('id', '>=', random_int($minId, $maxId))
                    ->orderBy('id')
                    ->first()
                : null;
            $sampleUid = $sampleProfile !== null ? $sampleProfile->golden_record_uid : 'GW-GOLDEN-PROBE';
            $sampleMember = $sampleProfile !== null ? $sampleProfile->loyalty_member_number : 'GW-AU-1001';

            $t0 = hrtime(true);

            if ($mode === 0) {
                CustomerProfile::query()
                    ->with('person')
                    ->where('golden_record_uid', $sampleUid)
                    ->where('is_primary_golden', true)
                    ->limit(5)
                    ->get();
            } elseif ($mode === 1) {
                CustomerProfile::query()
                    ->with('person')
                    ->where('region_code', $region)
                    ->where('loyalty_tier', $tier)
                    ->limit(10)
                    ->get();
            } elseif ($mode === 2) {
                CustomerProfile::query()
                    ->with('person')
                    ->where('loyalty_member_number', $sampleMember)
                    ->first();
            } elseif ($mode === 3) {
                ServiceTicket::query()
                    ->where('region_code', $region)
                    ->whereIn('status', ['open', 'investigating'])
                    ->orderBy('priority')
                    ->limit(10)
                    ->get();
            } else {
                $integration = Integration::query()->orderBy('id')->first();

                if ($integration !== null) {
                    SyncEvent::query()
                        ->where('integration_id', $integration->id)
                        ->whereIn('status', ['processed', 'dead_letter', 'replayed'])
                        ->latest('id')
                        ->limit(10)
                        ->get();
                } else {
                    Integration::query()->where('status', 'healthy')->count();
                }
            }

            $durationsMs[] = (hrtime(true) - $t0) / 1_000_000.0;
        }

        sort($durationsMs);

        $count = count($durationsMs);
        $minMs = round($durationsMs[0], 3);
        $p50Ms = round($this->percentile($durationsMs, 0.50), 3);
        $p95Ms = round($this->percentile($durationsMs, 0.95), 3);
        $p99Ms = round($this->percentile($durationsMs, 0.99), 3);
        $maxMs = round($durationsMs[$count - 1], 3);
        $meanMs = round(array_sum($durationsMs) / $count, 3);
        $slaTargetMs = (float) config('gourwest.sla_target_ms', 3000);
        $capacityTarget = (int) config('gourwest.capacity_target_records', 5000000);

        $indexAudit = $this->auditCapacityIndexes();
        $fiveMillionReady = collect($indexAudit)->every(static fn (array $entry): bool => $entry['ready']);

        $mfaReady = class_exists(Totp::class)
            && Schema::hasTable('user_mfa_settings')
            && Schema::hasTable('security_audit_logs');

        $privacyRegimesCount = count(RegionCatalog::all());
        $privacyReady = $privacyRegimesCount === 7;
        $healthProbeReady = $this->probeDeepReadiness();

        return [
            'samples' => $count,
            'min_ms' => $minMs,
            'p50_ms' => $p50Ms,
            'p95_ms' => $p95Ms,
            'p99_ms' => $p99Ms,
            'max_ms' => $maxMs,
            'mean_ms' => $meanMs,
            'sla_target_ms' => $slaTargetMs,
            'passes_3s_sla' => $p95Ms <= $slaTargetMs,
            'uptime_target_percent' => (float) config('gourwest.uptime_target_percent', 99.9),
            'capacity_target_records' => $capacityTarget,
            'dataset_records' => $datasetRecords,
            'capacity_verified' => $datasetRecords >= $capacityTarget && $p95Ms <= $slaTargetMs,
            'five_million_ready' => $fiveMillionReady,
            'mfa_ready' => $mfaReady,
            'privacy_regimes_covered' => $privacyRegimesCount,
            'privacy_regimes_ready' => $privacyReady,
            'health_probe_ready' => $healthProbeReady,
            'index_audit' => $indexAudit,
            'measured_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  list<float>  $sorted
     */
    private function percentile(array $sorted, float $quantile): float
    {
        $n = count($sorted);

        if ($n === 0) {
            return 0.0;
        }

        $position = ($n - 1) * $quantile;
        $lower = (int) floor($position);
        $upper = (int) ceil($position);

        if ($lower === $upper) {
            return $sorted[$lower];
        }

        $weight = $position - $lower;

        return $sorted[$lower] * (1.0 - $weight) + $sorted[$upper] * $weight;
    }

    /**
     * Verify composite and foreign-key indexes across core 5M-record tables.
     *
     * @return array<string, array{table: string, exists: bool, index_count: int, key_indexes: list<string>, ready: bool}>
     */
    private function auditCapacityIndexes(): array
    {
        $requiredColumnsByTable = [
            'gourwest_customer_profiles' => [
                ['region_code', 'loyalty_tier'],
                ['golden_record_uid', 'is_primary_golden'],
                ['loyalty_member_number'],
            ],
            'persons' => [
                ['unique_id'],
                ['user_id'],
            ],
            'leads' => [
                ['person_id'],
                ['user_id'],
            ],
            'experiment_events' => [
                ['experiment_id', 'variant_key', 'event_type'],
            ],
        ];

        $audit = [];

        foreach ($requiredColumnsByTable as $table => $expectedColumnSets) {
            if (! Schema::hasTable($table)) {
                $audit[$table] = [
                    'table' => $table,
                    'exists' => false,
                    'index_count' => 0,
                    'key_indexes' => [],
                    'ready' => false,
                ];

                continue;
            }

            $indexes = Schema::getIndexes($table);
            $indexNames = [];

            foreach ($indexes as $idx) {
                $indexNames[] = $idx['name'];
            }

            $allMatched = true;

            foreach ($expectedColumnSets as $expectedCols) {
                $matchedSet = false;

                foreach ($indexes as $idx) {
                    $cols = $idx['columns'];

                    if (array_slice($cols, 0, count($expectedCols)) === $expectedCols) {
                        $matchedSet = true;

                        break;
                    }
                }

                if (! $matchedSet) {
                    $allMatched = false;

                    break;
                }
            }

            $audit[$table] = [
                'table' => $table,
                'exists' => true,
                'index_count' => count($indexes),
                'key_indexes' => $indexNames,
                'ready' => $allMatched,
            ];
        }

        return $audit;
    }

    private function probeDeepReadiness(): bool
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');

            $probeKey = 'gourwest:warranty_probe:'.bin2hex(random_bytes(6));
            Cache::store()->put($probeKey, 'ok', 10);
            $val = Cache::store()->get($probeKey);
            Cache::store()->forget($probeKey);

            $frameworkDir = storage_path('framework');

            return $val === 'ok' && is_dir($frameworkDir) && is_writable($frameworkDir);
        } catch (Throwable) {
            return false;
        }
    }
}
