<?php

namespace Webkul\Tma\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Webkul\Security\Support\SecurityAuditRecorder;
use Webkul\Tma\Models\TelegramIdentity;
use Webkul\User\Models\User;

final class PairingCodeManager
{
    public const TTL_SECONDS = 600;

    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Issue a short-lived 8-character uppercase alphanumeric pairing code for linking a CRM user
     * to their Telegram account inside the Mini App or via `/start <CODE>`.
     *
     * @return array{code: string, expires_in: int, deep_link: string|null}
     */
    public function issueCode(int $userId): array
    {
        $maxIndex = strlen(self::ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < 8; $i++) {
            $code .= self::ALPHABET[random_int(0, $maxIndex)];
        }

        Cache::put(self::cacheKey($code), $userId, self::TTL_SECONDS);

        $botUsername = TelegramSettings::botUsername();
        $deepLink = preg_match('/^[A-Za-z0-9_]{5,32}$/', $botUsername) === 1
            ? "https://t.me/{$botUsername}?start={$code}"
            : null;

        return [
            'code' => $code,
            'expires_in' => self::TTL_SECONDS,
            'deep_link' => $deepLink,
        ];
    }

    /**
     * Atomically consume a pairing code and link the Telegram identity to the issuing CRM user.
     *
     * @param  array<string, mixed>  $telegramUser
     */
    public function consumeCode(string $code, array $telegramUser, ?int $chatId = null): ?TelegramIdentity
    {
        $normalized = strtoupper(trim($code));

        if (preg_match('/^[A-Z0-9]{8}$/', $normalized) !== 1) {
            return null;
        }

        $telegramId = isset($telegramUser['id']) && is_numeric($telegramUser['id'])
            ? (int) $telegramUser['id']
            : 0;

        if ($telegramId <= 0) {
            return null;
        }

        $userId = Cache::pull(self::cacheKey($normalized));

        if (! is_int($userId) && ! is_numeric($userId)) {
            return null;
        }

        $resolvedUserId = (int) $userId;

        /** @var User|null $user */
        $user = User::query()->whereKey($resolvedUserId)->where('status', 1)->first();

        if (! $user instanceof User) {
            return null;
        }

        /** @var TelegramIdentity $identity */
        $identity = DB::transaction(function () use ($resolvedUserId, $telegramId, $telegramUser, $chatId): TelegramIdentity {
            TelegramIdentity::query()
                ->where('telegram_user_id', $telegramId)
                ->where('user_id', '!=', $resolvedUserId)
                ->delete();

            /** @var TelegramIdentity|null $existing */
            $existing = TelegramIdentity::query()->where('user_id', $resolvedUserId)->first();

            /** @var TelegramIdentity $linked */
            $linked = TelegramIdentity::query()->updateOrCreate(
                ['user_id' => $resolvedUserId],
                [
                    'telegram_user_id' => $telegramId,
                    'telegram_username' => isset($telegramUser['username']) && is_string($telegramUser['username']) && $telegramUser['username'] !== ''
                        ? $telegramUser['username']
                        : null,
                    'first_name' => isset($telegramUser['first_name']) && is_string($telegramUser['first_name']) && $telegramUser['first_name'] !== ''
                        ? $telegramUser['first_name']
                        : null,
                    'chat_id' => $chatId ?? $existing?->chat_id,
                    'last_auth_date' => isset($telegramUser['auth_date']) && is_numeric($telegramUser['auth_date'])
                        ? (int) $telegramUser['auth_date']
                        : time(),
                    'linked_at' => now(),
                ]
            );

            return $linked;
        });

        SecurityAuditRecorder::record(
            'tma.identity.linked',
            'telegram_identity',
            $identity->id,
            [
                'telegram_user_id' => $identity->telegram_user_id,
                'linked_user_id' => $identity->user_id,
            ],
            $user->id,
            (string) $user->getAttribute('email')
        );

        return $identity;
    }

    private static function cacheKey(string $code): string
    {
        return "tma:pair:{$code}";
    }
}
