<?php

namespace Webkul\Security\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Webkul\Security\Support\SecurityAuditRecorder;

/**
 * Records a successful download of CRM data that does not pass through the datagrid exporter.
 *
 * The response body is never read and never stored.
 */
class AuditDataExport
{
    /**
     * @var array<string, array{event: string, type: string, format: string}>
     */
    private const ROUTES = [
        'admin.quotes.print' => ['event' => 'quote.exported', 'type' => 'quote', 'format' => 'pdf'],
        'admin.dashboard.export_pdf' => ['event' => 'dashboard.exported', 'type' => 'dashboard', 'format' => 'pdf'],
        'admin.contacts.persons.google_export.store' => ['event' => 'person.exported', 'type' => 'person', 'format' => 'google_contacts'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $name = $request->route()?->getName();

        if (! is_string($name) || ! isset(self::ROUTES[$name])) {
            return $response;
        }

        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            return $response;
        }

        $spec = self::ROUTES[$name];
        $id = null;

        if ($spec['type'] === 'quote') {
            $raw = $request->route('id');
            $id = is_scalar($raw) ? (string) $raw : null;
        }

        SecurityAuditRecorder::record($spec['event'], $spec['type'], $id, [
            'action' => 'exported',
            'format' => $spec['format'],
            'fields' => [],
        ]);

        return $response;
    }
}
