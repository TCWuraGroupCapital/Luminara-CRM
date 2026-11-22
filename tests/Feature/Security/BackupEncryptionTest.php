<?php

use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Webkul\Security\Support\BackupCipher;

uses(DatabaseTransactions::class);

function backupEncryptionServer(): Connection
{
    $base = config('database.connections.'.config('database.default'));

    config(['database.connections.backup_encryption_server' => array_merge($base, ['database' => ''])]);

    return DB::connection('backup_encryption_server');
}

function dropBackupEncryptionDatabase(string $name): void
{
    backupEncryptionServer()->statement("DROP DATABASE IF EXISTS `{$name}`");
    DB::purge('backup_encryption_server');
}

function backupEncryptionKey(): string
{
    return sodium_bin2base64(
        random_bytes(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES),
        SODIUM_BASE64_VARIANT_ORIGINAL
    );
}

/**
 * @param  callable(): mixed  $callback
 */
function withBackupKey(string $key, callable $callback): mixed
{
    $previous = config('security.backup_key');
    config(['security.backup_key' => $key]);

    try {
        return $callback();
    } finally {
        config(['security.backup_key' => $previous]);
    }
}

function scratchBackupDatabase(string $suffix): string
{
    return substr(DB::connection()->getDatabaseName().'_'.$suffix.'_'.bin2hex(random_bytes(3)), 0, 64);
}

it('prints a backup key and the key id of the configured key', function () {
    expect(Artisan::call('luminara:backup-key'))->toBe(0);

    $output = Artisan::output();

    expect($output)->toMatch('/^LUMINARA_BACKUP_KEY=\S+$/m')
        ->and($output)->toMatch('/^key_id=[a-f0-9]{64}$/m');

    preg_match('/^LUMINARA_BACKUP_KEY=(.+)$/m', $output, $key);
    preg_match('/^key_id=([a-f0-9]{64})$/m', $output, $id);

    $raw = BackupCipher::parseKey($key[1]);

    expect(BackupCipher::keyId($raw))->toBe($id[1]);
    BackupCipher::wipe($raw);

    $encoded = backupEncryptionKey();

    withBackupKey($encoded, function () use ($encoded) {
        expect(Artisan::call('luminara:backup-key', ['--show' => true]))->toBe(0);
        $shown = Artisan::output();

        expect($shown)->toContain('key_id='.BackupCipher::keyId(BackupCipher::parseKey($encoded)))
            ->and($shown)->not->toContain($encoded);
    });

    config(['security.backup_key' => '']);

    expect(Artisan::call('luminara:backup-key', ['--show' => true]))->toBe(1)
        ->and(Artisan::output())->toContain('LUMINARA_BACKUP_KEY is not set');
});

it('refuses a backup key that is APP_KEY or not a 32 byte key', function () {
    Storage::fake('local');

    $appKey = (string) config('app.key');
    expect($appKey)->not->toBe('');

    withBackupKey($appKey, function () {
        expect(Artisan::call('luminara:backup', ['--disk' => 'local', '--path' => 'enc']))->toBe(1)
            ->and(Artisan::output())->toContain('must not be the same key as APP_KEY');
    });

    withBackupKey('not-a-key', function () {
        expect(Artisan::call('luminara:backup', ['--disk' => 'local', '--path' => 'enc']))->toBe(1)
            ->and(Artisan::output())->toContain('not valid base64');
    });
});

it('encrypts a backup, verifies it, and restores every row into an empty database', function () {
    Storage::fake('local');

    $encoded = backupEncryptionKey();
    $raw = BackupCipher::parseKey($encoded);
    $keyId = BackupCipher::keyId($raw);
    $marker = 'enc.drill.'.bin2hex(random_bytes(16));
    $live = DB::connection()->getDatabaseName();
    $scratch = scratchBackupDatabase('enc');

    DB::table('core_config')->insert([
        'code' => $marker,
        'value' => $marker,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    try {
        withBackupKey($encoded, function () use ($keyId, $marker, $live, $scratch, $encoded) {
            expect(Artisan::call('luminara:backup', ['--disk' => 'local', '--path' => 'enc']))->toBe(0);
            expect(Artisan::output())->toContain('encrypted, key id '.$keyId);

            $files = Storage::disk('local')->files('enc');
            $archive = collect($files)->first(fn (string $file) => str_ends_with($file, '.ndjson.gz.enc'));
            $manifestFile = collect($files)->first(fn (string $file) => str_ends_with($file, '.manifest.json'));
            $signedManifest = Storage::disk('local')->get($manifestFile);

            expect($archive)->not->toBeNull()
                ->and(collect($files)->contains(fn (string $file) => str_ends_with($file, '.ndjson.gz')))->toBeFalse();

            $bytes = Storage::disk('local')->get($archive);

            expect($bytes)->toStartWith(BackupCipher::MAGIC)
                ->and(substr($bytes, 0, 2))->not->toBe("\x1f\x8b")
                ->and($bytes)->not->toContain($marker);

            $manifest = json_decode($signedManifest, true);

            expect($manifest['version'])->toBe(BackupCipher::VERSION_ENCRYPTED)
                ->and($manifest['encryption']['algorithm'])->toBe(BackupCipher::ALGORITHM)
                ->and($manifest['encryption']['key_id'])->toBe($keyId)
                ->and($manifest['archive'])->toBe($archive);

            expect(Artisan::call('luminara:backup-verify', ['--disk' => 'local', '--path' => 'enc']))->toBe(0)
                ->and(Artisan::output())->toContain('secretstream');

            $manifest['encryption']['key_id'] = str_repeat('ab', 32);
            Storage::disk('local')->put($manifestFile, json_encode($manifest));

            expect(Artisan::call('luminara:backup-verify', ['--disk' => 'local', '--path' => 'enc']))->toBe(1)
                ->and(Artisan::output())->toContain('HMAC');

            Storage::disk('local')->put($manifestFile, $signedManifest);

            $server = backupEncryptionServer();
            $server->statement("CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            foreach (Schema::getTables($live) as $table) {
                $server->statement("CREATE TABLE `{$scratch}`.`{$table['name']}` LIKE `{$live}`.`{$table['name']}`");
            }

            $exit = Artisan::call('luminara:backup-restore', [
                '--database' => $scratch,
                '--disk' => 'local',
                '--path' => 'enc',
            ]);
            $output = Artisan::output();

            expect($exit)->toBe(0, $output)
                ->and($output)->toContain('every table matches the manifest');

            expect($server->table("{$scratch}.core_config")->where('code', $marker)->value('value'))->toBe($marker);

            config(['security.backup_key' => backupEncryptionKey()]);

            expect(Artisan::call('luminara:backup-verify', ['--disk' => 'local', '--path' => 'enc']))->toBe(1)
                ->and(Artisan::output())->toContain('but this backup was encrypted with key id '.$keyId);

            config(['security.backup_key' => '']);

            expect(Artisan::call('luminara:backup-verify', ['--disk' => 'local', '--path' => 'enc']))->toBe(1)
                ->and(Artisan::output())->toContain('LUMINARA_BACKUP_KEY is not set');

            config(['security.backup_key' => $encoded]);

            Storage::disk('local')->put($archive, str_repeat('x', 64));

            expect(Artisan::call('luminara:backup-verify', ['--disk' => 'local', '--path' => 'enc']))->toBe(1)
                ->and(Artisan::output())->toContain('SHA-256');
        });
    } finally {
        BackupCipher::wipe($raw);
        dropBackupEncryptionDatabase($scratch);
    }
});

it('still verifies and restores a legacy plain archive when a backup key is configured', function () {
    Storage::fake('local');

    $marker = 'plain.drill.'.bin2hex(random_bytes(8));
    $live = DB::connection()->getDatabaseName();
    $scratch = scratchBackupDatabase('plain');

    DB::table('core_config')->insert([
        'code' => $marker,
        'value' => 'kept',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    config(['security.backup_key' => '']);

    expect(Artisan::call('luminara:backup', ['--disk' => 'local', '--path' => 'plain']))->toBe(0);

    $files = Storage::disk('local')->files('plain');
    $archive = collect($files)->first(fn (string $file) => str_ends_with($file, '.ndjson.gz'));
    $manifest = json_decode(Storage::disk('local')->get(
        collect($files)->first(fn (string $file) => str_ends_with($file, '.manifest.json'))
    ), true);

    expect($archive)->toEndWith('.ndjson.gz')
        ->and($manifest['version'])->toBe(BackupCipher::VERSION_PLAIN)
        ->and(array_key_exists('encryption', $manifest))->toBeFalse();

    $server = backupEncryptionServer();
    $server->statement("CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    try {
        foreach (Schema::getTables($live) as $table) {
            $server->statement("CREATE TABLE `{$scratch}`.`{$table['name']}` LIKE `{$live}`.`{$table['name']}`");
        }

        withBackupKey(backupEncryptionKey(), function () use ($scratch, $server, $marker) {
            expect(Artisan::call('luminara:backup-verify', ['--disk' => 'local', '--path' => 'plain']))->toBe(0)
                ->and(Artisan::output())->toContain('SHA-256 + HMAC +')
                ->and(Artisan::output())->not->toContain('secretstream');

            $exit = Artisan::call('luminara:backup-restore', [
                '--database' => $scratch,
                '--disk' => 'local',
                '--path' => 'plain',
            ]);

            expect($exit)->toBe(0, Artisan::output());

            expect($server->table("{$scratch}.core_config")->where('code', $marker)->value('value'))->toBe('kept');
        });
    } finally {
        dropBackupEncryptionDatabase($scratch);
    }
});
