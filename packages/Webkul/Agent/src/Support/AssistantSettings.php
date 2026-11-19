<?php

namespace Webkul\Agent\Support;

/**
 * Where the in-app assistant gets its model connection from.
 *
 * A key in the server's environment (`LUMINARA_ASSISTANT_API_KEY`) wins, and then the address and
 * model come from the environment too. Otherwise everything comes from Settings > Configuration >
 * AI Assistant, where the key is stored encrypted. The two sources are never mixed, so a base URL
 * typed into the settings screen can never receive a key that only the server's operator holds.
 */
final class AssistantSettings
{
    public const CONFIG_PREFIX = 'general.assistant.settings.';

    public const DEFAULT_MODEL = 'claude-opus-5-5';

    public const SOURCE_ENV = 'env';

    public const SOURCE_SETTINGS = 'settings';

    /**
     * @return array{api_key: string, base_url: ?string, model: string, source: ?string}
     */
    public static function resolve(): array
    {
        $envKey = self::clean(config('agent.assistant.api_key'));

        if ($envKey !== null) {
            return [
                'api_key' => $envKey,
                'base_url' => self::clean(config('agent.assistant.base_url')),
                'model' => self::clean(config('agent.assistant.model')) ?? self::DEFAULT_MODEL,
                'source' => self::SOURCE_ENV,
            ];
        }

        $key = self::clean(core()->getConfigData(self::CONFIG_PREFIX.'api_key'));

        return [
            'api_key' => $key ?? '',
            'base_url' => self::clean(core()->getConfigData(self::CONFIG_PREFIX.'base_url')),
            'model' => self::clean(core()->getConfigData(self::CONFIG_PREFIX.'model'))
                ?? self::clean(config('agent.assistant.model'))
                ?? self::DEFAULT_MODEL,
            'source' => $key === null ? null : self::SOURCE_SETTINGS,
        ];
    }

    public static function configured(): bool
    {
        return self::resolve()['api_key'] !== '';
    }

    private static function clean(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
