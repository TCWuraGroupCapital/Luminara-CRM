<?php

namespace Webkul\Security\Support;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Age of the newest successful logical backup, taken from the manifest file name.
 *
 * The manifest body is not read, so the HMAC and table listing never enter the health report.
 */
class BackupAge
{
    /**
     * @return array{age_seconds: int|null, completed_at: string|null, error: string|null}
     */
    public function latest(): array
    {
        $missing = [
            'age_seconds' => null,
            'completed_at' => null,
            'error' => 'missing',
        ];

        try {
            $diskName = (string) config('security.backup_disk', 'local');
            $baseDir = trim(str_replace('\\', '/', (string) config('security.backup_path', 'backups')), '/');

            if ($baseDir === '' || str_contains($baseDir, '..')) {
                return [
                    'age_seconds' => null,
                    'completed_at' => null,
                    'error' => 'unavailable',
                ];
            }

            $latest = null;

            foreach (Storage::disk($diskName)->files($baseDir) as $file) {
                if (preg_match('/^luminara_backup_(\d{8})_(\d{6})\.manifest\.json$/', basename((string) $file), $match) !== 1) {
                    continue;
                }

                $parsed = DateTimeImmutable::createFromFormat(
                    '!YmdHis',
                    $match[1].$match[2],
                    new DateTimeZone('UTC')
                );

                if ($parsed === false) {
                    continue;
                }

                $timestamp = $parsed->getTimestamp();

                if ($latest === null || $timestamp > $latest) {
                    $latest = $timestamp;
                }
            }

            if ($latest === null) {
                return $missing;
            }

            return [
                'age_seconds' => max(0, time() - $latest),
                'completed_at' => gmdate('Y-m-d\TH:i:s\Z', $latest),
                'error' => null,
            ];
        } catch (Throwable) {
            return [
                'age_seconds' => null,
                'completed_at' => null,
                'error' => 'unavailable',
            ];
        }
    }
}
