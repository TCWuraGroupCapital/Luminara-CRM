<?php

namespace Webkul\Agent\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Webkul\Agent\Support\ActionOutcome;
use Webkul\Agent\Support\PassportGuard;
use Webkul\User\Models\User;

class AuthenticateAgentToken
{
    public const ABILITY = 'agent';

    /**
     * Admit only a bearer token issued for agent use, then place its owner on the `user` guard so
     * Bouncer, data scoping and the audit log treat the agent exactly as they treat that user.
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

        $passport = app(PassportGuard::class)->resolve($user);

        if ($passport !== null && ! $passport->status) {
            $revoked = ActionOutcome::error(
                403,
                'passport_revoked',
                "Agent passport [{$passport->slug}] is inactive.",
                ['Ask the workspace owner to re-enable this passport or issue a new one.']
            );

            return response()->json($revoked->body, $revoked->httpStatus);
        }

        return $next($request);
    }

    private function unauthenticatedResponse(): Response
    {
        $outcome = ActionOutcome::error(401, 'unauthenticated', 'A valid agent token is required.', [
            'Send the token as an Authorization: Bearer header.',
            'Ask an administrator to issue one with: php artisan luminara:agent-token <email>.',
        ]);

        return response()->json($outcome->body, $outcome->httpStatus);
    }
}
