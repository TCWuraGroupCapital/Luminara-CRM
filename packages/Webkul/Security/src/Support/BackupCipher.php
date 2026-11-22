<?php

namespace Webkul\Security\Support;

use RuntimeException;
use SodiumException;
use Throwable;

/**
 * Optional libsodium secretstream (XChaCha20-Poly1305) wrapping for logical backups.
 *
 * The encryption key comes from LUMINARA_BACKUP_KEY and is separate from APP_KEY.
 * APP_KEY still signs the manifest. The manifest stores only the key id.
 *
 * Encrypted archive layout:
 *   magic "LMBK" (4 bytes)
 *   format version (1 byte, currently 1)
 *   secretstream header (24 bytes)
 *   frames: uint32 big-endian ciphertext length, then ciphertext
 * The last frame is tagged final. The plaintext is the gzip NDJSON payload.
 */
final class BackupCipher
{
    public const ALGORITHM = 'secretstream_xchacha20poly1305';

    public const MAGIC = 'LMBK';

    public const FORMAT_VERSION = 1;

    public const VERSION_PLAIN = 1;

    public const VERSION_ENCRYPTED = 2;

    public const CHUNK_BYTES = 65536;

    public const ARCHIVE_BASENAME = '/^luminara_backup_\d{8}_\d{6}\.ndjson\.gz(\.enc)?$/';

    public const STORED_BASENAME = '/^luminara_backup_\d{8}_\d{6}\.(?:ndjson\.gz(?:\.enc)?|manifest\.json)$/';

    /**
     * Validate LUMINARA_BACKUP_KEY when it is set. An empty value means plain gzip.
     */
    public static function assertConfiguredKey(): void
    {
        $raw = self::configuredKey();

        if (is_string($raw)) {
            self::wipe($raw);
        }
    }

    /**
     * Raw 32-byte key, or null when encryption is off.
     */
    public static function configuredKey(): ?string
    {
        $configured = trim((string) config('security.backup_key', ''));

        if ($configured === '') {
            return null;
        }

        $raw = self::parseKey($configured);

        if (self::matchesAppKey($raw)) {
            self::wipe($raw);

            throw new RuntimeException('LUMINARA_BACKUP_KEY must not be the same key as APP_KEY.');
        }

        return $raw;
    }

    public static function parseKey(string $configured): string
    {
        $configured = trim($configured);

        if (str_starts_with($configured, 'base64:')) {
            $configured = substr($configured, 7);
        }

        if ($configured === '') {
            throw new RuntimeException('LUMINARA_BACKUP_KEY is empty.');
        }

        try {
            $raw = sodium_base642bin($configured, SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (SodiumException) {
            throw new RuntimeException('LUMINARA_BACKUP_KEY is not valid base64.');
        }

        if (strlen($raw) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            self::wipe($raw);

            throw new RuntimeException('LUMINARA_BACKUP_KEY must decode to 32 bytes.');
        }

        return $raw;
    }

    public static function keyId(string $rawKey): string
    {
        self::assertRawKey($rawKey);

        return bin2hex(sodium_crypto_generichash($rawKey));
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array{algorithm: string, key_id: string}|null
     */
    public static function encryptionFromManifest(array $manifest): ?array
    {
        $version = (int) ($manifest['version'] ?? self::VERSION_PLAIN);
        $hasEncryption = array_key_exists('encryption', $manifest) && $manifest['encryption'] !== null;

        if (! $hasEncryption) {
            if ($version !== self::VERSION_PLAIN) {
                throw new RuntimeException('Plain backup manifest version is not supported.');
            }

            return null;
        }

        if ($version !== self::VERSION_ENCRYPTED) {
            throw new RuntimeException('Encrypted backup manifest version is not supported.');
        }

        $encryption = $manifest['encryption'];

        if (! is_array($encryption)) {
            throw new RuntimeException('Malformed encryption block in backup manifest.');
        }

        $algorithm = $encryption['algorithm'] ?? null;
        $keyId = $encryption['key_id'] ?? null;

        if ($algorithm !== self::ALGORITHM || ! is_string($keyId) || preg_match('/^[a-f0-9]{64}$/', $keyId) !== 1) {
            throw new RuntimeException('Malformed encryption block in backup manifest.');
        }

        return [
            'algorithm' => self::ALGORITHM,
            'key_id' => $keyId,
        ];
    }

    /**
     * Return a path to gzip NDJSON. Encrypted archives are decrypted beside the stored file.
     *
     * @param  array<string, mixed>  $manifest
     */
    public static function materializeGzip(string $storedPath, array $manifest): string
    {
        $encryption = self::encryptionFromManifest($manifest);

        if ($encryption === null) {
            return $storedPath;
        }

        $gzipPath = $storedPath.'.decrypted.gz';
        $raw = self::requireKeyFor($encryption['key_id']);

        try {
            self::decryptFile($storedPath, $gzipPath, $raw);
        } catch (Throwable $e) {
            @unlink($gzipPath);

            throw $e;
        } finally {
            self::wipe($raw);
        }

        return $gzipPath;
    }

    public static function encryptFile(string $plainPath, string $cipherPath, string $rawKey): void
    {
        self::assertRawKey($rawKey);
        self::assertSodium();

        $in = fopen($plainPath, 'rb');
        $out = fopen($cipherPath, 'wb');

        if ($in === false || $out === false) {
            if (is_resource($in)) {
                fclose($in);
            }

            if (is_resource($out)) {
                fclose($out);
            }

            throw new RuntimeException('Unable to open files for backup encryption.');
        }

        $state = '';

        try {
            $pushed = sodium_crypto_secretstream_xchacha20poly1305_init_push($rawKey);
            $state = $pushed[0];
            $header = $pushed[1];

            if (! is_string($state) || ! is_string($header)) {
                throw new RuntimeException('Backup encryption failed.');
            }

            self::writeAll($out, self::MAGIC.chr(self::FORMAT_VERSION).$header);

            $pending = fread($in, self::CHUNK_BYTES);

            if (! is_string($pending)) {
                throw new RuntimeException('Unable to read the backup payload for encryption.');
            }

            if ($pending === '') {
                self::writeFrame($out, $state, '', true);
            }

            while ($pending !== '') {
                $next = fread($in, self::CHUNK_BYTES);

                if (! is_string($next)) {
                    throw new RuntimeException('Unable to read the backup payload for encryption.');
                }

                self::writeFrame($out, $state, $pending, $next === '');
                $pending = $next;
            }

            if (fflush($out) === false) {
                throw new RuntimeException('Unable to finish writing the encrypted backup archive.');
            }
        } catch (Throwable $e) {
            fclose($in);
            fclose($out);
            @unlink($cipherPath);

            if ($e instanceof SodiumException) {
                throw new RuntimeException('Backup encryption failed.');
            }

            throw $e;
        }

        fclose($in);
        fclose($out);
    }

    public static function decryptFile(string $cipherPath, string $plainPath, string $rawKey): void
    {
        self::assertRawKey($rawKey);
        self::assertSodium();

        $in = fopen($cipherPath, 'rb');
        $out = fopen($plainPath, 'wb');

        if ($in === false || $out === false) {
            if (is_resource($in)) {
                fclose($in);
            }

            if (is_resource($out)) {
                fclose($out);
            }

            throw new RuntimeException('Unable to open files for backup decryption.');
        }

        $state = '';

        try {
            $magic = self::readExact($in, strlen(self::MAGIC));

            if ($magic !== self::MAGIC) {
                throw new RuntimeException('Encrypted backup archive has an unknown header.');
            }

            $version = ord(self::readExact($in, 1));

            if ($version !== self::FORMAT_VERSION) {
                throw new RuntimeException('Unsupported backup encryption format.');
            }

            $header = self::readExact($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);

            try {
                $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $rawKey);
            } catch (SodiumException) {
                throw new RuntimeException('Backup decryption failed. The key is wrong or the archive was tampered with.');
            }

            $sawFinal = false;

            while (true) {
                $lenBytes = fread($in, 4);

                if (! is_string($lenBytes)) {
                    throw new RuntimeException('Encrypted backup archive is truncated.');
                }

                if ($lenBytes === '') {
                    break;
                }

                if (strlen($lenBytes) < 4) {
                    $lenBytes .= self::readExact($in, 4 - strlen($lenBytes));
                }

                $unpacked = unpack('Nlen', $lenBytes);
                $length = is_array($unpacked) ? (int) ($unpacked['len'] ?? 0) : 0;
                $max = self::CHUNK_BYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;

                if ($length < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES || $length > $max) {
                    throw new RuntimeException('Encrypted backup archive has an invalid frame.');
                }

                $cipher = self::readExact($in, $length);
                $opened = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher, '');

                if ($opened === false) {
                    throw new RuntimeException('Backup decryption failed. The key is wrong or the archive was tampered with.');
                }

                self::writeAll($out, $opened[0]);

                $tag = (int) $opened[1];

                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    $sawFinal = true;
                    break;
                }

                if ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE) {
                    throw new RuntimeException('Encrypted backup archive has an unexpected frame.');
                }
            }

            if (! $sawFinal) {
                throw new RuntimeException('Encrypted backup archive is truncated.');
            }

            $extra = fread($in, 1);

            if (is_string($extra) && $extra !== '') {
                throw new RuntimeException('Encrypted backup archive has trailing data.');
            }

            if (fflush($out) === false) {
                throw new RuntimeException('Unable to finish writing the decrypted backup payload.');
            }
        } catch (Throwable $e) {
            fclose($in);
            fclose($out);
            @unlink($plainPath);

            throw $e;
        }

        fclose($in);
        fclose($out);
    }

    public static function wipe(?string &$value): void
    {
        if (is_string($value) && $value !== '') {
            sodium_memzero($value);
        }
    }

    private static function requireKeyFor(string $keyId): string
    {
        $raw = self::configuredKey();

        if ($raw === null) {
            throw new RuntimeException('LUMINARA_BACKUP_KEY is not set. This backup was encrypted with key id '.$keyId.'.');
        }

        $actual = self::keyId($raw);

        if (! hash_equals($keyId, $actual)) {
            self::wipe($raw);

            throw new RuntimeException('LUMINARA_BACKUP_KEY is key id '.$actual.', but this backup was encrypted with key id '.$keyId.'.');
        }

        return $raw;
    }

    private static function matchesAppKey(string $raw): bool
    {
        $appKey = trim((string) config('app.key', ''));

        if ($appKey === '') {
            return false;
        }

        try {
            $appRaw = self::parseKey($appKey);
        } catch (RuntimeException) {
            return false;
        }

        try {
            return hash_equals($appRaw, $raw);
        } finally {
            self::wipe($appRaw);
        }
    }

    private static function assertRawKey(string $rawKey): void
    {
        if (strlen($rawKey) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('Backup encryption key must be 32 bytes.');
        }
    }

    private static function assertSodium(): void
    {
        if (! function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push')) {
            throw new RuntimeException('The sodium extension is required to encrypt backups.');
        }
    }

    /**
     * @param  resource  $handle
     */
    private static function readExact($handle, int $length): string
    {
        if ($length === 0) {
            return '';
        }

        $data = '';

        while (strlen($data) < $length) {
            $chunk = fread($handle, $length - strlen($data));

            if (! is_string($chunk) || $chunk === '') {
                throw new RuntimeException('Encrypted backup archive is truncated.');
            }

            $data .= $chunk;
        }

        return $data;
    }

    /**
     * @param  resource  $handle
     */
    private static function writeAll($handle, string $data): void
    {
        $offset = 0;
        $length = strlen($data);

        while ($offset < $length) {
            $written = fwrite($handle, substr($data, $offset));

            if ($written === false || $written === 0) {
                throw new RuntimeException('Unable to write the backup archive.');
            }

            $offset += $written;
        }
    }

    /**
     * @param  resource  $handle
     */
    private static function writeFrame($handle, string &$state, string $plain, bool $final): void
    {
        $tag = $final
            ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
            : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;

        $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $plain, '', $tag);
        self::writeAll($handle, pack('N', strlen($cipher)).$cipher);
    }
}
