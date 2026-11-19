<?php

namespace Webkul\Security\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAdminSession
{
    /**
     * Require an active authenticated admin session for self-service MFA routes without ACL role gating.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->guard('user')->check()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('admin.session.create');
        }

        $user = auth()->guard('user')->user();

        if (! (bool) ($user->status ?? false)) {
            auth()->guard('user')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('admin.session.create');
        }

        return $next($request);
    }
}
