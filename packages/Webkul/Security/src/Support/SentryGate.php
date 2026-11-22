<?php

namespace Webkul\Security\Support;

/**
 * The Sentry Laravel SDK is installed, but it must stay inactive until an
 * operator sets SENTRY_LARAVEL_DSN. A generic SENTRY_DSN does not count.
 */
class SentryGate
{
    public static function enabled(): bool
    {
        $dsn = config('sentry.dsn');

        return is_string($dsn) && trim($dsn) !== '';
    }
}
