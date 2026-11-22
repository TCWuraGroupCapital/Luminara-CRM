<?php

namespace Webkul\Security\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Webkul\Security\Support\AuditContext;

/**
 * Gives the request a stable id and echoes it back, so audit rows can be grouped without reading the body.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get('X-Request-Id');
        $id = is_string($incoming) && preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $incoming) === 1
            ? $incoming
            : (string) Str::uuid();

        AuditContext::set($id);
        $request->attributes->set('luminara_request_id', $id);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
