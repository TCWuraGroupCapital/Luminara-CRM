<?php

namespace Webkul\Agent\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;
use Webkul\Agent\Actions\AgentAction;
use Webkul\Agent\Http\Middleware\AuthenticateAgentToken;
use Webkul\Agent\Models\AgentActionCall;
use Webkul\Agent\Models\AgentPassport;
use Webkul\Security\Support\SecurityAuditRecorder;
use Webkul\User\Models\User;

/**
 * Resolves and enforces Agent Passport scope, daily call budget, and trust policy.
 * Legacy Sanctum `agent` tokens without a passport row return null and keep v1 behavior.
 */
final class PassportGuard
{
    private ?AgentPassport $cliPassport = null;

    /**
     * Bind a passport for the duration of a CLI callback where no Sanctum bearer token is present.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withPassport(?AgentPassport $passport, callable $callback): mixed
    {
        $previous = $this->cliPassport;
        $this->cliPassport = $passport;

        try {
            return $callback();
        } finally {
            $this->cliPassport = $previous;
        }
    }

    public function resolve(?User $actor = null): ?AgentPassport
    {
        if ($this->cliPassport !== null) {
            return $this->cliPassport;
        }

        $actor ??= auth()->guard('user')->user();

        if (! $actor instanceof User || ! Schema::hasTable('agent_passports')) {
            return null;
        }

        $token = $actor->currentAccessToken();

        // @phpstan-ignore instanceof.alwaysTrue
        if (! $token instanceof PersonalAccessToken) {
            return null;
        }

        return AgentPassport::query()
            ->where('token_id', $token->getKey())
            ->first();
    }

    public function check(AgentPassport $passport, AgentAction $action): ?ActionOutcome
    {
        if (! $passport->status) {
            return ActionOutcome::error(
                403,
                'passport_revoked',
                "Agent passport [{$passport->slug}] is inactive.",
                ['Ask the workspace owner to re-enable this passport or issue a new one.']
            );
        }

        if (! $passport->allowsAction($action->name())) {
            return ActionOutcome::error(
                403,
                'passport_scope_denied',
                "Agent passport [{$passport->slug}] is not permitted to call [{$action->name()}].",
                ['Read GET /api/agent/context or ask the workspace owner to widen this passport scope.']
            );
        }

        $usedToday = AgentActionCall::query()
            ->where('agent_passport_id', $passport->id)
            ->where('created_at', '>=', now()->utc()->startOfDay())
            ->count() + (int) Cache::get(self::dryRunCacheKey($passport->id), 0);

        if ($usedToday >= $passport->daily_call_limit) {
            return ActionOutcome::error(
                429,
                'passport_budget_exceeded',
                "Agent passport [{$passport->slug}] reached its daily call limit ({$passport->daily_call_limit}).",
                ['Wait until the next day or ask the workspace owner to increase daily_call_limit.']
            );
        }

        return null;
    }

    public static function dryRunCacheKey(int $passportId, ?string $utcDate = null): string
    {
        $date = $utcDate ?? now()->utc()->toDateString();

        return "agent.passport.dry_run.{$passportId}.{$date}";
    }

    public function recordDryRun(AgentPassport $passport): void
    {
        $key = self::dryRunCacheKey($passport->id);
        $next = ((int) Cache::get($key, 0)) + 1;
        Cache::put($key, $next, now()->utc()->endOfDay()->addMinute());
    }

    /**
     * Issue or rotate a scoped Agent Passport + Sanctum bearer token.
     *
     * @param  array{
     *     name?: ?string,
     *     allowed_actions?: array<int, string>|null,
     *     daily_call_limit?: int,
     *     auto_approve_value_ceiling?: ?float,
     *     trust_mode?: string,
     *     webhook_url?: ?string,
     *     days?: int
     * }  $options
     * @return array{passport: AgentPassport, token: string, webhook_secret: ?string, expires_at: ?string}
     */
    public function issue(User $user, string $slug, array $options = []): array
    {
        $normalizedSlug = strtolower(trim($slug));
        $days = max(1, min(365, (int) ($options['days'] ?? 90)));
        $webhookUrl = isset($options['webhook_url']) && trim((string) $options['webhook_url']) !== ''
            ? trim((string) $options['webhook_url'])
            : null;

        /** @var AgentPassport|null $existing */
        $existing = AgentPassport::query()
            ->where('user_id', $user->id)
            ->where('slug', $normalizedSlug)
            ->first();

        if ($existing?->token_id) {
            PersonalAccessToken::query()->whereKey($existing->token_id)->delete();
        }

        $newToken = $user->createToken(
            "passport:{$normalizedSlug}",
            [AuthenticateAgentToken::ABILITY],
            now()->addDays($days)
        );

        $webhookSecret = null;

        if ($webhookUrl !== null) {
            $webhookSecret = ($existing !== null && $existing->webhook_url === $webhookUrl && filled($existing->webhook_secret))
                ? $existing->webhook_secret
                : bin2hex(random_bytes(24));
        }

        $label = isset($options['name']) && trim((string) $options['name']) !== ''
            ? trim((string) $options['name'])
            : ucwords(str_replace(['-', '_'], ' ', $normalizedSlug));

        /** @var AgentPassport $passport */
        $passport = AgentPassport::query()->updateOrCreate(
            ['user_id' => $user->id, 'slug' => $normalizedSlug],
            [
                'token_id' => $newToken->accessToken->getKey(),
                'name' => $label,
                'allowed_actions' => $options['allowed_actions'] ?? null,
                'daily_call_limit' => max(1, (int) ($options['daily_call_limit'] ?? 1000)),
                'auto_approve_value_ceiling' => $options['auto_approve_value_ceiling'] ?? null,
                'trust_mode' => (string) ($options['trust_mode'] ?? AgentPassport::TRUST_STANDARD),
                'webhook_url' => $webhookUrl,
                'webhook_secret' => $webhookSecret,
                'status' => true,
            ]
        );

        $expiresAt = $newToken->accessToken->expires_at?->toIso8601String();
        $userEmail = (string) ($user->getAttribute('email') ?? '');

        SecurityAuditRecorder::record('agent.passport.issued', 'agent_passport', $passport->id, [
            'slug' => $passport->slug,
            'trust_mode' => $passport->trust_mode,
            'daily_call_limit' => $passport->daily_call_limit,
            'expires_at' => $expiresAt,
        ], $user->id, $userEmail);

        return [
            'passport' => $passport,
            'token' => $newToken->plainTextToken,
            'webhook_secret' => $webhookSecret,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @param  array<int, mixed>|string  $rawScopes
     * @return array<int, string>|null
     */
    public static function parseScopes(array|string $rawScopes): ?array
    {
        $entries = is_array($rawScopes) ? $rawScopes : [$rawScopes];
        $items = [];

        foreach ($entries as $entry) {
            foreach (explode(',', (string) $entry) as $part) {
                $trimmed = trim($part);

                if ($trimmed !== '') {
                    $items[] = $trimmed;
                }
            }
        }

        if ($items === [] || in_array('*', $items, true)) {
            return null;
        }

        return array_values(array_unique($items));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function requiresApproval(?AgentPassport $passport, AgentAction $action, array $validated): bool
    {
        $needsApproval = $action->requiresApproval($validated);

        if ($passport === null) {
            return $needsApproval;
        }

        if ($passport->trust_mode === AgentPassport::TRUST_ALWAYS_CONFIRM && $action->isMutating()) {
            return true;
        }

        if (
            $passport->trust_mode === AgentPassport::TRUST_EARNED_AUTO
            && $needsApproval
            && $action->approval() === AgentAction::APPROVAL_CONDITIONAL
            && $passport->auto_approve_value_ceiling !== null
        ) {
            $value = $action->estimatedValue($validated);

            if ($value !== null && $value <= $passport->auto_approve_value_ceiling) {
                return false;
            }
        }

        return $needsApproval;
    }
}
