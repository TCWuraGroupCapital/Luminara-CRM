<?php

namespace Webkul\Security\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackupVerifyCommand extends Command
{
    protected $signature = 'luminara:backup-verify
                            {manifest? : Optional relative path to manifest JSON; defaults to latest}
                            {--disk= : Storage disk override}
                            {--path= : Directory path override}';

    protected $description = 'Verify SHA-256 digest, HMAC authenticity, gzip decompression, and per-table row counts of a logical backup.';

    public function handle(): int
    {
        $diskName = (string) ($this->option('disk') ?: config('security.backup_disk', 'local'));
        $baseDir = trim(str_replace('\\', '/', (string) ($this->option('path') ?: config('security.backup_path', 'backups'))), '/');

        if ($baseDir === '' || str_contains($baseDir, '..')) {
            $this->error('Invalid backup directory path.');

            return self::FAILURE;
        }

        $signingKey = trim((string) config('app.key', ''));

        if ($signingKey === '' && app()->isProduction()) {
            $this->error('APP_KEY must be configured in production to verify backup manifests.');

            return self::FAILURE;
        }

        $effectiveKey = $signingKey !== '' ? $signingKey : 'luminara-backup-key';
        $disk = Storage::disk($diskName);

        $manifestPath = trim(str_replace('\\', '/', (string) ($this->argument('manifest') ?? '')), '/');

        if ($manifestPath === '') {
            $manifests = collect($disk->files($baseDir))
                ->filter(static fn (string $file): bool => str_ends_with($file, '.manifest.json'))
                ->sort()
                ->values();

            if ($manifests->isEmpty()) {
                $this->error("No backup manifests found in [{$baseDir}].");

                return self::FAILURE;
            }

            $manifestPath = (string) $manifests->last();
        }

        if (str_contains($manifestPath, '..') || ! $disk->exists($manifestPath)) {
            $this->error("Manifest [{$manifestPath}] does not exist or is invalid.");

            return self::FAILURE;
        }

        $manifest = json_decode((string) $disk->get($manifestPath), true);

        if (
            ! is_array($manifest)
            || empty($manifest['archive'])
            || empty($manifest['sha256'])
            || ! is_string($manifest['hmac_sha256'] ?? null)
            || $manifest['hmac_sha256'] === ''
            || ! is_int($manifest['total_rows'] ?? null)
            || ! is_array($manifest['tables'] ?? null)
        ) {
            $this->error('Malformed or unsigned backup manifest JSON.');

            return self::FAILURE;
        }

        $archivePath = trim(str_replace('\\', '/', (string) $manifest['archive']), '/');

        if (
            str_contains($archivePath, '..')
            || ! str_starts_with($archivePath, $baseDir.'/')
            || preg_match('/^luminara_backup_\d{8}_\d{6}\.ndjson\.gz$/', basename($archivePath)) !== 1
            || ! $disk->exists($archivePath)
        ) {
            $this->error("Archive [{$archivePath}] referenced by manifest is missing or invalid.");

            return self::FAILURE;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'lum_vfy_');

        if ($tempFile === false) {
            $this->error('Unable to allocate temporary file for streaming verification.');

            return self::FAILURE;
        }

        try {
            $readStream = $disk->readStream($archivePath);
            $writeStream = fopen($tempFile, 'wb');

            if (! is_resource($readStream) || $writeStream === false) {
                if (is_resource($readStream)) {
                    fclose($readStream);
                }

                if (is_resource($writeStream)) {
                    fclose($writeStream);
                }

                $this->error('Unable to stream compressed archive from disk.');

                return self::FAILURE;
            }

            stream_copy_to_stream($readStream, $writeStream);
            fclose($readStream);
            fclose($writeStream);

            $actualSha = (string) hash_file('sha256', $tempFile);

            if (! hash_equals((string) $manifest['sha256'], $actualSha)) {
                $this->error('Backup SHA-256 digest mismatch — archive is corrupted or tampered.');

                return self::FAILURE;
            }

            $expectedTotalRows = (int) $manifest['total_rows'];
            $expectedTables = (array) $manifest['tables'];

            $signablePayload = [
                'version' => (int) ($manifest['version'] ?? 1),
                'archive' => $archivePath,
                'size_bytes' => (int) ($manifest['size_bytes'] ?? 0),
                'sha256' => $actualSha,
                'total_tables' => (int) ($manifest['total_tables'] ?? count($expectedTables)),
                'total_rows' => $expectedTotalRows,
                'tables' => $expectedTables,
            ];

            $expectedHmac = hash_hmac(
                'sha256',
                json_encode($signablePayload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                $effectiveKey
            );

            if (! hash_equals($expectedHmac, $manifest['hmac_sha256'])) {
                $this->error('Backup manifest HMAC signature mismatch.');

                return self::FAILURE;
            }

            $gz = @gzopen($tempFile, 'rb');

            if ($gz === false) {
                $this->error('Failed to open gzip backup payload.');

                return self::FAILURE;
            }

            $observedCounts = [];

            foreach (array_keys($expectedTables) as $tableName) {
                $observedCounts[(string) $tableName] = 0;
            }

            $totalVerifiedRows = 0;

            try {
                while (($rawLine = gzgets($gz)) !== false) {
                    $line = rtrim($rawLine, "\r\n");

                    if ($line === '') {
                        continue;
                    }

                    $decoded = json_decode($line, true);

                    if (! is_array($decoded) || ! isset($decoded['table']) || ! is_array($decoded['row'])) {
                        $this->error('Corrupted NDJSON record encountered during backup verification.');

                        return self::FAILURE;
                    }

                    $tbl = (string) $decoded['table'];

                    if (! array_key_exists($tbl, $observedCounts)) {
                        $this->error("Unexpected table [{$tbl}] encountered during backup verification.");

                        return self::FAILURE;
                    }

                    $observedCounts[$tbl]++;
                    $totalVerifiedRows++;
                }

                if (! gzeof($gz)) {
                    $this->error('Failed to decompress gzip backup payload.');

                    return self::FAILURE;
                }
            } finally {
                gzclose($gz);
            }
        } finally {
            @unlink($tempFile);
        }

        foreach ($manifest['tables'] as $tbl => $expectedCount) {
            if (($observedCounts[$tbl] ?? 0) !== (int) $expectedCount) {
                $this->error("Row count mismatch for table [{$tbl}]: expected {$expectedCount}, got ".($observedCounts[$tbl] ?? 0).'.');

                return self::FAILURE;
            }
        }

        if ($totalVerifiedRows !== $expectedTotalRows) {
            $this->error("Total row count mismatch: expected {$expectedTotalRows}, got {$totalVerifiedRows}.");

            return self::FAILURE;
        }

        $this->info("Backup [{$archivePath}] verified intact (SHA-256 + HMAC + {$totalVerifiedRows} rows).");

        return self::SUCCESS;
    }
}
