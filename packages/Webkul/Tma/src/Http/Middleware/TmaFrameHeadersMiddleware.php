<?php

namespace Webkul\Tma\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TmaFrameHeadersMiddleware
{
    /**
     * Allow `/tma` to render inside Telegram Web / Desktop / Mobile Mini App containers while blocking
     * arbitrary third-party framing.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->remove('X-Frame-Options');
        $response->headers->set(
            'Content-Security-Policy',
            "frame-ancestors 'self' https://web.telegram.org https://*.telegram.org"
        );
        $response->headers->set(
            'Permissions-Policy',
            'camera=(self), microphone=(self)'
        );

        return $response;
    }
}
