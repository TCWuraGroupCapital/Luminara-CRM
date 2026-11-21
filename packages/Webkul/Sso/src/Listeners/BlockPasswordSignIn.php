<?php

namespace Webkul\Sso\Listeners;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Http\Exceptions\HttpResponseException;
use Webkul\Security\Support\SecurityAuditRecorder;
use Webkul\Sso\Support\SsoSettings;

/**
 * When a business requires single sign-on for its email domains, a password sign-in for one of
 * those addresses is refused before the password is even checked.
 *
 * It runs inside the password check, after the workspace for the typed email has been chosen,
 * so it reads that business's own settings.
 */
class BlockPasswordSignIn
{
    public function handle(Attempting $event): void
    {
        if ($event->guard !== 'user' || ! config('sso.enabled')) {
            return;
        }

        $email = strtolower(trim((string) ($event->credentials['email'] ?? '')));

        if ($email === '') {
            return;
        }

        $settings = SsoSettings::current();

        if (! $settings->usable() || ! $settings->required || ! $settings->coversEmail($email)) {
            return;
        }

        SecurityAuditRecorder::record('auth.password_blocked_sso', 'user', $email, []);

        throw new HttpResponseException(
            redirect()->route('admin.sso.create')->withInput(['email' => $email])->with('warning', trans('sso::app.errors.use-sso'))
        );
    }
}
