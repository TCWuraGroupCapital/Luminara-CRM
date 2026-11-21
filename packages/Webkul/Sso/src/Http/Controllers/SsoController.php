<?php

namespace Webkul\Sso\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Webkul\Security\Listeners\SecurityEventSubscriber;
use Webkul\Security\Support\SecurityAuditRecorder;
use Webkul\Sso\Support\OidcClient;
use Webkul\Sso\Support\SsoException;
use Webkul\Sso\Support\SsoSettings;
use Webkul\User\Models\User;
use Webkul\Workspace\Models\Workspace;
use Webkul\Workspace\WorkspaceManager;

/**
 * Email-first single sign-on. The work email picks the business (as it does for passwords), and
 * that business's provider proves who the person is. Only existing, active users can sign in.
 */
class SsoController extends Controller
{
    public const SESSION_KEY = 'luminara_sso';

    /**
     * Authentication-method values (RFC 8176) that mean the provider used a second factor.
     */
    private const MFA_METHODS = ['mfa', 'otp', 'hwk', 'swk', 'sms', 'fpt', 'face', 'iris', 'retina', 'pin'];

    public function __construct(
        private OidcClient $oidc,
        private WorkspaceManager $workspaces,
    ) {}

    public function create(): View|RedirectResponse
    {
        abort_unless(config('sso.enabled'), 404);

        if (auth()->guard('user')->check()) {
            return redirect()->route('admin.dashboard.index');
        }

        return view('sso::start');
    }

    public function start(Request $request): View|RedirectResponse
    {
        abort_unless(config('sso.enabled'), 404);

        $email = strtolower(trim((string) $request->validate(['email' => 'required|email:rfc|max:190'])['email']));

        try {
            $workspace = $this->workspaceFor($email);
            $settings = $this->settingsFor($workspace, $email);

            $state = OidcClient::randomToken(40);
            $nonce = OidcClient::randomToken(40);
            $verifier = OidcClient::randomToken(64);

            $url = $this->workspaces->run($workspace, fn (): string => $this->oidc->authorizationUrl(
                $settings,
                route('admin.sso.callback'),
                $state,
                $nonce,
                $verifier,
                $email,
            ));
        } catch (SsoException $e) {
            return $this->fail($e, $email);
        }

        $request->session()->put(self::SESSION_KEY, [
            'state' => $state,
            'nonce' => $nonce,
            'verifier' => $verifier,
            'email' => $email,
            'workspace_id' => $workspace?->id,
            'started_at' => now()->getTimestamp(),
        ]);

        /**
         * A page that moves on by itself, not a redirect: a form whose response redirects to another
         * site is blocked by the CSP's form-action in Chrome.
         */
        return view('sso::redirect', ['url' => $url]);
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(config('sso.enabled'), 404);

        $pending = $request->session()->pull(self::SESSION_KEY);

        try {
            if (! is_array($pending) || ! is_string($request->query('state'))
                || ! hash_equals((string) $pending['state'], (string) $request->query('state'))) {
                throw new SsoException('expired', 'State missing or does not match.');
            }

            if (now()->getTimestamp() - (int) $pending['started_at'] > (int) config('sso.max_age_seconds', 600)) {
                throw new SsoException('expired', 'Sign-in took too long.');
            }

            if (is_string($request->query('error'))) {
                throw new SsoException('cancelled', 'Provider returned '.$request->query('error'));
            }

            if (! is_string($request->query('code')) || $request->query('code') === '') {
                throw new SsoException('provider', 'No authorization code.');
            }

            $workspace = $pending['workspace_id'] !== null
                ? Workspace::query()->active()->find($pending['workspace_id'])
                : null;

            if ($pending['workspace_id'] !== null && $workspace === null) {
                throw new SsoException('unavailable', 'Workspace is no longer active.');
            }

            if ($workspace !== null) {
                $this->workspaces->switchTo($workspace);
            }

            $settings = $this->settingsFor($workspace, (string) $pending['email']);

            $claims = $this->oidc->finish($settings, (string) $request->query('code'), (string) $pending['verifier'], route('admin.sso.callback'), (string) $pending['nonce']);

            $user = $this->userFor($claims, $settings, $workspace);
        } catch (SsoException $e) {
            return $this->fail($e, is_array($pending) ? (string) ($pending['email'] ?? '') : '');
        }

        auth()->guard('user')->login($user);
        $request->session()->regenerate();

        if ($workspace !== null) {
            $request->session()->put((string) config('workspaces.session_key'), $workspace->id);
        }

        $amr = array_map('strval', (array) ($claims['amr'] ?? []));

        if ($settings->trustProviderMfa || array_intersect($amr, self::MFA_METHODS) !== []) {
            $request->session()->put(SecurityEventSubscriber::MFA_SESSION_KEY, $user->id);
        }

        SecurityAuditRecorder::record('auth.sso_login', 'user', (string) $user->id, [
            'issuer' => $settings->issuer,
            'provider_mfa' => array_values(array_intersect($amr, self::MFA_METHODS)),
            'trusted_provider_mfa' => $settings->trustProviderMfa,
        ]);

        return redirect()->intended(route('admin.dashboard.index'));
    }

    private function workspaceFor(string $email): ?Workspace
    {
        if (! $this->workspaces->enabled()) {
            return null;
        }

        return $this->workspaces->findByEmail($email)
            ?? throw new SsoException('not-configured', 'No workspace for this address.');
    }

    private function settingsFor(?Workspace $workspace, string $email): SsoSettings
    {
        $settings = $this->workspaces->run($workspace, static fn (): SsoSettings => SsoSettings::current());

        if (! $settings->usable() || ! $settings->coversEmail($email)) {
            throw new SsoException('not-configured', 'Single sign-on is not set up for this address.');
        }

        return $settings;
    }

    /**
     * The verified email must be one the provider vouches for, in an allowed domain (and, for
     * Google Workspace, from an account of that domain rather than a personal one), belong to an
     * active user of this business, and point back to this same business.
     *
     * @param  array<string, mixed>  $claims
     */
    private function userFor(array $claims, SsoSettings $settings, ?Workspace $workspace): User
    {
        $email = strtolower(trim((string) ($claims['email'] ?? '')));

        if ($email === '' || ($claims['email_verified'] ?? true) === false) {
            throw new SsoException('unknown-user', 'Provider did not supply a verified email.');
        }

        if (! $settings->coversEmail($email)
            || (isset($claims['hd']) && ! in_array(strtolower((string) $claims['hd']), $settings->domains, true))) {
            throw new SsoException('unknown-user', "Email domain of {$email} is not allowed.");
        }

        if ($workspace !== null && $this->workspaces->findByEmail($email)?->id !== $workspace->id) {
            throw new SsoException('unknown-user', 'Verified email belongs to another workspace.');
        }

        $user = User::query()->where(DB::raw('LOWER(email)'), $email)->where('status', 1)->first();

        return $user ?? throw new SsoException('unknown-user', "No active user {$email}.");
    }

    private function fail(SsoException $e, string $email): RedirectResponse
    {
        SecurityAuditRecorder::record('auth.sso_failed', 'user', $email, ['reason' => $e->reason, 'detail' => $e->getMessage()]);

        return redirect()
            ->route('admin.sso.create')
            ->withInput(['email' => $email])
            ->with('error', trans('sso::app.errors.'.$e->reason));
    }
}
