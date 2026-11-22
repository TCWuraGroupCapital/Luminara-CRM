<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Sentry\Laravel\Integration;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Security\Support\SentryGate;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_PROTO |
                Request::HEADER_X_FORWARDED_PORT
        );

        $middleware->append(CanInstall::class);

        $middleware->encryptCookies(except: [
            'dark_mode',
            'sidebar_collapsed',
        ]);

        /**
         * The public web form submission endpoint is embedded cross-origin on external
         * sites and never carries the admin prefix. The inbound-parse webhook exemption
         * is registered in AppServiceProvider::boot() from config('app.admin_path') so
         * custom admin paths remain exempt even when config:cache is enabled.
         */
        $middleware->validateCsrfTokens(except: [
            'web-forms/forms/*',
        ]);

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // The SDK ships with the app and stays dormant until SENTRY_LARAVEL_DSN is set.
        if (SentryGate::enabled() && class_exists(Integration::class)) {
            Integration::handles($exceptions);
        }
    })->create();
