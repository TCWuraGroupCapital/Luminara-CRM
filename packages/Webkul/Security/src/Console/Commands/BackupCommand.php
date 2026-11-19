<?php

namespace Webkul\Security\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Webkul\Security\Support\SecurityAuditRecorder;

class BackupCommand extends Command
{
    protected $signature = 'luminara:backup {--disk= : Storage disk override} {--path= : Directory path override}';

    protected $description = 'Create a compressed, SHA-256 and HMAC-SHA256 signed logical database backup and prune expired archives.';

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
        $disk = Storage::disk($diskName);

        $timestamp = now()->utc()->format('Ymd_His');
        $archiveRelPath = "{$baseDir}/luminara_backup_{$timestamp}.ndjson.gz";
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
            throw new RuntimeException('Unable to allocate temporary file for logical backup.');
        }

        $gz = gzopen($tempFile, 'wb6');

        if ($gz === false) {
            @unlink($tempFile);
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

                    gzwrite($gz, $line);
                    $count++;
                    $totalRows++;
                }

                $tableRowCounts[$tableName] = $count;
            }
        } catch (\Throwable $e) {
            gzclose($gz);
            @unlink($tempFile);

            throw $e;
        }

        gzclose($gz);

        try {
            $sha256 = (string) hash_file('sha256', $tempFile);
            $sizeBytes = (int) filesize($tempFile);

            $readStream = fopen($tempFile, 'rb');

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
        } finally {
            @unlink($tempFile);
        }

        $signablePayload = [
            'version' => 1,
            'archive' => $archiveRelPath,
            'size_bytes' => $sizeBytes,
            'sha256' => $sha256,
            'total_tables' => count($tables),
            'total_rows' => $totalRows,
            'tables' => $tableRowCounts,
        ];

        $hmac = hash_hmac(
            'sha256',
            json_encode($signablePayload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $effectiveKey
        );

        $manifest = array_merge(
            ['version' => 1, 'created_at' => now()->utc()->toIso8601String()],
            $signablePayload,
            ['hmac_sha256' => $hmac]
        );

        $disk->put($manifestRelPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $this->pruneExpiredBackups($diskName, $baseDir);

        SecurityAuditRecorder::record('system.backup_created', 'backup', $archiveRelPath, [
            'sha256' => $sha256,
            'total_tables' => count($tables),
            'total_rows' => $totalRows,
        ]);

        $this->info("Backup created: {$archiveRelPath} ({$totalRows} rows across ".count($tables).' tables).');

        return self::SUCCESS;
    }

    private function pruneExpiredBackups(string $diskName, string $baseDir): void
    {
        $retentionDays = max(1, (int) config('security.backup_retention_days', 14));
        $cutoff = now()->subDays($retentionDays)->getTimestamp();
        $disk = Storage::disk($diskName);

        foreach ($disk->files($baseDir) as $file) {
            if (
                preg_match('/^luminara_backup_\d{8}_\d{6}\.(ndjson\.gz|manifest\.json)$/', basename($file)) === 1
                && $disk->lastModified($file) < $cutoff
            ) {
                $disk->delete($file);
            }
        }
    }
}
