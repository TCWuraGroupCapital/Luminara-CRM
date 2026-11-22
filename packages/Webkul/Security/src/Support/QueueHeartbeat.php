<?php

namespace Webkul\Security\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Timestamp written by the scheduler so readiness can tell that queue work is running.
 */
class QueueHeartbeat
{
    public const CACHE_KEY = 'luminara:queue:heartbeat';

    public function record(): string
    {
        $stamp = gmdate('Y-m-d\TH:i:s\Z');

        Cache::forever(self::CACHE_KEY, $stamp);

        return $stamp;
    }

    public function read(): ?string
    {
        $value = Cache::get(self::CACHE_KEY);

        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
