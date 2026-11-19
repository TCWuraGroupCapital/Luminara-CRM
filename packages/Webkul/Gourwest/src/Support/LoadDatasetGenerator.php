<?php

namespace Webkul\Gourwest\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Generates a synthetic customer volume (persons + Gourwest profiles) entirely inside MySQL so the
 * retrieval warranty can be measured against a realistic table size instead of inferred from indexes.
 */
class LoadDatasetGenerator
{
    public const UNIQUE_ID_PREFIX = 'gw-load:';

    private const CHUNK = 100000;

    /**
     * Idempotently ensure synthetic customers 1..$records exist.
     *
     * @param  callable(int): void  $onProgress  Receives the highest sequence number processed so far.
     */
    public function generate(int $records, callable $onProgress): void
    {
        $this->assertMysql();

        for ($start = 1; $start <= $records; $start += self::CHUNK) {
            $end = min($start + self::CHUNK - 1, $records);

            DB::insert($this->personsSql(), [$start, $end]);
            DB::insert($this->profilesSql(), [$start, $end]);

            $onProgress($end);
        }

        // Refresh optimiser statistics so the benchmark measures settled query plans.
        DB::statement('ANALYZE TABLE persons, gourwest_customer_profiles');
    }

    public function purge(): int
    {
        $this->assertMysql();

        $deleted = 0;

        // Profiles go with their person through the ON DELETE CASCADE foreign key.
        do {
            $batch = DB::delete(
                'DELETE FROM persons WHERE unique_id LIKE ? LIMIT '.self::CHUNK,
                [self::UNIQUE_ID_PREFIX.'%']
            );
            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }

    private function assertMysql(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('The synthetic load dataset is generated with MySQL-specific SQL.');
        }
    }

    /**
     * Derived table yielding every integer n in [?, ?] (at most CHUNK values).
     */
    private function sequenceSql(): string
    {
        $digits = '(SELECT 0 AS d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 '
            .'UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9)';

        return "(SELECT base.s + a.d + b.d * 10 + c.d * 100 + e.d * 1000 + f.d * 10000 AS n, base.t AS upper
            FROM (SELECT CAST(? AS UNSIGNED) AS s, CAST(? AS UNSIGNED) AS t) base
            CROSS JOIN {$digits} a CROSS JOIN {$digits} b CROSS JOIN {$digits} c
            CROSS JOIN {$digits} e CROSS JOIN {$digits} f) seq";
    }

    private function personsSql(): string
    {
        $prefix = self::UNIQUE_ID_PREFIX;

        return "INSERT IGNORE INTO persons (name, emails, contact_numbers, unique_id, created_at, updated_at)
            SELECT CONCAT('Load Guest ', n),
                JSON_ARRAY(JSON_OBJECT('value', CONCAT('guest', n, '@load.gourwest.example'), 'label', 'work')),
                JSON_ARRAY(JSON_OBJECT('value', CONCAT('+6140', LPAD(n, 7, '0')), 'label', 'mobile')),
                CONCAT('{$prefix}', n), NOW(), NOW()
            FROM {$this->sequenceSql()} WHERE n <= upper";
    }

    private function profilesSql(): string
    {
        $prefix = self::UNIQUE_ID_PREFIX;
        $regions = array_values(RegionCatalog::all());
        $count = count($regions);
        $codes = $this->eltList(array_column($regions, 'code'));
        $regimes = $this->eltList(array_column($regions, 'privacy_regime'));
        $hubs = $this->eltList(array_column($regions, 'hub_name'));
        $pick = "1 + MOD(seq.n, {$count})";

        return "INSERT IGNORE INTO gourwest_customer_profiles
                (person_id, golden_record_uid, region_code, home_outlet, privacy_regime, consent_status,
                 loyalty_member_number, loyalty_tier, loyalty_points, lifetime_spend_aud, is_primary_golden,
                 last_synced_at, created_at, updated_at)
            SELECT p.id, CONCAT('GW-LOAD-', LPAD(seq.n, 9, '0')),
                ELT({$pick}, {$codes}), ELT({$pick}, {$hubs}), ELT({$pick}, {$regimes}), 'verified',
                CONCAT('GWL-', LPAD(seq.n, 9, '0')),
                CASE WHEN MOD(seq.n, 20) = 0 THEN 'Platinum' WHEN MOD(seq.n, 20) <= 3 THEN 'Gold'
                     WHEN MOD(seq.n, 20) <= 8 THEN 'Silver' ELSE 'Bronze' END,
                MOD(seq.n * 37, 6000), MOD(seq.n * 53, 900000) / 100, 1, NOW(), NOW(), NOW()
            FROM {$this->sequenceSql()}
            JOIN persons p ON p.unique_id = CONCAT('{$prefix}', seq.n)
            WHERE seq.n <= seq.upper";
    }

    /**
     * @param  list<string>  $values
     */
    private function eltList(array $values): string
    {
        $pdo = DB::connection()->getPdo();

        return implode(', ', array_map(static fn (string $value): string => $pdo->quote($value), $values));
    }
}
