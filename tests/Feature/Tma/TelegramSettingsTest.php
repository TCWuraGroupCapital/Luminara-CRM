<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Webkul\Core\Models\CoreConfig;
use Webkul\Security\Support\CoreConfigEncryptor;
use Webkul\Tma\Support\TelegramSettings;

uses(DatabaseTransactions::class);

beforeEach(function () {
    config([
        'tma.bot_token' => '',
        'tma.bot_username' => '',
        'tma.webhook_secret' => '',
    ]);
    Cache::flush();
});

function saveTelegramSetting(string $field, string $value): void
{
    CoreConfig::updateOrCreate(['code' => TelegramSettings::CONFIG_PREFIX.$field], ['value' => $value]);
}

it('stores the bot token and webhook secret encrypted and reads them back', function () {
    $token = '7000000001:AAsettingsScreenToken'.bin2hex(random_bytes(6));
    $secret = 'settings-webhook-'.bin2hex(random_bytes(6));

    saveTelegramSetting('bot_token', $token);
    saveTelegramSetting('webhook_secret', $secret);
    saveTelegramSetting('bot_username', '@MySettingsBot');

    foreach (['bot_token' => $token, 'webhook_secret' => $secret] as $field => $plain) {
        $raw = (string) DB::table('core_config')->where('code', TelegramSettings::CONFIG_PREFIX.$field)->value('value');

        expect($raw)->toStartWith(CoreConfigEncryptor::PREFIX)
            ->and($raw)->not->toContain($plain);
    }

    expect(TelegramSettings::botToken())->toBe($token)
        ->and(TelegramSettings::webhookSecret())->toBe($secret)
        ->and(TelegramSettings::botUsername())->toBe('MySettingsBot');
});

it('accepts webhook calls signed with the secret saved in settings', function () {
    saveTelegramSetting('webhook_secret', 'settings-webhook-secret');

    $this->postJson('/api/tma/bot/webhook', ['update_id' => 1], ['X-Telegram-Bot-Api-Secret-Token' => 'wrong'])
        ->assertStatus(401);

    $this->postJson('/api/tma/bot/webhook', ['update_id' => 1], ['X-Telegram-Bot-Api-Secret-Token' => 'settings-webhook-secret'])
        ->assertOk()
        ->assertJsonPath('ignored', true);
});

it('prefers each value set in the server environment over settings', function () {
    config([
        'tma.bot_token' => 'env-token',
        'tma.webhook_secret' => 'env-webhook-secret',
    ]);

    saveTelegramSetting('bot_token', 'settings-token');
    saveTelegramSetting('webhook_secret', 'settings-webhook-secret');
    saveTelegramSetting('bot_username', 'SettingsBot');

    expect(TelegramSettings::botToken())->toBe('env-token')
        ->and(TelegramSettings::webhookSecret())->toBe('env-webhook-secret')
        ->and(TelegramSettings::botUsername())->toBe('SettingsBot');

    $this->postJson('/api/tma/bot/webhook', ['update_id' => 1], ['X-Telegram-Bot-Api-Secret-Token' => 'settings-webhook-secret'])
        ->assertStatus(401);

    $this->postJson('/api/tma/bot/webhook', ['update_id' => 1], ['X-Telegram-Bot-Api-Secret-Token' => 'env-webhook-secret'])
        ->assertOk();
});

it('builds the pairing link from the bot username saved in settings', function () {
    saveTelegramSetting('bot_username', '@MySettingsBot');

    $response = $this->actingAs(agentUser(), 'user')
        ->postJson(route('admin.tma.pairing.code'))
        ->assertSuccessful();

    expect((string) $response->json('deep_link'))->toStartWith('https://t.me/MySettingsBot?start=');
});
