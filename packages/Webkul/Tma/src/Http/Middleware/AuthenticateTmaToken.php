<?php

namespace Webkul\Tma\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Webkul\Agent\Support\ActionOutcome;
use Webkul\User\Models\User;

class AuthenticateTmaToken
{
    public const ABILITY = 'tma';

    /**
     * Admit only a stateless Sanctum bearer token scoped to `tma`, then bind its owner onto the
     * `user` guard so Bouncer, data scoping, and ledger attribution run as that exact CRM user.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! is_string($bearer) || $bearer === '') {
            return $this->unauthenticatedResponse();
        }

        $token = PersonalAccessToken::findToken($bearer);

        if ($token === null) {
            return $this->unauthenticatedResponse();
        }

        $tokenable = $token->tokenable;
        $user = $tokenable instanceof User ? $tokenable : null;

        $expiration = config('sanctum.expiration');
        $expired = ($token->expires_at !== null && $token->expires_at->isPast())
            || (is_numeric($expiration) && (int) $expiration > 0 && $token->created_at?->lte(now()->subMinutes((int) $expiration)));

        if (
            ! $user instanceof User
            || ! $user->getAttribute('status')
            || $expired
            || ! $token->can(self::ABILITY)
        ) {
            return $this->unauthenticatedResponse();
        }

        $user->withAccessToken($token);
        auth()->guard('user')->setUser($user);

        return $next($request);
    }

    private function unauthenticatedResponse(): Response
    {
        $outcome = ActionOutcome::error(401, 'unauthenticated', 'A valid Telegram Mini App session token is required.', [
            'Exchange signed Telegram initData at POST /api/tma/session.',
        ]);

        return response()->json($outcome->body, $outcome->httpStatus);
    }
}
