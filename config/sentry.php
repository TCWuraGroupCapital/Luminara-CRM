<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sentry
    |--------------------------------------------------------------------------
    |
    | The SDK is installed and stays inactive until SENTRY_LARAVEL_DSN is set.
    | A generic SENTRY_DSN is ignored on purpose. Spotlight is off. Metrics
    | stay off unless SENTRY_ENABLE_METRICS is true. No personal data is sent
    | by default.
    |
    */

    'dsn' => env('SENTRY_LARAVEL_DSN') ?: null,

    'spotlight' => false,

    'release' => env('SENTRY_RELEASE'),

    'environment' => env('SENTRY_ENVIRONMENT'),

    'sample_rate' => env('SENTRY_SAMPLE_RATE') === null ? 1.0 : (float) env('SENTRY_SAMPLE_RATE'),

    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE') === null ? null : (float) env('SENTRY_TRACES_SAMPLE_RATE'),

    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE') === null ? null : (float) env('SENTRY_PROFILES_SAMPLE_RATE'),

    'enable_logs' => env('SENTRY_ENABLE_LOGS', false),

    'enable_metrics' => env('SENTRY_ENABLE_METRICS', false),

    'send_default_pii' => false,

    'ignore_transactions' => [
        '/up',
        '/health/ready',
        '/^\/(horizon|telescope|_debugbar)(\/|$)/',
    ],

];
