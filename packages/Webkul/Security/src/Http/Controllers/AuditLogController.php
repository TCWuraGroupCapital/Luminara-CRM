<?php

namespace Webkul\Security\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Security\Models\SecurityAuditLog;
use Webkul\Security\Support\AuditContext;

class AuditLogController extends Controller
{
    private const ACTIONS = ['created', 'updated', 'deleted', 'exported'];

    public function index(Request $request): View|JsonResponse
    {
        abort_unless(
            bouncer()->hasPermission('settings.other_settings.security_audit'),
            401,
            trans('admin::app.errors.unauthorized')
        );

        $logs = $this->filtered($request)
            ->orderByDesc('id')
            ->paginate(min(100, max(10, (int) $request->query('per_page', 25))))
            ->withQueryString();

        if ($request->expectsJson() || $request->ajax()) {
            return new JsonResponse($logs);
        }

        return view('security::audit.index', ['logs' => $logs]);
    }

    /**
     * @return Builder<SecurityAuditLog>
     */
    private function filtered(Request $request): Builder
    {
        $query = SecurityAuditLog::query();

        $event = $this->token($request, 'event', 96);

        if ($event !== null) {
            $query->where('event', $event);
        }

        $entity = $this->token($request, 'entity', 96);

        if ($entity !== null) {
            $query->where('auditable_type', $entity);
        }

        $action = $this->token($request, 'action', 32);

        if ($action !== null && in_array($action, self::ACTIONS, true)) {
            $query->where('event', 'like', '%.'.$action);
        }

        $actor = $this->plain($request, 'actor', 191);

        if ($actor !== null) {
            $query->where(function (Builder $inner) use ($actor): void {
                $inner->where('actor_email', $actor);

                if (preg_match('/^[1-9][0-9]{0,9}$/', $actor) === 1) {
                    $inner->orWhere('actor_id', (int) $actor);
                }
            });
        }

        $workspace = $this->token($request, 'workspace', 191);

        if ($workspace !== null) {
            $query->where('workspace', $workspace);
        }

        $requestId = $this->plain($request, 'request_id', 64);

        if ($requestId !== null && AuditContext::isValid($requestId)) {
            $query->where('request_id', $requestId);
        }

        return $query;
    }

    private function token(Request $request, string $key, int $max): ?string
    {
        $value = $this->plain($request, $key, $max);

        if ($value === null || preg_match('/^[A-Za-z0-9._:-]{1,'.$max.'}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private function plain(Request $request, string $key, int $max): ?string
    {
        $value = $request->query($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || mb_strlen($value) > $max) {
            return null;
        }

        return $value;
    }
}
