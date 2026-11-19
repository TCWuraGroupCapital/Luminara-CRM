<?php

namespace Webkul\Security\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Webkul\Security\Listeners\SecurityEventSubscriber;
use Webkul\Security\Models\UserMfaSetting;

class EnforceAdminMfa
{
    private const CHALLENGE_EXEMPT_ROUTES = [
        'admin.security.mfa.challenge',
        'admin.security.mfa.verify',
        'admin.session.create',
        'admin.session.store',
        'admin.session.destroy',
    ];

    private const ENROLLMENT_EXEMPT_ROUTES = [
        'admin.security.mfa.setup',
        'admin.security.mfa.enable',
        'admin.session.create',
        'admin.session.store',
        'admin.session.destroy',
    ];

    /**
     * Gate session-authenticated CRM routes behind second-factor verification whenever the user has MFA enabled or policy requires MFA.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! auth()->guard('user')->check()) {
            return $next($request);
        }

        $adminPrefix = trim((string) config('app.admin_path', 'admin'), '/');
        $isAdminPath = $adminPrefix === '' || $request->is($adminPrefix) || $request->is($adminPrefix.'/*');
        $routeMiddleware = $request->route()?->gatherMiddleware() ?? [];
        $isProtectedStaffRoute = $isAdminPath
            || in_array('user', $routeMiddleware, true)
            || in_array('auth:sanctum', $routeMiddleware, true)
            || in_array(AuthenticateAdminSession::class, $routeMiddleware, true);

        if (! $isProtectedStaffRoute) {
            return $next($request);
        }

        if (! Schema::hasTable('user_mfa_settings')) {
            return $next($request);
        }

        $userId = (int) auth()->guard('user')->id();
        $routeName = (string) Route::currentRouteName();
        $setting = UserMfaSetting::query()->where('user_id', $userId)->first();

        if ($setting && $setting->mfa_enabled) {
            if ((int) $request->session()->get(SecurityEventSubscriber::MFA_SESSION_KEY, 0) === $userId) {
                return $next($request);
            }

            if ($routeName !== '' && in_array($routeName, self::CHALLENGE_EXEMPT_ROUTES, true)) {
                return $next($request);
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => trans('security::app.mfa.verification-required'),
                    'mfa_required' => true,
                ], 403);
            }

            if ($request->isMethod('GET')) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect()->route('admin.security.mfa.challenge');
        }

        if ((bool) config('security.require_admin_mfa', false)) {
            if ($routeName !== '' && in_array($routeName, self::ENROLLMENT_EXEMPT_ROUTES, true)) {
                return $next($request);
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => trans('security::app.mfa.enrollment-required'),
                    'mfa_enrollment_required' => true,
                ], 403);
            }

            return redirect()->route('admin.security.mfa.setup');
        }

        return $next($request);
    }
}
