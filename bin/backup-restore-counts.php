<?php

/**
 * Row-count helper for the encrypted backup drill.
 *
 *   php bin/backup-restore-counts.php database
 *   php bin/backup-restore-counts.php capture /tmp/counts.json
 *   RESTORE_DATABASE=name php bin/backup-restore-counts.php diff /tmp/counts.json
 *   RESTORE_DATABASE=name php bin/backup-restore-counts.php drop
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$command = $argv[1] ?? '';
$driver = (string) DB::connection()->getDriverName();

if (! in_array($driver, ['mysql', 'mariadb'], true)) {
    fwrite(STDERR, "The backup drill supports MySQL and MariaDB only.\n");
    exit(1);
}

$source = sourceDatabase();

match ($command) {
    'database' => printDatabase($source),
    'capture' => capture($source, $argv[2] ?? ''),
    'diff' => diff($source, $argv[2] ?? ''),
    'drop' => dropDatabase($source),
    default => usage(),
};

function printDatabase(string $source): void
{
    fwrite(STDOUT, $source."\n");
}

function capture(string $source, string $path): void
{
    if ($path === '') {
        usage();
    }

    $counts = tableCounts($source);
    $json = json_encode($counts, JSON_THROW_ON_ERROR);
    if (file_put_contents($path, $json) === false) {
        fwrite(STDERR, "Unable to write [{$path}].\n");
        exit(1);
    }

    fwrite(STDERR, 'captured '.count($counts)." tables from {$source}\n");
}

function diff(string $source, string $path): void
{
    if ($path === '' || ! is_file($path)) {
        fwrite(STDERR, "Capture file is missing.\n");
        exit(1);
    }

    $captured = json_decode((string) file_get_contents($path), true);

    if (! is_array($captured)) {
        fwrite(STDERR, "Capture file is not a JSON object.\n");
        exit(1);
    }

    $target = restoreDatabase($source);
    $restored = tableCounts($target);
    $names = array_unique(array_merge(array_keys($captured), array_keys($restored)));
    sort($names);

    $failed = false;

    foreach ($names as $name) {
        $before = array_key_exists($name, $captured) ? (int) $captured[$name] : null;
        $after = array_key_exists($name, $restored) ? $restored[$name] : null;

        if ($before !== $after) {
            $left = $before === null ? 'missing' : (string) $before;
            $right = $after === null ? 'missing' : (string) $after;
            fwrite(STDERR, "row count mismatch for {$name}: source {$left}, restored {$right}\n");
            $failed = true;
        }
    }

    if ($failed) {
        exit(1);
    }

    fwrite(STDOUT, 'row counts match across '.count($names)." tables\n");
}

function dropDatabase(string $source): void
{
    $target = restoreDatabase($source);

    if ($target === $source) {
        fwrite(STDERR, "Refusing to drop the source database.\n");
        exit(1);
    }

    DB::statement("DROP DATABASE IF EXISTS `{$target}`");
    fwrite(STDERR, "dropped {$target}\n");
}

function sourceDatabase(): string
{
    $default = (string) config('database.default');
    $name = (string) config("database.connections.{$default}.database");

    return requireIdentifier($name, 'Source database');
}

function restoreDatabase(string $source): string
{
    $name = getenv('RESTORE_DATABASE');

    if (! is_string($name) || $name === '') {
        fwrite(STDERR, "RESTORE_DATABASE is not set.\n");
        exit(1);
    }

    $name = requireIdentifier($name, 'RESTORE_DATABASE');

    if ($name === $source) {
        fwrite(STDERR, "RESTORE_DATABASE must be a different database from the source.\n");
        exit(1);
    }

    return $name;
}

/**
 * @return array<string, int>
 */
function tableCounts(string $schema): array
{
    $rows = DB::select(
        'SELECT TABLE_NAME AS table_name FROM information_schema.tables WHERE table_schema = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
        [$schema, 'BASE TABLE']
    );

    $counts = [];

    foreach ($rows as $row) {
        $name = column($row, 'table_name');
        $name = requireIdentifier($name, 'Table');
        $count = DB::selectOne('SELECT COUNT(*) AS c FROM `'.$schema.'`.`'.$name.'`');
        $counts[$name] = (int) column($count, 'c');
    }

    return $counts;
}

function column(object $row, string $name): string
{
    foreach (get_object_vars($row) as $key => $value) {
        if (strcasecmp((string) $key, $name) === 0) {
            return (string) $value;
        }
    }

    fwrite(STDERR, "Query result is missing [{$name}].\n");
    exit(1);
}

function requireIdentifier(string $name, string $label): string
{
    if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $name) !== 1) {
        fwrite(STDERR, "{$label} [{$name}] is not a safe identifier.\n");
        exit(1);
    }

    return $name;
}

function usage(): void
{
    fwrite(STDERR, "Usage: backup-restore-counts.php database|capture <file>|diff <file>|drop\n");
    exit(1);
}
