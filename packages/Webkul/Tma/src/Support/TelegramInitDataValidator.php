<?php

namespace Webkul\Tma\Support;

use Illuminate\Support\Facades\Cache;
use Webkul\Tma\Exceptions\TmaAuthException;

final class TelegramInitDataValidator
{
    /**
     * Validate official Telegram Mini App `initData` query string using `WebAppData` HMAC-SHA256,
     * freshness window (`auth_date`), and single-use replay cache.
     *
     * @return array{
     *     auth_date: int,
     *     hash: string,
     *     query_id: string|null,
     *     start_param: string|null,
     *     user: array{
     *         id: int,
     *         username: string|null,
     *         first_name: string|null,
     *         last_name: string|null,
     *         language_code: string|null
     *     }
     * }
     */
    public function validate(string $initData, ?int $now = null): array
    {
        $trimmed = trim($initData);

        if ($trimmed === '') {
            throw new TmaAuthException('invalid_init_data', 'Telegram initData is empty.');
        }

        $botToken = TelegramSettings::botToken();

        if ($botToken === '') {
            throw new TmaAuthException('missing_bot_token', 'Telegram bot token is not configured.');
        }

        $pairs = [];
        $hash = null;

        foreach (explode('&', $trimmed) as $segment) {
            if ($segment === '') {
                continue;
            }

            $equalsPos = strpos($segment, '=');

            if ($equalsPos === false) {
                throw new TmaAuthException('invalid_init_data', 'Malformed initData parameter segment.');
            }

            $key = rawurldecode(substr($segment, 0, $equalsPos));
            $value = rawurldecode(substr($segment, $equalsPos + 1));

            if ($key === 'hash') {
                $hash = $value;

                continue;
            }

            $pairs[$key] = $value;
        }

        if ($hash === null || preg_match('/^[0-9a-f]{64}$/i', $hash) !== 1 || $pairs === []) {
            throw new TmaAuthException('invalid_init_data', 'Telegram initData is missing a valid signature hash.');
        }

        ksort($pairs, SORT_STRING);

        $lines = [];

        foreach ($pairs as $key => $value) {
            $lines[] = "{$key}={$value}";
        }

        $dataCheckString = implode("\n", $lines);

        // Per Telegram Mini App spec: HMAC-SHA256 where "WebAppData" is the HMAC key and $botToken is the message.
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);
        $normalizedHash = strtolower($hash);

        if (! hash_equals($computedHash, $normalizedHash)) {
            throw new TmaAuthException('invalid_signature', 'Telegram initData signature verification failed.');
        }

        $rawAuthDate = $pairs['auth_date'] ?? '';

        if (! ctype_digit((string) $rawAuthDate) || (int) $rawAuthDate <= 0) {
            throw new TmaAuthException('invalid_auth_date', 'Telegram initData is missing a valid auth_date.');
        }

        $authDate = (int) $rawAuthDate;
        $ttlSeconds = max(1, (int) config('tma.init_data_ttl_seconds', 300));
        $referenceTime = $now ?? time();

        if (abs($referenceTime - $authDate) > $ttlSeconds) {
            throw new TmaAuthException('expired_init_data', 'Telegram initData auth_date is outside the allowed window.');
        }

        if (! Cache::add('tma:init_replay:'.$normalizedHash, 1, max(600, $ttlSeconds * 2))) {
            throw new TmaAuthException('replayed_init_data', 'Telegram initData has already been consumed.');
        }

        $rawUser = $pairs['user'] ?? '';
        $decodedUser = $rawUser !== '' ? json_decode($rawUser, true) : null;

        if (! is_array($decodedUser) || ! isset($decodedUser['id']) || ! is_numeric($decodedUser['id']) || (int) $decodedUser['id'] <= 0) {
            throw new TmaAuthException('invalid_user_payload', 'Telegram initData does not contain a valid user object.');
        }

        return [
            'auth_date' => $authDate,
            'hash' => $normalizedHash,
            'query_id' => isset($pairs['query_id']) && $pairs['query_id'] !== '' ? $pairs['query_id'] : null,
            'start_param' => isset($pairs['start_param']) && $pairs['start_param'] !== '' ? $pairs['start_param'] : null,
            'user' => [
                'id' => (int) $decodedUser['id'],
                'username' => isset($decodedUser['username']) && is_string($decodedUser['username']) && $decodedUser['username'] !== ''
                    ? $decodedUser['username']
                    : null,
                'first_name' => isset($decodedUser['first_name']) && is_string($decodedUser['first_name']) && $decodedUser['first_name'] !== ''
                    ? $decodedUser['first_name']
                    : null,
                'last_name' => isset($decodedUser['last_name']) && is_string($decodedUser['last_name']) && $decodedUser['last_name'] !== ''
                    ? $decodedUser['last_name']
                    : null,
                'language_code' => isset($decodedUser['language_code']) && is_string($decodedUser['language_code']) && $decodedUser['language_code'] !== ''
                    ? $decodedUser['language_code']
                    : null,
            ],
        ];
    }
}
