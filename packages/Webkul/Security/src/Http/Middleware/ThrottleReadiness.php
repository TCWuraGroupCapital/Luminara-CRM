<?php

namespace Webkul\Security\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Limits probes per address using the file cache, so a Redis outage still
 * lets the readiness report explain that Redis is down.
 */
class ThrottleReadiness
{
    public function handle(Request $request, Closure $next): Response
    {
        $max = max(1, (int) config('security.health_ready_rate_per_minute', 120));
        $address = (string) ($request->ip() ?? 'unknown');
        $key = 'luminara:health:ready:'.hash('sha256', $address).':'.gmdate('YmdHi');

        try {
            $store = Cache::store('file');
            $hits = (int) $store->get($key, 0);

            if ($hits >= $max) {
                return response()
                    ->json([
                        'status' => 'fail',
                        'message' => 'Too many requests.',
                    ], 429)
                    ->header('Cache-Control', 'no-store')
                    ->header('Retry-After', '60');
            }

            $store->put($key, $hits + 1, 90);
        } catch (Throwable) {
            // A broken file cache must not hide the dependency report.
        }

        return $next($request);
    }
}
