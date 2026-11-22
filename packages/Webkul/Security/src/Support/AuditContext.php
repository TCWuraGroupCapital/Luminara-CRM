<?php

namespace Webkul\Security\Support;

use Illuminate\Support\Str;

/**
 * The id that ties every audit row written for one HTTP request, console command, or queued job.
 */
final class AuditContext
{
    private static ?string $requestId = null;

    public static function set(?string $requestId): void
    {
        self::$requestId = $requestId !== null && $requestId !== '' ? $requestId : null;
    }

    public static function get(): ?string
    {
        return self::$requestId;
    }

    public static function id(): string
    {
        if (self::$requestId === null) {
            self::$requestId = (string) Str::uuid();
        }

        return self::$requestId;
    }
}
