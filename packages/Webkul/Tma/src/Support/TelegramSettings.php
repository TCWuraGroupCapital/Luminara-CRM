<?php

namespace Webkul\Tma\Support;

/**
 * The Telegram bot's credentials. Each value set in the server's environment (`TELEGRAM_BOT_TOKEN`,
 * `TELEGRAM_BOT_USERNAME`, `TELEGRAM_WEBHOOK_SECRET`) wins; an empty one falls back to Settings >
 * Configuration > Telegram, where the token and webhook secret are stored encrypted.
 *
 * Mixing sources per value is safe here: none of these values is ever sent to an address the
 * settings screen controls.
 */
final class TelegramSettings
{
    public const CONFIG_PREFIX = 'general.telegram.bot.';

    public static function botToken(): string
    {
        return self::resolve('tma.bot_token', 'bot_token');
    }

    public static function botUsername(): string
    {
        return ltrim(self::resolve('tma.bot_username', 'bot_username'), '@');
    }

    public static function webhookSecret(): string
    {
        return self::resolve('tma.webhook_secret', 'webhook_secret');
    }

    private static function resolve(string $configKey, string $field): string
    {
        $fromEnv = trim((string) config($configKey, ''));

        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $stored = core()->getConfigData(self::CONFIG_PREFIX.$field);

        return is_string($stored) ? trim($stored) : '';
    }
}
