<?php

namespace Webkul\Security\Support;

use Illuminate\Support\Str;

/**
 * The id that ties every audit row written for one HTTP request, console command, or queued job.
 */
final class AuditContext
{
    /**
     * Callers may send their own id. Anything else is replaced so the column cannot hold free text.
     */
    public const PATTERN = '/^[A-Za-z0-9._:-]{8,64}$/';

    private static ?string $requestId = null;

    public static function isValid(string $value): bool
    {
        return preg_match(self::PATTERN, $value) === 1;
    }

    public static function accept(mixed $incoming): string
    {
        if (is_string($incoming) && self::isValid($incoming)) {
            return $incoming;
        }

        return (string) Str::uuid();
    }

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
