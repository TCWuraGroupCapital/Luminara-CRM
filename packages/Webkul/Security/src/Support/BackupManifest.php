<?php

namespace Webkul\Security\Support;

/**
 * HMAC-SHA256 payload shared by backup creation and verification.
 * Plain archives (version 1) keep the original field set so older manifests still verify.
 * Encrypted archives (version 2) add the encryption block, including the key id.
 */
final class BackupManifest
{
    /**
     * @param  array<string, mixed>  $tables
     * @param  array{algorithm: string, key_id: string}|null  $encryption
     * @return array<string, mixed>
     */
    public static function signable(
        int $version,
        string $archive,
        int $sizeBytes,
        string $sha256,
        int $totalTables,
        int $totalRows,
        array $tables,
        ?array $encryption
    ): array {
        $payload = [
            'version' => $version,
            'archive' => $archive,
            'size_bytes' => $sizeBytes,
            'sha256' => $sha256,
            'total_tables' => $totalTables,
            'total_rows' => $totalRows,
            'tables' => $tables,
        ];

        if ($encryption !== null) {
            $payload['encryption'] = [
                'algorithm' => $encryption['algorithm'],
                'key_id' => $encryption['key_id'],
            ];
        }

        return $payload;
    }

    /**
     * Rebuild the signed payload from a manifest the same way verification does.
     *
     * @param  array<string, mixed>  $manifest
     * @param  array{algorithm: string, key_id: string}|null  $encryption
     * @return array<string, mixed>
     */
    public static function signableFromManifest(array $manifest, string $archivePath, string $sha256, ?array $encryption): array
    {
        $tables = is_array($manifest['tables'] ?? null) ? $manifest['tables'] : [];

        return self::signable(
            (int) ($manifest['version'] ?? BackupCipher::VERSION_PLAIN),
            $archivePath,
            (int) ($manifest['size_bytes'] ?? 0),
            $sha256,
            (int) ($manifest['total_tables'] ?? count($tables)),
            (int) ($manifest['total_rows'] ?? 0),
            $tables,
            $encryption
        );
    }

    /**
     * @param  array<string, mixed>  $signable
     */
    public static function hmac(array $signable, string $key): string
    {
        return hash_hmac(
            'sha256',
            json_encode($signable, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $key
        );
    }
}
