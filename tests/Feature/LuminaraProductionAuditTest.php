<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Webkul\Installer\Helpers\DatabaseManager;
use Webkul\Installer\Http\Middleware\CanInstall;

it('sends error pages with their real status code when debug is off', function () {
    config(['app.debug' => false]);

    $this->get('/no-such-page-anywhere')->assertStatus(404)->assertSee('404');
});

it('allows the /up healthcheck endpoint without redirecting even before installation completes', function () {
    $originalStorage = app()->storagePath();
    $tempStorage = sys_get_temp_dir().'/luminara-health-'.bin2hex(random_bytes(6));
    mkdir($tempStorage, 0777, true);
    app()->useStoragePath($tempStorage);

    try {
        app()->instance(DatabaseManager::class, new class extends DatabaseManager
        {
            public function isInstallationCompleted(): bool
            {
                return false;
            }

            public function isInstalled()
            {
                return false;
            }
        });

        $reached = false;
        $response = (new CanInstall)->handle(Request::create('/up', 'GET'), function () use (&$reached) {
            $reached = true;

            return response('ok', 200);
        });

        expect($reached)->toBeTrue()
            ->and($response->getStatusCode())->toBe(200);
    } finally {
        app()->useStoragePath($originalStorage);
        @rmdir($tempStorage);
    }

    test()->get('/up')->assertOk();
});

it('honors Cloudflare X-Forwarded-Proto when trustedproxy.proxies trusts the ingress address', function () {
    $previousConfig = config('trustedproxy.proxies');
    config(['trustedproxy.proxies' => '127.0.0.1, 172.16.0.0/12']);

    try {
        $request = Request::create('http://crm.luminaradigital.example/admin/login', 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.45',
        ]);

        (new TrustProxies)->handle($request, fn (Request $req) => response('ok'));

        expect($request->isSecure())->toBeTrue()
            ->and($request->getClientIp())->toBe('203.0.113.45');
    } finally {
        config(['trustedproxy.proxies' => $previousConfig]);
        Request::setTrustedProxies([], -1);
    }
});

it('renders anonymous auth views cleanly in light and dark modes without duplicate logo IDs', function () {
    foreach ([false, true] as $darkMode) {
        $client = $darkMode ? test()->withUnencryptedCookie('dark_mode', '1') : test();

        $loginHtml = $client->get(route('admin.session.create'))
            ->assertOk()
            ->getContent();

        expect($loginHtml)->not->toContain('id="logo-image"')
            ->and($loginHtml)->not->toContain('bagisto.png')
            ->and($loginHtml)->toContain(trans('admin::app.components.layouts.powered-by.tagline'));

        if ($darkMode) {
            expect($loginHtml)->toMatch('/<html[^>]*class="[^"]*\bdark\b/');
        }

        $client->get(route('admin.forgot_password.create'))
            ->assertOk();

        $client->get(route('admin.reset_password.create', ['token' => 'qa-reset-token']))
            ->assertOk();
    }
});

it('enforces 10-locale translation parity for Experiment and Admin Luminara branding keys', function () {
    $locales = ['ar', 'en', 'es', 'fa', 'ja', 'ko', 'pt_BR', 'tr', 'vi', 'zh_CN'];

    $enExperiment = require base_path('packages/Webkul/Experiment/src/Resources/lang/en/app.php');
    $expectedExperimentKeys = array_keys(Arr::dot($enExperiment));

    expect($expectedExperimentKeys)->not->toBeEmpty();

    foreach ($locales as $locale) {
        $expFile = base_path("packages/Webkul/Experiment/src/Resources/lang/{$locale}/app.php");
        expect(is_file($expFile))->toBeTrue("Missing Experiment translation file for locale [{$locale}]");

        $translations = Arr::dot(require $expFile);

        foreach ($expectedExperimentKeys as $key) {
            expect($translations)->toHaveKey($key)
                ->and($translations[$key])->toBeString()->not->toBeEmpty();
        }

        $adminFile = base_path("packages/Webkul/Admin/src/Resources/lang/{$locale}/app.php");
        expect(is_file($adminFile))->toBeTrue("Missing Admin translation file for locale [{$locale}]");

        $adminTranslations = Arr::dot(require $adminFile);
        expect($adminTranslations)->toHaveKey('components.layouts.powered-by.tagline')
            ->and($adminTranslations['components.layouts.powered-by.tagline'])->toBeString()->not->toBeEmpty();
    }
});
