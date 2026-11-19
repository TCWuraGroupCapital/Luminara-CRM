<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Webkul\Automation\Models\Workflow;
use Webkul\Email\InboundEmailProcessor\Contracts\InboundEmailProcessor;
use Webkul\Installer\Console\Commands\Installer;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Services\MagicAIService;
use Webkul\WebForm\Models\WebForm;
use Webkul\WebForm\Models\WebFormAttribute;

uses(DatabaseTransactions::class);

it('neutralizes crafted dark_mode and sidebar_collapsed cookie payloads inside Admin JavaScript blocks', function () {
    $admin = getDefaultAdmin();

    $xssPayload = '0, pwned: window.__xss = 1, x: 0';

    $html = test()->actingAs($admin, 'user')
        ->withUnencryptedCookie('dark_mode', $xssPayload)
        ->withUnencryptedCookie('sidebar_collapsed', $xssPayload)
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->not->toContain('window.__xss')
        ->toContain('isDarkMode: 0,')
        ->toContain('isCollapsed: 0,');
});

it('strips X-Forwarded-Host in TrustProxies to block host-header poisoning while honoring scheme and client IP', function () {
    $previousConfig = config('trustedproxy.proxies');
    config(['trustedproxy.proxies' => '127.0.0.1']);

    try {
        $request = Request::create('http://crm.luminaradigital.example/admin/forgot-password', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'crm.luminaradigital.example',
            'HTTP_X_FORWARDED_HOST' => 'evil-attacker.invalid',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.77',
        ]);

        (new TrustProxies)->handle($request, fn (Request $req) => response('ok'));

        expect($request->getHost())->toBe('crm.luminaradigital.example')
            ->and($request->isSecure())->toBeTrue()
            ->and($request->getClientIp())->toBe('198.51.100.77');
    } finally {
        config(['trustedproxy.proxies' => $previousConfig]);
        Request::setTrustedProxies(
            [],
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_PORT
        );
    }
});

it('ships hardened mail TLS peer verification, non-webkul mail domain, and encrypted session defaults', function () {
    $prevDomain = $_ENV['MAIL_DOMAIN'] ?? null;
    $prevVerify = $_ENV['MAIL_VERIFY_PEER'] ?? null;
    $prevEncrypt = $_ENV['SESSION_ENCRYPT'] ?? null;

    unset($_ENV['MAIL_DOMAIN'], $_SERVER['MAIL_DOMAIN'], $_ENV['MAIL_VERIFY_PEER'], $_SERVER['MAIL_VERIFY_PEER'], $_ENV['SESSION_ENCRYPT'], $_SERVER['SESSION_ENCRYPT']);
    putenv('MAIL_DOMAIN');
    putenv('MAIL_VERIFY_PEER');
    putenv('SESSION_ENCRYPT');

    try {
        $mailConfig = require base_path('config/mail.php');
        $sessionConfig = require base_path('config/session.php');
    } finally {
        if ($prevDomain !== null) {
            $_ENV['MAIL_DOMAIN'] = $_SERVER['MAIL_DOMAIN'] = $prevDomain;
            putenv("MAIL_DOMAIN={$prevDomain}");
        }

        if ($prevVerify !== null) {
            $_ENV['MAIL_VERIFY_PEER'] = $_SERVER['MAIL_VERIFY_PEER'] = $prevVerify;
            putenv("MAIL_VERIFY_PEER={$prevVerify}");
        }

        if ($prevEncrypt !== null) {
            $_ENV['SESSION_ENCRYPT'] = $_SERVER['SESSION_ENCRYPT'] = $prevEncrypt;
            putenv("SESSION_ENCRYPT={$prevEncrypt}");
        }
    }

    expect($mailConfig['mailers']['smtp']['verify_peer'])->toBeTrue()
        ->and($mailConfig['domain'])->toBe('luminaracrm.local')
        ->and($sessionConfig['encrypt'])->toBeTrue();
});

it('rejects empty or non-string inbound email payloads with 422 before invoking InboundEmailProcessor', function () {
    $called = false;

    app()->bind(InboundEmailProcessor::class, fn () => new class($called) implements InboundEmailProcessor
    {
        public function __construct(private bool &$called) {}

        public function processMessagesFromAllFolders() {}

        public function processMessage($content = null): void
        {
            $this->called = true;
        }
    });

    config(['mail-receiver.inbound_token' => 'secret-inbound-token']);
    $url = '/'.trim((string) config('app.admin_path', 'admin'), '/').'/mail/inbound-parse';

    test()->withHeader('X-Inbound-Token', 'secret-inbound-token')
        ->post($url, ['email' => ''])
        ->assertStatus(422);

    test()->withHeader('X-Inbound-Token', 'secret-inbound-token')
        ->post($url, ['email' => ['nested' => 'array']])
        ->assertStatus(422);

    expect($called)->toBeFalse();

    // Verify webklex-imap mode returns 422 on invalid payload and 503 on valid payload without opening an IMAP socket
    app()->forgetInstance(InboundEmailProcessor::class);
    config(['mail-receiver.default' => 'webklex-imap']);

    test()->withHeader('X-Inbound-Token', 'secret-inbound-token')
        ->post($url, ['email' => ''])
        ->assertStatus(422);

    test()->withHeader('X-Inbound-Token', 'secret-inbound-token')
        ->post($url, ['email' => "From: a@b.test\r\nTo: c@d.test\r\nSubject: Hi\r\n\r\nBody"])
        ->assertStatus(503);
});

it('returns clean 404 responses on missing WebForm JS and admin preview routes and prevents caching form.js', function () {
    $admin = getDefaultAdmin();

    test()->get('/web-forms/forms/nonexistent-form-token/form.js')
        ->assertNotFound();

    test()->actingAs($admin, 'user')
        ->get('/web-forms/form/99999999/form.html')
        ->assertNotFound();

    $webForm = WebForm::create([
        'form_id' => 'wf_'.bin2hex(random_bytes(6)),
        'title' => 'Enterprise Contact',
        'description' => 'Reach out',
        'submit_button_label' => 'Submit',
        'submit_success_action' => 'message',
        'submit_success_content' => 'Received',
        'create_lead' => 1,
    ]);

    $response = test()->get('/web-forms/forms/'.$webForm->form_id.'/form.js')
        ->assertOk();

    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store');
});

it('executes add_tag and add_note_as_activity automations during anonymous WebForm lead submission without crashing', function () {
    getDefaultAdmin();
    $pipeline = securityTestPipeline();

    $webForm = WebForm::create([
        'form_id' => 'wf_auto_'.bin2hex(random_bytes(6)),
        'title' => 'Automation Form',
        'description' => 'Public lead form',
        'submit_button_label' => 'Send',
        'submit_success_action' => 'message',
        'submit_success_content' => 'Thank you',
        'create_lead' => 1,
        'lead_pipeline_id' => $pipeline->id,
    ]);

    foreach (['leads.title', 'persons.name', 'persons.emails'] as $idx => $qualified) {
        [$entityType, $code] = explode('.', $qualified, 2);
        $attribute = webFormAttributeFor($entityType, $code);

        WebFormAttribute::create([
            'name' => $code,
            'is_required' => 0,
            'is_hidden' => 0,
            'sort_order' => $idx + 1,
            'attribute_id' => $attribute->id,
            'web_form_id' => $webForm->id,
        ]);
    }

    Workflow::create([
        'name' => 'Public WebForm Tagger',
        'description' => 'Tag and note anonymous leads',
        'entity_type' => 'leads',
        'event' => 'lead.create.after',
        'condition_type' => 'and',
        'conditions' => [],
        'actions' => [
            ['id' => 'add_tag', 'value' => 'webform-inbound'],
            ['id' => 'add_note_as_activity', 'value' => 'Captured via public WebForm'],
        ],
    ]);

    test()->post('/web-forms/forms/'.$webForm->id, [
        'leads' => ['title' => 'Enterprise Inbound Inquiry'],
        'persons' => [
            'name' => 'Dana Prospect',
            'emails' => [['value' => 'dana-'.bin2hex(random_bytes(4)).'@example.invalid', 'label' => 'work']],
        ],
    ])->assertOk();

    $lead = Lead::query()->where('title', 'Enterprise Inbound Inquiry')->latest('id')->firstOrFail();

    expect($lead->tags()->where('name', 'webform-inbound')->exists())->toBeTrue()
        ->and($lead->activities()->where('comment', 'Captured via public WebForm')->exists())->toBeTrue();
});

it('dispatches lead.update.after and sets closed_at when massUpdate moves leads to a won stage', function () {
    $admin = getDefaultAdmin();
    $pipeline = securityTestPipeline();

    $wonStage = $pipeline->stages()->where('code', 'won')->first()
        ?? $pipeline->stages()->create([
            'name' => 'Won',
            'code' => 'won',
            'probability' => 100,
            'sort_order' => 99,
        ]);

    webFormAttributeFor('leads', 'lead_pipeline_id');
    webFormAttributeFor('leads', 'lead_pipeline_stage_id');

    $lead = Lead::create([
        'title' => 'Mass Update Deal',
        'lead_value' => 12500,
        'status' => 1,
        'user_id' => $admin->id,
        'lead_pipeline_id' => $pipeline->id,
        'lead_pipeline_stage_id' => $pipeline->stages()->first()->id,
    ]);

    $afterEvents = [];
    Event::listen('lead.update.after', function ($updatedLead) use (&$afterEvents) {
        $afterEvents[] = $updatedLead->id;
    });

    test()->actingAs($admin, 'user')
        ->postJson(route('admin.leads.mass_update'), [
            'indices' => [$lead->id],
            'value' => $wonStage->id,
        ])
        ->assertOk();

    $lead->refresh();

    expect($afterEvents)->toBe([$lead->id])
        ->and($lead->lead_pipeline_stage_id)->toBe($wonStage->id)
        ->and($lead->closed_at)->not->toBeNull();
});

it('sends OpenRouter image_url blocks with HTTP timeout instead of double-base64 text in MagicAIService', function () {
    Http::fake([
        MagicAIService::OPEN_ROUTER_URL => Http::response([
            'choices' => [
                ['message' => ['content' => '{"status":1,"title":"Extracted Card"}']],
            ],
        ], 200),
    ]);

    DB::table('core_config')->updateOrInsert(
        ['code' => 'general.magic_ai.settings.model'],
        ['value' => 'openai/gpt-4o-mini']
    );
    DB::table('core_config')->updateOrInsert(
        ['code' => 'general.magic_ai.settings.api_key'],
        ['value' => 'sk-or-test-key']
    );

    // 1x1 transparent PNG
    $pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    $result = MagicAIService::extractDataFromFile($pngBase64);

    expect($result)->toHaveKey('choices');

    Http::assertSent(function ($request) use ($pngBase64) {
        $content = $request['messages'][1]['content'] ?? [];

        return ($content[1]['type'] ?? null) === 'image_url'
            && ($content[1]['image_url']['url'] ?? null) === 'data:image/png;base64,'.$pngBase64;
    });
});

it('provides Vietnamese installer translations without Portuguese strings and registers all 10 CLI installer locales', function () {
    $viInstaller = Arr::dot((require base_path('packages/Webkul/Installer/src/Resources/lang/vi/app.php'))['installer']);

    foreach ($viInstaller as $text) {
        expect($text)->not->toContain('Confirmar Senha')
            ->not->toContain('Configuração da Loja')
            ->not->toContain('Bem-vindo');
    }

    $command = app(Installer::class);
    $prop = new ReflectionProperty($command, 'locales');
    $prop->setAccessible(true);
    $locales = array_keys($prop->getValue($command));

    expect($locales)->toEqualCanonicalizing(['ar', 'en', 'es', 'fa', 'ja', 'ko', 'pt_BR', 'tr', 'vi', 'zh_CN']);
});
