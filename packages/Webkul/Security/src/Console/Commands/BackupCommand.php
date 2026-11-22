<?php

namespace Webkul\Security\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use Webkul\Security\Support\BackupCipher;
use Webkul\Security\Support\BackupManifest;
use Webkul\Security\Support\SecurityAuditRecorder;

class BackupCommand extends Command
{
    protected $signature = 'luminara:backup {--disk= : Storage disk override} {--path= : Directory path override}';

    protected $description = 'Create a compressed, SHA-256 and HMAC-SHA256 signed logical database backup and prune expired archives. Encrypts with LUMINARA_BACKUP_KEY when that key is set.';

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
            $this->error('APP_KEY must be configured in production to sign backup manifests.');

            return self::FAILURE;
        }

        $effectiveKey = $signingKey !== '' ? $signingKey : 'luminara-backup-key';

        try {
            $resolvedKey = BackupCipher::configuredKey();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $rawKey = is_string($resolvedKey) ? $resolvedKey : '';
        $disk = Storage::disk($diskName);

        $timestamp = now()->utc()->format('Ymd_His');
        $manifestRelPath = "{$baseDir}/luminara_backup_{$timestamp}.manifest.json";

        $schema = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
            ? DB::connection()->getDatabaseName()
            : null;

        $tables = collect(Schema::getTables($schema))
            ->map(static fn (array $table): string => (string) $table['name'])
            ->unique()
            ->sort()
            ->values()
            ->all();

        $tempFile = tempnam(sys_get_temp_dir(), 'lum_bkp_');

        if ($tempFile === false) {
            BackupCipher::wipe($rawKey);
            throw new RuntimeException('Unable to allocate temporary file for logical backup.');
        }

        $gz = gzopen($tempFile, 'wb6');

        if ($gz === false) {
            @unlink($tempFile);
            BackupCipher::wipe($rawKey);
            throw new RuntimeException('Unable to open gzip stream for logical backup.');
        }

        $tableRowCounts = [];
        $totalRows = 0;

        try {
            foreach ($tables as $tableName) {
                $count = 0;

                foreach (DB::table($tableName)->cursor() as $row) {
                    $line = json_encode([
                        'table' => $tableName,
                        'row' => (array) $row,
                    ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)."\n";

                    if (gzwrite($gz, $line) === false) {
                        throw new RuntimeException("Unable to write a backup row for [{$tableName}].");
                    }

                    $count++;
                    $totalRows++;
                }

                $tableRowCounts[$tableName] = $count;
            }
        } catch (Throwable $e) {
            gzclose($gz);
            @unlink($tempFile);
            BackupCipher::wipe($rawKey);

            throw $e;
        }

        if (gzclose($gz) === false) {
            @unlink($tempFile);
            BackupCipher::wipe($rawKey);

            throw new RuntimeException('Unable to finish the gzip backup payload.');
        }

        $encryptedPath = null;

        try {
            $uploadPath = $tempFile;
            $version = BackupCipher::VERSION_PLAIN;
            $encryption = null;
            $archiveRelPath = "{$baseDir}/luminara_backup_{$timestamp}.ndjson.gz";

            if ($rawKey !== '') {
                $encryptedPath = $tempFile.'.enc';
                BackupCipher::encryptFile($tempFile, $encryptedPath, $rawKey);
                $keyId = BackupCipher::keyId($rawKey);
                BackupCipher::wipe($rawKey);
                $uploadPath = $encryptedPath;
                $version = BackupCipher::VERSION_ENCRYPTED;
                $archiveRelPath .= '.enc';
                $encryption = [
                    'algorithm' => BackupCipher::ALGORITHM,
                    'key_id' => $keyId,
                ];
            }

            $hashed = hash_file('sha256', $uploadPath);
            $measured = filesize($uploadPath);

            if ($hashed === false || $measured === false) {
                throw new RuntimeException('Unable to hash the backup archive.');
            }

            $sha256 = $hashed;
            $sizeBytes = $measured;

            $readStream = fopen($uploadPath, 'rb');

            if ($readStream === false) {
                throw new RuntimeException('Unable to open staged backup archive for streaming upload.');
            }

            try {
                if (! $disk->writeStream($archiveRelPath, $readStream)) {
                    throw new RuntimeException("Failed to stream backup archive to [{$archiveRelPath}].");
                }
            } finally {
                if (is_resource($readStream)) {
                    fclose($readStream);
                }
            }

            $signablePayload = BackupManifest::signable(
                $version,
                $archiveRelPath,
                $sizeBytes,
                $sha256,
                count($tableRowCounts),
                $totalRows,
                $tableRowCounts,
                $encryption
            );

            $hmac = BackupManifest::hmac($signablePayload, $effectiveKey);

            $manifest = array_merge(
                ['version' => $version, 'created_at' => now()->utc()->toIso8601String()],
                $signablePayload,
                ['hmac_sha256' => $hmac]
            );

            $disk->put($manifestRelPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } finally {
            BackupCipher::wipe($rawKey);
            @unlink($tempFile);

            if (is_string($encryptedPath)) {
                @unlink($encryptedPath);
            }
        }

        $this->pruneExpiredBackups($diskName, $baseDir);

        $audit = [
            'sha256' => $sha256,
            'total_tables' => count($tables),
            'total_rows' => $totalRows,
        ];

        if ($encryption !== null) {
            $audit['encrypted'] = true;
            $audit['key_id'] = $encryption['key_id'];
        }

        SecurityAuditRecorder::record('system.backup_created', 'backup', $archiveRelPath, $audit);

        $suffix = $encryption !== null ? ', encrypted, key id '.$encryption['key_id'] : '';
        $this->info("Backup created: {$archiveRelPath} ({$totalRows} rows across ".count($tables)." tables{$suffix}).");

        return self::SUCCESS;
    }

    private function pruneExpiredBackups(string $diskName, string $baseDir): void
    {
        $retentionDays = max(1, (int) config('security.backup_retention_days', 14));
        $cutoff = now()->subDays($retentionDays)->getTimestamp();
        $disk = Storage::disk($diskName);

        foreach ($disk->files($baseDir) as $file) {
            if (
                preg_match(BackupCipher::STORED_BASENAME, basename($file)) === 1
                && $disk->lastModified($file) < $cutoff
            ) {
                $disk->delete($file);
            }
        }
    }
}
