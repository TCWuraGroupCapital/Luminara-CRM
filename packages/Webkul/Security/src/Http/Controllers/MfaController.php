<?php

namespace Webkul\Security\Http\Controllers;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Webkul\Security\Listeners\SecurityEventSubscriber;
use Webkul\Security\Models\UserMfaSetting;
use Webkul\Security\Support\SecurityAuditRecorder;
use Webkul\Security\Support\Totp;

class MfaController extends Controller
{
    private const PENDING_SECRET_KEY = 'security.mfa_pending_secret';

    /**
     * Render personal TOTP MFA enrollment & management screen.
     */
    public function setup(Request $request): View
    {
        $user = auth()->guard('user')->user();
        $setting = UserMfaSetting::query()->where('user_id', $user->id)->first();
        $isEnabled = (bool) $setting?->mfa_enabled;

        $pendingSecret = null;
        $provisioningUri = null;

        if (! $isEnabled) {
            $pendingSecret = (string) $request->session()->get(self::PENDING_SECRET_KEY, '');

            if ($pendingSecret === '') {
                $pendingSecret = Totp::generateSecret();
                $request->session()->put(self::PENDING_SECRET_KEY, $pendingSecret);
            }

            $provisioningUri = Totp::provisioningUri(
                issuer: (string) config('security.mfa_issuer', 'Luminara CRM'),
                accountEmail: (string) $user->getAttribute('email'),
                base32Secret: $pendingSecret,
            );
        }

        return view('security::mfa.setup', [
            'isEnabled' => $isEnabled,
            'enabledAt' => $setting?->enabled_at,
            'remainingRecoveryCodes' => is_array($setting?->recovery_codes_hashes) ? count($setting->recovery_codes_hashes) : 0,
            'pendingSecret' => $pendingSecret,
            'provisioningUri' => $provisioningUri,
            'freshRecoveryCodes' => $request->session()->get('security.fresh_recovery_codes'),
            'requireMfaPolicy' => (bool) config('security.require_admin_mfa', false),
        ]);
    }

    /**
     * Confirm current password + 6-digit TOTP code to activate MFA and issue 8 single-use recovery codes.
     */
    public function enable(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'code' => ['required', 'string', 'max:16'],
        ]);

        $user = auth()->guard('user')->user();

        if (! Hash::check($validated['current_password'], (string) $user->getAttribute('password'))) {
            return $this->failResponse($request, trans('security::app.mfa.invalid-password'));
        }

        $pendingSecret = (string) $request->session()->get(self::PENDING_SECRET_KEY, '');

        if ($pendingSecret === '') {
            return $this->failResponse($request, trans('security::app.mfa.missing-secret'));
        }

        $matchedSlice = Totp::verify($pendingSecret, $validated['code']);

        if ($matchedSlice === null) {
            SecurityAuditRecorder::record('mfa.enable_failed', 'user', $user->id);

            return $this->failResponse($request, trans('security::app.mfa.invalid-code'));
        }

        $recoveryCodes = Totp::generateRecoveryCodes(8);
        $hashedCodes = array_map([Totp::class, 'hashRecoveryCode'], $recoveryCodes);

        $setting = UserMfaSetting::query()->firstOrNew(['user_id' => (int) $user->id]);
        $setting->setPlainSecret($pendingSecret);
        $setting->recovery_codes_hashes = $hashedCodes;
        $setting->mfa_enabled = true;
        $setting->last_used_slice = $matchedSlice;
        $setting->enabled_at = now();
        $setting->save();

        $request->session()->forget(self::PENDING_SECRET_KEY);
        $request->session()->regenerate();
        $request->session()->put(SecurityEventSubscriber::MFA_SESSION_KEY, (int) $user->id);
        $request->session()->flash('security.fresh_recovery_codes', $recoveryCodes);
        $request->session()->flash('success', trans('security::app.mfa.enabled-success'));

        SecurityAuditRecorder::record('mfa.enabled', 'user', $user->id);

        if ($request->expectsJson() || $request->ajax()) {
            return new JsonResponse([
                'enabled' => true,
                'recovery_codes' => $recoveryCodes,
                'message' => trans('security::app.mfa.enabled-success'),
            ]);
        }

        return redirect()->route('admin.security.mfa.setup');
    }

    /**
     * Disable MFA after verifying both the user's current password and an active TOTP or recovery code.
     */
    public function disable(Request $request): JsonResponse|RedirectResponse
    {
        if ((bool) config('security.require_admin_mfa', false)) {
            return $this->failResponse($request, trans('security::app.mfa.enrollment-required'));
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'code' => ['required', 'string', 'max:32'],
        ]);

        $user = auth()->guard('user')->user();

        if (! Hash::check($validated['current_password'], (string) $user->getAttribute('password'))) {
            return $this->failResponse($request, trans('security::app.mfa.invalid-password'));
        }

        $setting = UserMfaSetting::query()->where('user_id', $user->id)->first();

        if (! $setting || ! $setting->mfa_enabled) {
            return $this->failResponse($request, trans('security::app.mfa.not-enabled'));
        }

        if (! $this->verifyCodeOrRecovery((int) $setting->id, $validated['code'], true)) {
            SecurityAuditRecorder::record('mfa.disable_failed', 'user', $user->id);

            return $this->failResponse($request, trans('security::app.mfa.invalid-code'));
        }

        $request->session()->forget(SecurityEventSubscriber::MFA_SESSION_KEY);
        $request->session()->flash('success', trans('security::app.mfa.disabled-success'));

        SecurityAuditRecorder::record('mfa.disabled', 'user', $user->id);

        if ($request->expectsJson() || $request->ajax()) {
            return new JsonResponse([
                'enabled' => false,
                'message' => trans('security::app.mfa.disabled-success'),
            ]);
        }

        return redirect()->route('admin.security.mfa.setup');
    }

    /**
     * Render the anonymous-layout MFA challenge gate after primary password login.
     */
    public function challenge(Request $request): View|RedirectResponse
    {
        $user = auth()->guard('user')->user();
        $setting = UserMfaSetting::query()->where('user_id', $user->id)->first();

        if (! $setting || ! $setting->mfa_enabled) {
            return redirect()->route('admin.dashboard.index');
        }

        if ((int) $request->session()->get(SecurityEventSubscriber::MFA_SESSION_KEY, 0) === (int) $user->id) {
            return redirect()->route('admin.dashboard.index');
        }

        return view('security::mfa.challenge');
    }

    /**
     * Verify a TOTP code or single-use recovery code to unlock the admin session.
     */
    public function verify(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $user = auth()->guard('user')->user();
        $setting = UserMfaSetting::query()->where('user_id', $user->id)->first();

        if (! $setting || ! $setting->mfa_enabled) {
            return redirect()->route('admin.dashboard.index');
        }

        if (! $this->verifyCodeOrRecovery((int) $setting->id, $validated['code'])) {
            SecurityAuditRecorder::record('mfa.failed', 'user', $user->id);

            return $this->failResponse($request, trans('security::app.mfa.invalid-code'));
        }

        $request->session()->regenerate();
        $request->session()->put(SecurityEventSubscriber::MFA_SESSION_KEY, (int) $user->id);

        SecurityAuditRecorder::record('mfa.verified', 'user', $user->id);

        if ($request->expectsJson() || $request->ajax()) {
            return new JsonResponse([
                'verified' => true,
                'redirect' => $this->safeAdminRedirectUrl($request),
            ]);
        }

        return redirect()->to($this->safeAdminRedirectUrl($request));
    }

    /**
     * Atomically verify and consume either a TOTP time-slice or a single-use recovery code under a row lock.
     */
    private function verifyCodeOrRecovery(int $settingId, string $submittedCode, bool $deactivateOnMatch = false): bool
    {
        return (bool) DB::transaction(static function () use ($settingId, $submittedCode, $deactivateOnMatch): bool {
            $locked = UserMfaSetting::query()->whereKey($settingId)->lockForUpdate()->first();

            if (! $locked || ! $locked->mfa_enabled) {
                return false;
            }

            try {
                $matchedSlice = Totp::verify($locked->decryptedSecret(), $submittedCode, $locked->last_used_slice);
            } catch (DecryptException) {
                $matchedSlice = null;
            }

            if ($matchedSlice !== null) {
                $locked->last_used_slice = $matchedSlice;

                if ($deactivateOnMatch) {
                    $locked->mfa_enabled = false;
                    $locked->recovery_codes_hashes = [];
                    $locked->enabled_at = null;
                }

                $locked->save();

                return true;
            }

            $candidateHash = Totp::hashRecoveryCode($submittedCode);
            $hashes = is_array($locked->recovery_codes_hashes) ? $locked->recovery_codes_hashes : [];

            foreach ($hashes as $idx => $storedHash) {
                if (hash_equals($storedHash, $candidateHash)) {
                    if ($deactivateOnMatch) {
                        $locked->mfa_enabled = false;
                        $locked->recovery_codes_hashes = [];
                        $locked->enabled_at = null;
                    } else {
                        unset($hashes[$idx]);
                        $locked->recovery_codes_hashes = array_values($hashes);
                    }

                    $locked->save();

                    SecurityAuditRecorder::record('mfa.recovery_code_used', 'user', $locked->user_id, [
                        'remaining_codes' => count($locked->recovery_codes_hashes),
                    ]);

                    return true;
                }
            }

            return false;
        });
    }

    private function safeAdminRedirectUrl(Request $request): string
    {
        $intended = trim((string) $request->session()->pull('url.intended', ''));

        if ($intended === '' || str_starts_with($intended, '//')) {
            return route('admin.dashboard.index');
        }

        $parts = parse_url($intended);

        if (! is_array($parts)) {
            return route('admin.dashboard.index');
        }

        if (isset($parts['host']) && strcasecmp($parts['host'], $request->getHost()) !== 0) {
            return route('admin.dashboard.index');
        }

        $adminPrefix = '/'.trim((string) config('app.admin_path', 'admin'), '/');
        $parsedPath = (string) ($parts['path'] ?? '');

        if ($parsedPath === $adminPrefix || str_starts_with($parsedPath, $adminPrefix.'/')) {
            $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

            return url($parsedPath.$query);
        }

        return route('admin.dashboard.index');
    }

    private function failResponse(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return new JsonResponse([
                'message' => $message,
                'errors' => ['code' => [$message]],
            ], 422);
        }

        return redirect()->back()->withErrors(['code' => $message]);
    }
}
