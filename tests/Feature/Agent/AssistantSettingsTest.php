<?php

use Anthropic\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Webkul\Agent\Support\Assistant;
use Webkul\Agent\Support\AssistantSettings;
use Webkul\Core\Models\CoreConfig;
use Webkul\Security\Support\CoreConfigEncryptor;

uses(DatabaseTransactions::class);

beforeEach(function () {
    config([
        'agent.assistant.api_key' => null,
        'agent.assistant.base_url' => null,
        'agent.assistant.model' => 'claude-opus-5-5',
    ]);
});

function saveAssistantSetting(string $field, string $value): void
{
    CoreConfig::updateOrCreate(['code' => AssistantSettings::CONFIG_PREFIX.$field], ['value' => $value]);
}

it('shows the AI Assistant and Telegram sections under Settings > Configuration', function () {
    $admin = agentUser();

    $this->actingAs($admin, 'user')
        ->get(route('admin.configuration.index', ['slug' => 'general', 'slug2' => 'assistant']))
        ->assertOk()
        ->assertSee(trans('agent::app.configuration.settings.api-key'))
        ->assertSee(trans('agent::app.configuration.settings.base-url'));

    $this->actingAs($admin, 'user')
        ->get(route('admin.configuration.index', ['slug' => 'general', 'slug2' => 'telegram']))
        ->assertOk()
        ->assertSee(trans('tma::app.configuration.bot.token'))
        ->assertSee(trans('tma::app.configuration.bot.webhook-secret'));
});

it('saves the key from the settings screen encrypted and uses it when the server sets none', function () {
    $apiKey = 'sk-ant-settings-'.bin2hex(random_bytes(8));

    $this->actingAs(agentUser(), 'user')
        ->post(route('admin.configuration.store', ['slug' => 'general', 'slug2' => 'assistant']), [
            'keys' => [json_encode([
                'key' => 'general.assistant.settings',
                'fields' => [['name' => 'api_key'], ['name' => 'model'], ['name' => 'base_url']],
            ])],
            'general' => ['assistant' => ['settings' => [
                'api_key' => $apiKey,
                'model' => 'claude-sonnet-5-5',
                'base_url' => '',
            ]]],
        ])
        ->assertRedirect();

    $raw = (string) DB::table('core_config')
        ->where('code', AssistantSettings::CONFIG_PREFIX.'api_key')
        ->value('value');

    expect($raw)->toStartWith(CoreConfigEncryptor::PREFIX)
        ->and($raw)->not->toContain($apiKey)
        ->and(AssistantSettings::resolve())->toBe([
            'api_key' => $apiKey,
            'base_url' => null,
            'model' => 'claude-sonnet-5-5',
            'source' => AssistantSettings::SOURCE_SETTINGS,
        ])
        ->and(AssistantSettings::configured())->toBeTrue();

    $this->actingAs(agentUser(), 'user')
        ->get(route('admin.agent.assistant.index'))
        ->assertOk()
        ->assertViewHas('configured', true)
        ->assertViewHas('model', 'claude-sonnet-5-5');
});

it('answers with the model chosen in settings when only a settings key exists', function () {
    saveAssistantSetting('api_key', 'sk-ant-settings-only');
    saveAssistantSetting('model', 'claude-sonnet-5-5');

    $requests = new ArrayObject;
    $stack = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'id' => uniqid('msg_'),
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-sonnet-5-5',
        'stop_reason' => 'end_turn',
        'stop_sequence' => null,
        'content' => [['type' => 'text', 'text' => 'Hello.']],
        'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
    ]))]));
    $stack->push(Middleware::mapRequest(function ($request) use ($requests) {
        $requests[] = json_decode((string) $request->getBody(), true);

        return $request;
    }));

    app()->when(Assistant::class)->needs(Client::class)->give(fn () => new Client(
        apiKey: AssistantSettings::resolve()['api_key'],
        requestOptions: ['transporter' => new GuzzleClient(['handler' => $stack]), 'maxRetries' => 0],
    ));

    $this->actingAs(agentUser(), 'user')
        ->postJson(route('admin.agent.assistant.send'), ['message' => 'Hi'])
        ->assertOk()
        ->assertJsonPath('reply', 'Hello.');

    expect($requests[0]['model'])->toBe('claude-sonnet-5-5')
        ->and($requests[0])->toHaveKey('fallbacks');
});

it('uses the server key, address and model together and never mixes in settings values', function () {
    config([
        'agent.assistant.api_key' => 'sk-ant-server-key',
        'agent.assistant.base_url' => null,
    ]);

    saveAssistantSetting('api_key', 'sk-ant-settings-key');
    saveAssistantSetting('base_url', 'https://collector.example.invalid');
    saveAssistantSetting('model', 'claude-haiku-5-5');

    expect(AssistantSettings::resolve())->toBe([
        'api_key' => 'sk-ant-server-key',
        'base_url' => null,
        'model' => 'claude-opus-5-5',
        'source' => AssistantSettings::SOURCE_ENV,
    ]);
});

it('treats an empty server base URL as the default Anthropic endpoint', function () {
    config([
        'agent.assistant.api_key' => 'sk-ant-server-key',
        'agent.assistant.base_url' => '',
        'agent.assistant.model' => '',
    ]);

    expect(AssistantSettings::resolve()['base_url'])->toBeNull()
        ->and(AssistantSettings::resolve()['model'])->toBe(AssistantSettings::DEFAULT_MODEL);
});

it('reports the assistant as not set up when neither the server nor settings hold a key', function () {
    expect(AssistantSettings::resolve()['source'])->toBeNull()
        ->and(AssistantSettings::configured())->toBeFalse();

    $this->actingAs(agentUser(), 'user')
        ->postJson(route('admin.agent.assistant.send'), ['message' => 'Hi'])
        ->assertStatus(503)
        ->assertJsonPath('message', trans('agent::app.assistant.errors.not-configured'));
});
