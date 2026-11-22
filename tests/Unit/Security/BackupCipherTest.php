<?php

use Webkul\Security\Support\BackupCipher;
use Webkul\Security\Support\BackupManifest;

function backupCipherKey(): string
{
    return sodium_bin2base64(
        random_bytes(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES),
        SODIUM_BASE64_VARIANT_ORIGINAL
    );
}

it('parses a 32 byte key and a base64 prefix', function () {
    $encoded = backupCipherKey();
    $raw = BackupCipher::parseKey($encoded);
    $prefixed = BackupCipher::parseKey('base64:'.$encoded);

    expect(strlen($raw))->toBe(32)
        ->and($prefixed)->toBe($raw)
        ->and(BackupCipher::keyId($raw))->toMatch('/^[a-f0-9]{64}$/');

    BackupCipher::wipe($raw);
    BackupCipher::wipe($prefixed);
});

it('rejects empty, malformed, and short keys', function () {
    expect(fn () => BackupCipher::parseKey(''))
        ->toThrow(RuntimeException::class, 'empty');

    expect(fn () => BackupCipher::parseKey('not base64!!!'))
        ->toThrow(RuntimeException::class, 'not valid base64');

    $short = sodium_bin2base64(random_bytes(16), SODIUM_BASE64_VARIANT_ORIGINAL);

    expect(fn () => BackupCipher::parseKey($short))
        ->toThrow(RuntimeException::class, '32 bytes');
});

it('roundtrips plaintext through secretstream, including chunk boundaries', function (int $size) {
    $encoded = backupCipherKey();
    $raw = BackupCipher::parseKey($encoded);
    $plain = $size === 0 ? '' : random_bytes($size);
    $plainPath = tempnam(sys_get_temp_dir(), 'lum_plain_');
    $cipherPath = $plainPath.'.enc';
    $restoredPath = $plainPath.'.out';

    file_put_contents($plainPath, $plain);

    try {
        BackupCipher::encryptFile($plainPath, $cipherPath, $raw);

        expect(file_get_contents($cipherPath))->toStartWith(BackupCipher::MAGIC)
            ->and(substr((string) file_get_contents($cipherPath), 0, 2))->not->toBe("\x1f\x8b");

        if ($size >= 32) {
            expect(file_get_contents($cipherPath))->not->toContain($plain);
        }

        BackupCipher::decryptFile($cipherPath, $restoredPath, $raw);

        expect(file_get_contents($restoredPath))->toBe($plain);
    } finally {
        BackupCipher::wipe($raw);
        foreach ([$plainPath, $cipherPath, $restoredPath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
})->with([0, 1, 64, 65536, 65537, 200000]);

it('rejects the wrong key, truncation, a bad header, and trailing bytes', function () {
    $raw = BackupCipher::parseKey(backupCipherKey());
    $other = BackupCipher::parseKey(backupCipherKey());
    $plainPath = tempnam(sys_get_temp_dir(), 'lum_plain_');
    $cipherPath = $plainPath.'.enc';
    $restoredPath = $plainPath.'.out';
    file_put_contents($plainPath, 'rows');

    try {
        BackupCipher::encryptFile($plainPath, $cipherPath, $raw);
        $cipher = (string) file_get_contents($cipherPath);

        expect(fn () => BackupCipher::decryptFile($cipherPath, $restoredPath, $other))
            ->toThrow(RuntimeException::class, 'decryption failed');

        file_put_contents($cipherPath, substr($cipher, 0, -1));

        expect(fn () => BackupCipher::decryptFile($cipherPath, $restoredPath, $raw))
            ->toThrow(RuntimeException::class);

        file_put_contents($cipherPath, 'XXXX'.$cipher);

        expect(fn () => BackupCipher::decryptFile($cipherPath, $restoredPath, $raw))
            ->toThrow(RuntimeException::class, 'unknown header');

        file_put_contents($cipherPath, BackupCipher::MAGIC.chr(9).substr($cipher, 5));

        expect(fn () => BackupCipher::decryptFile($cipherPath, $restoredPath, $raw))
            ->toThrow(RuntimeException::class, 'Unsupported backup encryption format');

        file_put_contents($cipherPath, $cipher.'x');

        expect(fn () => BackupCipher::decryptFile($cipherPath, $restoredPath, $raw))
            ->toThrow(RuntimeException::class, 'trailing data');
    } finally {
        BackupCipher::wipe($raw);
        BackupCipher::wipe($other);
        foreach ([$plainPath, $cipherPath, $restoredPath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
});

it('keeps the version 1 manifest payload free of an encryption block', function () {
    $sha = str_repeat('ab', 32);
    $signable = BackupManifest::signable(
        BackupCipher::VERSION_PLAIN,
        'backups/luminara_backup_20260101_000000.ndjson.gz',
        4,
        $sha,
        1,
        1,
        ['users' => 1],
        null
    );

    expect(json_encode($signable, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))->toBe(
        '{"version":1,"archive":"backups/luminara_backup_20260101_000000.ndjson.gz","size_bytes":4,"sha256":"'.$sha.'","total_tables":1,"total_rows":1,"tables":{"users":1}}'
    );

    $encrypted = BackupManifest::signable(
        BackupCipher::VERSION_ENCRYPTED,
        'backups/luminara_backup_20260101_000000.ndjson.gz.enc',
        4,
        $sha,
        1,
        1,
        ['users' => 1],
        ['algorithm' => BackupCipher::ALGORITHM, 'key_id' => str_repeat('cd', 32)]
    );

    expect($encrypted['encryption']['key_id'])->toBe(str_repeat('cd', 32))
        ->and(array_key_last($encrypted))->toBe('encryption');
});
