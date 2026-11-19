<?php

namespace Webkul\Security\Support;

use InvalidArgumentException;
use RuntimeException;

final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private const PERIOD_SECONDS = 30;

    /**
     * Generate a 160-bit (32-character RFC 4648 Base32) TOTP secret.
     */
    public static function generateSecret(int $bytes = 20): string
    {
        if ($bytes < 16 || $bytes % 5 !== 0) {
            throw new InvalidArgumentException('TOTP secret byte length must be a multiple of 5 and at least 16 bytes.');
        }

        return self::encodeBase32(random_bytes($bytes));
    }

    /**
     * Compute the 6-digit RFC 6238 HMAC-SHA1 TOTP code for a 30-second time slice.
     */
    public static function codeAt(string $base32Secret, int $timeSlice): string
    {
        $key = self::decodeBase32($base32Secret);
        $binaryTime = pack('N2', ($timeSlice >> 32) & 0xFFFFFFFF, $timeSlice & 0xFFFFFFFF);
        $hash = hash_hmac('sha1', $binaryTime, $key, true);
        $offset = ord($hash[19]) & 0x0F;

        $truncated = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($truncated % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a 6-digit TOTP code within +/- $window slices while enforcing strict monotonic replay protection.
     *
     * @return int|null Matched time slice if valid and unused, or null on rejection.
     */
    public static function verify(
        string $base32Secret,
        string $code,
        ?int $lastUsedSlice = null,
        ?int $timestamp = null,
        int $window = 1
    ): ?int {
        $normalized = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{6}$/', $normalized)) {
            return null;
        }

        $currentSlice = intdiv($timestamp ?? time(), self::PERIOD_SECONDS);

        for ($offset = -$window; $offset <= $window; $offset++) {
            $slice = $currentSlice + $offset;

            if ($slice < 0 || ($lastUsedSlice !== null && $slice <= $lastUsedSlice)) {
                continue;
            }

            if (hash_equals(self::codeAt($base32Secret, $slice), $normalized)) {
                return $slice;
            }
        }

        return null;
    }

    /**
     * Build a standard otpauth://totp URI for authenticator apps.
     */
    public static function provisioningUri(string $issuer, string $accountEmail, string $base32Secret): string
    {
        $cleanIssuer = str_replace(':', '-', trim($issuer)) ?: 'Luminara CRM';
        $label = rawurlencode($cleanIssuer).':'.rawurlencode(trim($accountEmail));

        $query = http_build_query([
            'secret' => $base32Secret,
            'issuer' => $cleanIssuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => self::PERIOD_SECONDS,
        ], '', '&', PHP_QUERY_RFC3986);

        return "otpauth://totp/{$label}?{$query}";
    }

    /**
     * Generate single-use backup recovery codes in XXXX-XXXX-XXXX format.
     *
     * @return array<int, string>
     */
    public static function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $hex = strtoupper(bin2hex(random_bytes(6)));
            $codes[] = substr($hex, 0, 4).'-'.substr($hex, 4, 4).'-'.substr($hex, 8, 4);
        }

        return $codes;
    }

    /**
     * Hash a recovery code with HMAC-SHA256 for safe database storage and constant-time comparison.
     */
    public static function hashRecoveryCode(string $code): string
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
        $secret = trim((string) config('app.key', ''));

        if ($secret === '' && app()->isProduction()) {
            throw new RuntimeException('APP_KEY must be configured in production for MFA recovery code hashing.');
        }

        return hash_hmac('sha256', 'mfa-recovery:'.$normalized, $secret !== '' ? $secret : 'luminara-dev-mfa-key');
    }

    public static function encodeBase32(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        $bits = '';
        $length = strlen($bytes);

        for ($i = 0; $i < $length; $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }

        $output = '';

        foreach (str_split($bits, 5) as $chunk) {
            $padded = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $output .= self::ALPHABET[(int) bindec($padded)];
        }

        return $output;
    }

    public static function decodeBase32(string $base32): string
    {
        $cleaned = strtoupper((string) preg_replace('/[\s=\-]+/', '', $base32));

        if ($cleaned === '') {
            throw new InvalidArgumentException('Empty Base32 secret.');
        }

        $bits = '';
        $length = strlen($cleaned);

        for ($i = 0; $i < $length; $i++) {
            $pos = strpos(self::ALPHABET, $cleaned[$i]);

            if ($pos === false) {
                throw new InvalidArgumentException('Invalid character in Base32 secret.');
            }

            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';

        foreach (str_split($bits, 8) as $byteBits) {
            if (strlen($byteBits) === 8) {
                $bytes .= chr((int) bindec($byteBits));
            }
        }

        return $bytes;
    }
}
