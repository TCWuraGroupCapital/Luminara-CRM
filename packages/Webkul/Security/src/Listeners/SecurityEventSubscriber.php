<?php

namespace Webkul\Security\Listeners;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Webkul\Security\Support\SecurityAuditRecorder;

class SecurityEventSubscriber
{
    public const MFA_SESSION_KEY = 'security.mfa_verified_user_id';

    private static ?int $lastConfigSaveRequestId = null;

    /**
     * Enforce global per-email brute-force lockout across rotating botnet IPs before bcrypt runs.
     */
    public function handleAuthAttempting(Attempting $event): void
    {
        if ($event->guard !== 'user') {
            return;
        }

        $email = $this->normalizeEmail($event->credentials['email'] ?? null);

        if ($email === '') {
            return;
        }

        $maxAttempts = max(3, (int) config('security.max_failed_logins_per_email', 10));
        $rateKey = $this->emailRateKey($email);

        if (RateLimiter::tooManyAttempts($rateKey, $maxAttempts)) {
            $decay = max(60, (int) config('security.email_lockout_seconds', 900));

            if (Cache::add($rateKey.':lockout-logged', 1, $decay)) {
                SecurityAuditRecorder::record(
                    event: 'auth.lockout',
                    auditableType: 'user_email',
                    auditableId: $email,
                    actorEmail: $email,
                );
            }

            abort(429, 'Too many failed sign-in attempts for this account.');
        }
    }

    public function handleAuthFailed(Failed $event): void
    {
        if ($event->guard !== 'user') {
            return;
        }

        $email = $this->normalizeEmail($event->credentials['email'] ?? null);

        if ($email !== '') {
            $decay = max(60, (int) config('security.email_lockout_seconds', 900));
            RateLimiter::hit($this->emailRateKey($email), $decay);
        }

        SecurityAuditRecorder::record(
            event: 'auth.failed',
            auditableType: 'user',
            auditableId: $event->user?->getAuthIdentifier(),
            actorId: $event->user ? (int) $event->user->getAuthIdentifier() : null,
            actorEmail: $email !== '' ? $email : null,
        );
    }

    public function handleAuthLogin(Login $event): void
    {
        if ($event->guard !== 'user') {
            return;
        }

        $email = $this->normalizeEmail(data_get($event->user, 'email'));

        if ($email !== '') {
            $rateKey = $this->emailRateKey($email);
            RateLimiter::clear($rateKey);
            Cache::forget($rateKey.':lockout-logged');
        }

        if (request()->hasSession()) {
            request()->session()->forget(self::MFA_SESSION_KEY);
        }

        SecurityAuditRecorder::record(
            event: 'auth.login',
            auditableType: 'user',
            auditableId: $event->user->getAuthIdentifier(),
            actorId: (int) $event->user->getAuthIdentifier(),
            actorEmail: $email !== '' ? $email : null,
        );
    }

    public function handleAuthLogout(Logout $event): void
    {
        if ($event->guard !== 'user' || $event->user === null) {
            return;
        }

        if (request()->hasSession()) {
            request()->session()->forget(self::MFA_SESSION_KEY);
        }

        $email = $this->normalizeEmail(data_get($event->user, 'email'));

        SecurityAuditRecorder::record(
            event: 'auth.logout',
            auditableType: 'user',
            auditableId: $event->user->getAuthIdentifier(),
            actorId: (int) $event->user->getAuthIdentifier(),
            actorEmail: $email !== '' ? $email : null,
        );
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Attempting::class, [self::class, 'handleAuthAttempting']);
        $events->listen(Failed::class, [self::class, 'handleAuthFailed']);
        $events->listen(Login::class, [self::class, 'handleAuthLogin']);
        $events->listen(Logout::class, [self::class, 'handleAuthLogout']);

        $events->listen('settings.user.create.after', static function (mixed $user): void {
            SecurityAuditRecorder::record('user.created', 'user', $user->id ?? null, ['email' => $user->email ?? null]);
        });
        $events->listen('settings.user.update.after', static function (mixed $user): void {
            SecurityAuditRecorder::record('user.updated', 'user', $user->id ?? null, ['email' => $user->email ?? null, 'status' => $user->status ?? null]);
        });
        $events->listen('settings.user.delete.before', static function (mixed $id): void {
            SecurityAuditRecorder::record('user.deleted', 'user', is_scalar($id) ? $id : null);
        });

        $events->listen('settings.role.create.after', static function (mixed $role): void {
            SecurityAuditRecorder::record('role.created', 'role', $role->id ?? null, ['name' => $role->name ?? null]);
        });
        $events->listen('settings.role.update.after', static function (mixed $role): void {
            SecurityAuditRecorder::record('role.updated', 'role', $role->id ?? null, ['name' => $role->name ?? null]);
        });
        $events->listen('settings.role.delete.before', static function (mixed $id): void {
            SecurityAuditRecorder::record('role.deleted', 'role', is_scalar($id) ? $id : null);
        });

        $events->listen('core.configuration.save.after', static function (): void {
            if (! app()->runningInConsole()) {
                $reqId = spl_object_id(request());

                if (self::$lastConfigSaveRequestId === $reqId) {
                    return;
                }

                self::$lastConfigSaveRequestId = $reqId;
            }

            SecurityAuditRecorder::record('core_config.updated', 'core_config');
        });

        $events->listen('settings.experiment.create.after', static function (mixed $exp): void {
            SecurityAuditRecorder::record('experiment.created', 'experiment', $exp->id ?? null, ['code' => $exp->code ?? null]);
        });
        $events->listen('settings.experiment.update.after', static function (mixed $exp): void {
            SecurityAuditRecorder::record('experiment.updated', 'experiment', $exp->id ?? null, ['code' => $exp->code ?? null, 'status' => $exp->status ?? null]);
        });
        $events->listen('settings.experiment.delete.before', static function (mixed $id): void {
            SecurityAuditRecorder::record('experiment.deleted', 'experiment', is_scalar($id) ? $id : null);
        });

        $events->listen('lead.delete.before', static function (mixed $id): void {
            SecurityAuditRecorder::record('lead.deleted', 'lead', is_scalar($id) ? $id : null);
        });
    }

    private function normalizeEmail(mixed $email): string
    {
        return is_string($email) ? Str::lower(trim($email)) : '';
    }

    private function emailRateKey(string $normalizedEmail): string
    {
        return 'security:login-email:'.hash('sha256', $normalizedEmail);
    }
}
