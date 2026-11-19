<?php

namespace Webkul\Experiment\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExperimentCorsMiddleware
{
    /**
     * Apply strict allow-listed CORS headers to public experiment SDK endpoints without altering global cors.paths.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = trim((string) $request->headers->get('Origin', ''));
        $allowedOrigins = array_values(array_filter(
            (array) config('cors.allowed_origins', []),
            fn ($item): bool => is_string($item) && $item !== '' && $item !== '*'
        ));

        $isTrustedOrigin = $origin !== '' && in_array($origin, $allowedOrigins, true);

        if ($request->isMethod('OPTIONS')) {
            $response = new Response('', 204);
            $response->headers->set('Vary', 'Origin');

            if ($isTrustedOrigin) {
                $this->applyCorsHeaders($response, $origin);
            }

            return $response;
        }

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('Vary', 'Origin');

        if ($isTrustedOrigin) {
            $this->applyCorsHeaders($response, $origin);
        }

        return $response;
    }

    private function applyCorsHeaders(Response $response, string $origin): void
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type, X-Requested-With, X-Experiment-Token');
        $response->headers->set('Vary', 'Origin');
    }
}
