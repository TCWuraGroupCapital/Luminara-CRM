<?php

namespace Webkul\Security\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Dependency checks for GET /health/ready.
 *
 * Critical failures answer 503. Backup age and ledger seal age are reported
 * and stay non-critical unless the operator opts in, so a quiet install is
 * not taken out of service before the first nightly backup.
 */
class ReadinessProbe
{
    public function __construct(
        private readonly QueueHeartbeat $heartbeat,
        private readonly BackupAge $backups,
    ) {}

    /**
     * @return array{status: string, checks: array<string, array<string, mixed>>}
     */
    public function run(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'backup' => $this->checkBackup(),
        ];

        $ledger = $this->checkLedger();

        if ($ledger !== null) {
            $checks['ledger_seal'] = $ledger;
        }

        $failedCritical = false;
        $failedOptional = false;

        foreach ($checks as $check) {
            if (($check['status'] ?? '') !== 'fail') {
                continue;
            }

            if (! empty($check['critical'])) {
                $failedCritical = true;
            } else {
                $failedOptional = true;
            }
        }

        $status = 'ok';

        if ($failedCritical) {
            $status = 'fail';
        } elseif ($failedOptional) {
            $status = 'degraded';
        }

        return [
            'status' => $status,
            'checks' => $checks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function checkDatabase(): array
    {
        $started = hrtime(true);

        try {
            $this->pingDatabase();

            return [
                'status' => 'ok',
                'critical' => true,
                'latency_ms' => $this->latency($started),
            ];
        } catch (Throwable $e) {
            $this->logFailure('database', $e);

            return [
                'status' => 'fail',
                'critical' => true,
                'error' => 'unavailable',
                'latency_ms' => $this->latency($started),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function checkRedis(): array
    {
        $started = hrtime(true);

        try {
            if (! $this->pingRedis()) {
                return [
                    'status' => 'fail',
                    'critical' => true,
                    'error' => 'ping_failed',
                    'latency_ms' => $this->latency($started),
                ];
            }

            return [
                'status' => 'ok',
                'critical' => true,
                'latency_ms' => $this->latency($started),
            ];
        } catch (Throwable $e) {
            $this->logFailure('redis', $e);

            return [
                'status' => 'fail',
                'critical' => true,
                'error' => 'unavailable',
                'latency_ms' => $this->latency($started),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function checkQueue(): array
    {
        $maxAge = max(1, (int) config('security.health_queue_max_age_seconds', 180));
        $stamp = $this->heartbeat->read();
        $critical = true;

        if ($stamp === null) {
            if ($this->withinStartGrace()) {
                return [
                    'status' => 'starting',
                    'critical' => $critical,
                    'age_seconds' => null,
                    'heartbeat_at' => null,
                ];
            }

            return [
                'status' => 'fail',
                'critical' => $critical,
                'error' => 'missing',
                'age_seconds' => null,
                'heartbeat_at' => null,
            ];
        }

        $parsed = strtotime($stamp);
        $age = $parsed === false ? null : max(0, time() - $parsed);

        if ($age !== null && $age <= $maxAge) {
            return [
                'status' => 'ok',
                'critical' => $critical,
                'age_seconds' => $age,
                'heartbeat_at' => $stamp,
            ];
        }

        if ($this->withinStartGrace()) {
            return [
                'status' => 'starting',
                'critical' => $critical,
                'age_seconds' => $age,
                'heartbeat_at' => $stamp,
            ];
        }

        return [
            'status' => 'fail',
            'critical' => $critical,
            'error' => 'stale',
            'age_seconds' => $age,
            'heartbeat_at' => $stamp,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function checkBackup(): array
    {
        $maxAge = max(1, (int) config('security.health_backup_max_age_seconds', 93600));
        $critical = filter_var(config('security.health_backup_critical', false), FILTER_VALIDATE_BOOL);
        $latest = $this->backups->latest();

        $report = [
            'status' => 'ok',
            'critical' => $critical,
            'age_seconds' => $latest['age_seconds'],
            'completed_at' => $latest['completed_at'],
        ];

        if ($latest['error'] !== null) {
            $report['status'] = 'fail';
            $report['error'] = $latest['error'];

            return $report;
        }

        if ($latest['age_seconds'] !== null && $latest['age_seconds'] > $maxAge) {
            $report['status'] = 'fail';
            $report['error'] = 'stale';
        }

        return $report;
    }

    /**
     * Null when the ledger package has no epochs table, so the feature is absent.
     *
     * A quiet ledger (nothing new to seal) stays ok even if the last seal is old.
     * The check fails when an unsealed block has been waiting longer than the max age.
     *
     * @return array<string, mixed>|null
     */
    public function checkLedger(): ?array
    {
        if (! $this->ledgerFeatureExists()) {
            return null;
        }

        $maxAge = max(1, (int) config('security.health_ledger_max_age_seconds', 7200));
        $critical = filter_var(config('security.health_ledger_critical', false), FILTER_VALIDATE_BOOL);

        try {
            $latest = DB::table('ledger_epochs')->max('sealed_at');
            $sealedAt = null;
            $age = null;

            if (is_string($latest) && $latest !== '') {
                $parsed = strtotime($latest);

                if ($parsed === false) {
                    return [
                        'status' => 'fail',
                        'critical' => $critical,
                        'error' => 'unavailable',
                        'age_seconds' => null,
                        'sealed_at' => null,
                    ];
                }

                $age = max(0, time() - $parsed);
                $sealedAt = gmdate('Y-m-d\TH:i:s\Z', $parsed);
            }

            $pending = false;

            if (Schema::hasTable('data_blocks')) {
                $cutoff = gmdate('Y-m-d H:i:s', time() - $maxAge);
                $pending = DB::table('data_blocks')
                    ->whereNull('epoch_id')
                    ->where('created_at', '<=', $cutoff)
                    ->exists();
            }

            $report = [
                'status' => $pending ? 'fail' : 'ok',
                'critical' => $critical,
                'age_seconds' => $age,
                'sealed_at' => $sealedAt,
            ];

            if ($pending) {
                $report['error'] = 'stale';
            }

            return $report;
        } catch (Throwable $e) {
            $this->logFailure('ledger_seal', $e);

            return [
                'status' => 'fail',
                'critical' => $critical,
                'error' => 'unavailable',
                'age_seconds' => null,
                'sealed_at' => null,
            ];
        }
    }

    public static function acceptsPong(mixed $pong): bool
    {
        if ($pong === true || $pong === 1) {
            return true;
        }

        if (is_object($pong) && method_exists($pong, '__toString')) {
            $pong = (string) $pong;
        }

        if (! is_string($pong)) {
            return false;
        }

        return strtoupper(trim($pong, " +\t\r\n")) === 'PONG';
    }

    protected function pingDatabase(): void
    {
        DB::select('SELECT 1');
    }

    protected function pingRedis(): bool
    {
        return self::acceptsPong(Redis::connection()->command('ping'));
    }

    protected function ledgerFeatureExists(): bool
    {
        try {
            return Schema::hasTable('ledger_epochs');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Missing or stale heartbeats are not failures during the container's start grace.
     *
     * The grace file lives on the container filesystem (not the shared storage volume)
     * so a restart can become ready before the scheduler, which waits for this process,
     * has written its first heartbeat.
     */
    protected function withinStartGrace(): bool
    {
        $grace = max(0, (int) config('security.health_queue_start_grace_seconds', 180));
        $path = (string) config('security.health_boot_path', '/tmp/luminara-health-boot');
        $now = time();

        if ($path === '') {
            return false;
        }

        if (! is_file($path)) {
            @file_put_contents($path, (string) $now);

            return true;
        }

        $started = (int) @file_get_contents($path);

        if ($started <= 0) {
            return true;
        }

        return ($now - $started) < $grace;
    }

    private function latency(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }

    private function logFailure(string $check, Throwable $e): void
    {
        Log::warning('luminara.health.check_failed', [
            'check' => $check,
            'exception' => $e::class,
        ]);
    }
}
