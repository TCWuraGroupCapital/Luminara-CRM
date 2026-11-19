<?php

namespace Webkul\Security\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

final class CoreConfigEncryptor
{
    public const PREFIX = 'enc:v1:';

    /**
     * Determine whether a core_config code holds sensitive credentials that must be encrypted at rest.
     */
    public static function isSensitiveCode(?string $code): bool
    {
        if ($code === null || $code === '') {
            return false;
        }

        $configured = (array) config('security.encrypted_core_config_codes', [
            'general.magic_ai.settings.api_key',
            'email.imap.account.password',
        ]);

        if (in_array($code, $configured, true)) {
            return true;
        }

        $field = core()->getConfigField($code);

        return is_array($field) && (($field['type'] ?? null) === 'password');
    }

    /**
     * Encrypt a plaintext config value unless empty or already encrypted.
     */
    public static function encryptIfNeeded(?string $code, mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || ! self::isSensitiveCode($code)) {
            return $value;
        }

        if (str_starts_with($value, self::PREFIX)) {
            return $value;
        }

        return self::PREFIX.Crypt::encryptString($value);
    }

    /**
     * Decrypt an enc:v1: value transparently while preserving legacy plaintext rows during migration.
     */
    public static function decryptIfNeeded(mixed $value): mixed
    {
        if (! is_string($value) || ! str_starts_with($value, self::PREFIX)) {
            return $value;
        }

        try {
            return Crypt::decryptString(substr($value, strlen(self::PREFIX)));
        } catch (DecryptException) {
            logger()->warning('Failed to decrypt sensitive core_config value; check APP_KEY consistency.');

            return '';
        }
    }
}
