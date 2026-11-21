<?php

namespace Webkul\Security\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a Content-Security-Policy with every HTML page.
 *
 * The admin needs 'unsafe-inline' and 'unsafe-eval' today (Vue compiles in-page templates and
 * Blade prints inline scripts), so the policy's value is in what it shuts out: scripts from any
 * host but ours and the two CDNs the pages use, plugins, <base> hijacking, forms posting to
 * other sites, and framing by other sites.
 *
 * Two pages differ: the web-form page is built to be embedded on customers' own sites, so it
 * gets no policy (as before), and the Telegram mini app may be framed by Telegram and loads
 * Telegram's script.
 */
class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $mode = (string) config('security.csp', 'enforce');

        if ($mode === 'off' || $response->headers->has('Content-Security-Policy') || Vite::isRunningHot()) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', 'text/html');

        if (! str_contains($contentType, 'text/html') || $this->isEmbeddableWebForm($request)) {
            return $response;
        }

        $header = $mode === 'report-only' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';

        $response->headers->set($header, $this->policy($request->is('tma', 'tma/*')));

        return $response;
    }

    public function policy(bool $telegram = false): string
    {
        $formAction = trim("'self' ".(string) config('security.csp_form_action_extra', ''));

        $directives = [
            'default-src' => "'self'",
            'script-src' => "'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com".($telegram ? ' https://telegram.org' : ''),
            'style-src' => "'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://npmcdn.com https://unpkg.com",
            'font-src' => "'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
            'img-src' => "'self' data: blob: https:",
            'media-src' => "'self' data: blob:",
            'connect-src' => "'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            'worker-src' => "'self' blob:",
            'frame-src' => "'self'",
            'object-src' => "'none'",
            'base-uri' => "'self'",
            'form-action' => $formAction,
            'frame-ancestors' => $telegram ? "'self' https://web.telegram.org https://*.telegram.org" : "'self'",
        ];

        return collect($directives)->map(static fn (string $value, string $name): string => "{$name} {$value}")->implode('; ');
    }

    private function isEmbeddableWebForm(Request $request): bool
    {
        return $request->isMethod('GET') && preg_match('#^web-forms/forms/[A-Za-z0-9_-]+/form\.html$#', $request->path()) === 1;
    }
}
