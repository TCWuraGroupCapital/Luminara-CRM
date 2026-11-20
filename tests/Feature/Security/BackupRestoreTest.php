<?php

use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(DatabaseTransactions::class);

/**
 * A separate session with no database selected, so CREATE / DROP DATABASE (which commit
 * implicitly) never end the test's own transaction.
 */
function restoreTestServer(): Connection
{
    $base = config('database.connections.'.config('database.default'));

    config(['database.connections.restore_test_server' => array_merge($base, ['database' => ''])]);

    return DB::connection('restore_test_server');
}

function dropRestoreTestDatabase(string $name): void
{
    restoreTestServer()->statement("DROP DATABASE IF EXISTS `{$name}`");
    DB::purge('restore_test_server');
}

it('restores a verified backup into an empty scratch database with every row count matching', function () {
    Storage::fake('local');

    $live = DB::connection()->getDatabaseName();
    $scratch = substr($live.'_restore_'.bin2hex(random_bytes(3)), 0, 64);
    $marker = 'restore.drill.'.bin2hex(random_bytes(6));

    DB::table('core_config')->insert(['code' => $marker, 'value' => 'kept', 'created_at' => now(), 'updated_at' => now()]);

    expect(Artisan::call('luminara:backup', ['--disk' => 'local', '--path' => 'drill']))->toBe(0);

    // Give the scratch database the live schema (what --migrate does in a real drill).
    $server = restoreTestServer();
    $server->statement("CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    try {
        foreach (Schema::getTables($live) as $table) {
            $server->statement("CREATE TABLE `{$scratch}`.`{$table['name']}` LIKE `{$live}`.`{$table['name']}`");
        }

        $exit = Artisan::call('luminara:backup-restore', [
            '--database' => $scratch,
            '--disk' => 'local',
            '--path' => 'drill',
        ]);
        $output = Artisan::output();

        expect($exit)->toBe(0, $output)
            ->and($output)->toContain('every table matches the manifest');

        expect($server->table("{$scratch}.core_config")->where('code', $marker)->value('value'))->toBe('kept');

        // A second run must refuse: the scratch database is no longer empty.
        expect(Artisan::call('luminara:backup-restore', [
            '--database' => $scratch,
            '--disk' => 'local',
            '--path' => 'drill',
        ]))->toBe(1)
            ->and(Artisan::output())->toContain('already contains data');
    } finally {
        dropRestoreTestDatabase($scratch);
    }
});

it('refuses the live database, unsafe names and backups that fail verification', function () {
    Storage::fake('local');

    expect(Artisan::call('luminara:backup', ['--disk' => 'local', '--path' => 'drill']))->toBe(0);

    expect(Artisan::call('luminara:backup-restore', [
        '--database' => DB::connection()->getDatabaseName(),
        '--disk' => 'local',
        '--path' => 'drill',
    ]))->toBe(1)
        ->and(Artisan::output())->toContain('Restore into a scratch database');

    expect(Artisan::call('luminara:backup-restore', [
        '--database' => 'bad`name; DROP',
        '--disk' => 'local',
        '--path' => 'drill',
    ]))->toBe(1);

    $archive = collect(Storage::disk('local')->files('drill'))
        ->first(fn (string $file) => str_ends_with($file, '.ndjson.gz'));
    Storage::disk('local')->put($archive, 'corrupted-gzip-bytes');

    $scratch = substr(DB::connection()->getDatabaseName().'_restore_never', 0, 64);

    try {
        expect(Artisan::call('luminara:backup-restore', [
            '--database' => $scratch,
            '--disk' => 'local',
            '--path' => 'drill',
        ]))->toBe(1)
            ->and(Artisan::output())->toContain('nothing was restored');

        expect(restoreTestServer()->select('SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?', [$scratch]))->toBe([]);
    } finally {
        dropRestoreTestDatabase($scratch);
    }
});
