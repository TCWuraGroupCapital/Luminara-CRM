<?php

namespace Webkul\Security\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use Webkul\Security\Support\SecurityAuditRecorder;

/**
 * Restores a verified logical backup into an EMPTY scratch database and proves it by comparing
 * every table's row count with the signed manifest. It refuses any database that already holds
 * a single row, so it can never overwrite live data; switching production over is a separate,
 * deliberate step. The schema comes from --migrate (no tables yet) or is created beforehand.
 */
class BackupRestoreCommand extends Command
{
    private const CONNECTION = 'luminara_restore';

    private const SERVER_CONNECTION = 'luminara_restore_server';

    private const CHUNK = 500;

    protected $signature = 'luminara:backup-restore
                            {manifest? : Relative path to the manifest JSON; defaults to the latest}
                            {--database= : Empty scratch database to restore into (created if missing)}
                            {--migrate : Create the schema in the scratch database with the current migrations first}
                            {--disk= : Storage disk override}
                            {--path= : Directory path override}';

    protected $description = 'Restore a verified backup into an empty scratch database and check every table\'s row count against the manifest.';

    public function handle(): int
    {
        $target = (string) $this->option('database');

        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $target) !== 1) {
            $this->error('Pass --database=<name> (letters, digits and underscores only).');

            return self::FAILURE;
        }

        if ($target === DB::connection()->getDatabaseName()) {
            $this->error("[{$target}] is the database this command is running against. Restore into a scratch database.");

            return self::FAILURE;
        }

        $diskName = (string) ($this->option('disk') ?: config('security.backup_disk', 'local'));
        $baseDir = trim(str_replace('\\', '/', (string) ($this->option('path') ?: config('security.backup_path', 'backups'))), '/');

        $verifyArgs = array_filter([
            'manifest' => $this->argument('manifest'),
            '--disk' => $diskName,
            '--path' => $baseDir,
        ], static fn ($value): bool => $value !== null && $value !== '');

        if ($this->call('luminara:backup-verify', $verifyArgs) !== self::SUCCESS) {
            $this->error('The backup did not pass luminara:backup-verify; nothing was restored.');

            return self::FAILURE;
        }

        $disk = Storage::disk($diskName);
        $manifestPath = $this->resolveManifestPath($disk->files($baseDir));
        $manifest = json_decode((string) $disk->get($manifestPath), true);
        $expected = array_map('intval', (array) $manifest['tables']);

        try {
            $this->openConnections($target);

            $existing = collect(Schema::connection(self::CONNECTION)->getTables($target))
                ->map(static fn (array $table): string => (string) $table['name']);

            if ($existing->isNotEmpty() && $this->option('migrate')) {
                $this->error("[{$target}] already contains tables; --migrate needs an empty database.");

                return self::FAILURE;
            }

            $holdingData = $existing
                ->filter(fn (string $table): bool => DB::connection(self::CONNECTION)->table($table)->exists())
                ->values()
                ->all();

            if ($holdingData !== []) {
                $this->error("[{$target}] already contains data (".implode(', ', array_slice($holdingData, 0, 5)).'). Restore only into an empty database.');

                return self::FAILURE;
            }

            if ($this->option('migrate')) {
                $this->info("Creating the schema in [{$target}] ...");

                if ($this->callSilently('migrate', ['--database' => self::CONNECTION, '--force' => true]) !== self::SUCCESS) {
                    $this->error('Migrations failed in the scratch database.');

                    return self::FAILURE;
                }
            }

            $present = collect(Schema::connection(self::CONNECTION)->getTables($target))
                ->map(static fn (array $table): string => (string) $table['name'])
                ->all();

            $missing = array_values(array_diff(array_keys($expected), $present));

            if ($missing !== []) {
                $this->error('The scratch database has no table for: '.implode(', ', $missing).'. Use --migrate, or create the schema first.');

                return self::FAILURE;
            }

            $this->loadRows($disk->readStream((string) $manifest['archive']), array_keys($expected));

            $mismatches = [];

            foreach ($expected as $table => $count) {
                $actual = DB::connection(self::CONNECTION)->table($table)->count();

                if ($actual !== $count) {
                    $mismatches[] = [$table, $count, $actual];
                }
            }
        } catch (Throwable $e) {
            $this->error('Restore failed: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            DB::purge(self::CONNECTION);
            DB::purge(self::SERVER_CONNECTION);
        }

        if ($mismatches !== []) {
            $this->error('Row counts differ from the manifest:');
            $this->table(['Table', 'Manifest', 'Restored'], $mismatches);

            return self::FAILURE;
        }

        SecurityAuditRecorder::record('system.backup_restored', 'backup', (string) $manifest['archive'], [
            'database' => $target,
            'total_tables' => count($expected),
            'total_rows' => array_sum($expected),
        ]);

        $this->info(sprintf(
            'Restored %d rows across %d tables into [%s]; every table matches the manifest.',
            array_sum($expected),
            count($expected),
            $target
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $files
     */
    private function resolveManifestPath(array $files): string
    {
        $requested = trim(str_replace('\\', '/', (string) ($this->argument('manifest') ?? '')), '/');

        if ($requested !== '') {
            return $requested;
        }

        return (string) collect($files)
            ->filter(static fn (string $file): bool => str_ends_with($file, '.manifest.json'))
            ->sort()
            ->last();
    }

    /**
     * Two extra connections cloned from the default one: a server-level session (no database
     * selected) that creates the scratch database, so the statement's implicit commit never
     * touches the default connection, and a session bound to the scratch database itself.
     */
    private function openConnections(string $target): void
    {
        $base = config('database.connections.'.config('database.default'));

        if (! is_array($base) || ! in_array($base['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Restore supports MySQL and MariaDB only.');
        }

        config([
            'database.connections.'.self::SERVER_CONNECTION => array_merge($base, ['database' => '']),
            'database.connections.'.self::CONNECTION => array_merge($base, ['database' => $target]),
        ]);

        DB::purge(self::SERVER_CONNECTION);
        DB::purge(self::CONNECTION);

        DB::connection(self::SERVER_CONNECTION)
            ->statement("CREATE DATABASE IF NOT EXISTS `{$target}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    /**
     * Empties each backed-up table (migrations may have inserted defaults), then streams the
     * archive back in chunks with foreign-key checks off, since rows arrive table by table.
     *
     * @param  resource|null  $stream
     * @param  array<int, string>  $tables
     */
    private function loadRows($stream, array $tables): void
    {
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to read the backup archive.');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'lum_rst_');

        if ($tempFile === false) {
            throw new RuntimeException('Unable to allocate a temporary file.');
        }

        $connection = DB::connection(self::CONNECTION);
        $schema = Schema::connection(self::CONNECTION);

        try {
            $out = fopen($tempFile, 'wb');
            stream_copy_to_stream($stream, $out);
            fclose($out);
            fclose($stream);

            $schema->disableForeignKeyConstraints();

            foreach ($tables as $table) {
                $connection->table($table)->truncate();
            }

            $gz = gzopen($tempFile, 'rb');

            if ($gz === false) {
                throw new RuntimeException('Unable to open the gzip archive.');
            }

            $buffer = [];

            while (($line = gzgets($gz)) !== false) {
                $record = json_decode(rtrim($line, "\r\n"), true);

                if (! is_array($record) || ! isset($record['table'], $record['row'])) {
                    continue;
                }

                $table = (string) $record['table'];
                $buffer[$table][] = (array) $record['row'];

                if (count($buffer[$table]) >= self::CHUNK) {
                    $connection->table($table)->insert($buffer[$table]);
                    $buffer[$table] = [];
                }
            }

            gzclose($gz);

            foreach ($buffer as $table => $rows) {
                if ($rows !== []) {
                    $connection->table($table)->insert($rows);
                }
            }
        } finally {
            $schema->enableForeignKeyConstraints();
            @unlink($tempFile);
        }
    }
}
