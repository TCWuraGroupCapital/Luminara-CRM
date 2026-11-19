<?php

namespace Webkul\Security\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Security\Models\SecurityAuditLog;

class AuditLogController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        abort_unless(
            bouncer()->hasPermission('settings.other_settings.security_audit'),
            401,
            trans('admin::app.errors.unauthorized')
        );

        $query = SecurityAuditLog::query()->orderByDesc('id');

        if ($request->filled('event')) {
            $query->where('event', (string) $request->query('event'));
        }

        $logs = $query->paginate(min(100, max(10, (int) $request->query('per_page', 25))));

        if ($request->expectsJson() || $request->ajax()) {
            return new JsonResponse($logs);
        }

        return view('security::audit.index', ['logs' => $logs]);
    }
}
