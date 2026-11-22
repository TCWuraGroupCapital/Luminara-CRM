<?php

namespace Webkul\Security\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Webkul\Security\Support\BackupAge;

class OpsAlertCommand extends Command
{
    public const FAILED_JOBS_KEY = 'luminara:ops:failed_jobs_count';

    public const BACKUP_ALERT_KEY = 'luminara:ops:backup_alerted';

    protected $signature = 'luminara:ops-alert
                            {--json : Print the alerts as JSON}';

    protected $description = 'Log a critical alert when failed jobs grow or the newest backup is too old.';

    public function handle(BackupAge $backups): int
    {
        $alerts = [];

        $failedJobs = $this->failedJobsAlert();

        if ($failedJobs !== null) {
            $alerts[] = $failedJobs;
        }

        $backup = $this->backupAlert($backups);

        if ($backup !== null) {
            $alerts[] = $backup;
        }

        if ($alerts !== []) {
            Log::critical('luminara.ops.alert', ['alerts' => $alerts]);
            $this->notify($alerts);
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(['alerts' => $alerts], JSON_UNESCAPED_SLASHES));
        } elseif ($alerts === []) {
            $this->info('No operational alerts.');
        } else {
            $this->warn('Operational alerts: '.count($alerts).'.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function failedJobsAlert(): ?array
    {
        try {
            if (! Schema::hasTable('failed_jobs')) {
                return null;
            }

            $current = (int) DB::table('failed_jobs')->count();
        } catch (Throwable) {
            Log::warning('luminara.ops.failed_jobs_unreadable', [
                'exception' => 'unavailable',
            ]);

            return null;
        }

        $previous = Cache::get(self::FAILED_JOBS_KEY);
        Cache::forever(self::FAILED_JOBS_KEY, $current);

        if (! is_numeric($previous)) {
            return null;
        }

        $growth = $current - (int) $previous;

        if ($growth <= 0) {
            return null;
        }

        return [
            'code' => 'failed_jobs_grew',
            'failed_jobs' => $current,
            'growth' => $growth,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function backupAlert(BackupAge $backups): ?array
    {
        $maxAge = max(1, (int) config('security.health_backup_max_age_seconds', 93600));
        $latest = $backups->latest();
        $stale = false;
        $alert = null;

        if ($latest['error'] === 'missing') {
            $installedAge = $this->installationAgeSeconds();

            if ($installedAge !== null && $installedAge > $maxAge) {
                $stale = true;
                $alert = [
                    'code' => 'backup_missing',
                    'age_seconds' => null,
                    'completed_at' => null,
                ];
            }
        } elseif ($latest['error'] === null && $latest['age_seconds'] !== null && $latest['age_seconds'] > $maxAge) {
            $stale = true;
            $alert = [
                'code' => 'backup_stale',
                'age_seconds' => $latest['age_seconds'],
                'completed_at' => $latest['completed_at'],
            ];
        } elseif ($latest['error'] === 'unavailable') {
            $stale = true;
            $alert = [
                'code' => 'backup_unreadable',
                'age_seconds' => null,
                'completed_at' => null,
            ];
        }

        if (! $stale || $alert === null) {
            Cache::forget(self::BACKUP_ALERT_KEY);

            return null;
        }

        if (Cache::has(self::BACKUP_ALERT_KEY)) {
            return null;
        }

        $cooldown = max(60, (int) config('security.alert_backup_cooldown_seconds', 21600));
        Cache::put(self::BACKUP_ALERT_KEY, 1, $cooldown);

        return $alert;
    }

    private function installationAgeSeconds(): ?int
    {
        $path = storage_path('installed');

        if (! is_file($path)) {
            return null;
        }

        $mtime = filemtime($path);

        if ($mtime === false) {
            return null;
        }

        return max(0, time() - $mtime);
    }

    /**
     * @param  list<array<string, mixed>>  $alerts
     */
    private function notify(array $alerts): void
    {
        $lines = ['Luminara CRM operational alert.'];

        foreach ($alerts as $alert) {
            $code = (string) ($alert['code'] ?? 'unknown');

            if ($code === 'failed_jobs_grew') {
                $lines[] = 'Failed jobs grew by '.(int) ($alert['growth'] ?? 0).'. The queue now has '.(int) ($alert['failed_jobs'] ?? 0).' failed job(s). Job payloads are not included.';
            } elseif ($code === 'backup_stale') {
                $lines[] = 'The newest successful backup is '.(int) ($alert['age_seconds'] ?? 0).' seconds old.';
            } elseif ($code === 'backup_missing') {
                $lines[] = 'No successful backup manifest was found.';
            } else {
                $lines[] = 'Backup status could not be read.';
            }
        }

        $text = implode("\n", $lines);
        $this->sendMail($text);
        $this->sendSlack($text);
    }

    private function sendMail(string $text): void
    {
        $email = trim((string) config('security.alert_email', ''));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            if ($email !== '') {
                Log::warning('luminara.ops.alert_email_ignored');
            }

            return;
        }

        try {
            Mail::raw($text, function (Message $message) use ($email): void {
                $message->to($email)->subject('Luminara CRM operational alert');
            });
        } catch (Throwable $e) {
            Log::error('luminara.ops.mail_failed', [
                'exception' => $e::class,
            ]);
        }
    }

    private function sendSlack(string $text): void
    {
        $url = trim((string) config('security.alert_slack_webhook', ''));

        if ($url === '') {
            return;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false || ! str_starts_with($url, 'https://')) {
            Log::warning('luminara.ops.slack_webhook_ignored');

            return;
        }

        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->asJson()
                ->post($url, ['text' => $text]);

            if (! $response->successful()) {
                Log::error('luminara.ops.slack_delivery_failed', [
                    'status' => $response->status(),
                ]);
            }
        } catch (Throwable $e) {
            Log::error('luminara.ops.slack_delivery_failed', [
                'exception' => $e::class,
            ]);
        }
    }
}
