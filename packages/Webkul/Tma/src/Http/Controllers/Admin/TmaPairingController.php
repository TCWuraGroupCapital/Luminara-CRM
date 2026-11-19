<?php

namespace Webkul\Tma\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Security\Support\SecurityAuditRecorder;
use Webkul\Tma\Exceptions\TmaAuthException;
use Webkul\Tma\Models\TelegramIdentity;
use Webkul\Tma\Models\UserPasskey;
use Webkul\Tma\Repositories\TelegramIdentityRepository;
use Webkul\Tma\Support\PairingCodeManager;
use Webkul\Tma\Support\WebAuthnVerifier;
use Webkul\User\Models\User;

class TmaPairingController extends Controller
{
    public const PERMISSION = 'settings.other_settings.tma';

    public function __construct(
        private TelegramIdentityRepository $identityRepository,
        private PairingCodeManager $pairingManager,
        private WebAuthnVerifier $verifier,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAccess();

        /** @var User $user */
        $user = auth()->guard('user')->user();

        /** @var TelegramIdentity|null $identity */
        $identity = $this->identityRepository->findOneWhere(['user_id' => $user->id]);
        $passkeys = UserPasskey::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();

        return view('tma::admin.pairing', [
            'identity' => $identity,
            'passkeys' => $passkeys,
            'pairing' => session('tma_pairing'),
            'webauthnChallenge' => $this->verifier->issueChallenge($user->id),
            'rpId' => $request->getHost(),
            'user' => $user,
        ]);
    }

    public function challenge(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        /** @var User $user */
        $user = auth()->guard('user')->user();
        $options = $this->verifier->challengeOptions($user->id, $request->getHost());

        return new JsonResponse(['ok' => true] + $options);
    }

    public function issueCode(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAccess();

        /** @var User $user */
        $user = auth()->guard('user')->user();
        $issued = $this->pairingManager->issueCode($user->id);

        if ($request->wantsJson()) {
            return new JsonResponse(['ok' => true] + $issued);
        }

        session()->flash('tma_pairing', $issued);
        session()->flash('success', trans('tma::app.pairing.code-issued'));

        return redirect()->route('admin.tma.pairing.index');
    }

    public function unlink(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAccess();

        /** @var User $user */
        $user = auth()->guard('user')->user();

        /** @var TelegramIdentity|null $identity */
        $identity = $this->identityRepository->findOneWhere(['user_id' => $user->id]);

        if ($identity instanceof TelegramIdentity) {
            $telegramId = $identity->telegram_user_id;
            $identityId = $identity->id;
            $identity->delete();

            SecurityAuditRecorder::record(
                'tma.identity.unlinked',
                'telegram_identity',
                $identityId,
                ['telegram_user_id' => $telegramId],
                $user->id,
                (string) $user->getAttribute('email')
            );
        }

        if ($request->wantsJson()) {
            return new JsonResponse(['ok' => true, 'linked' => false]);
        }

        session()->flash('success', trans('tma::app.pairing.unlinked'));

        return redirect()->route('admin.tma.pairing.index');
    }

    public function storePasskey(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAccess();

        /** @var User $user */
        $user = auth()->guard('user')->user();

        try {
            $passkey = $this->verifier->registerPasskey(
                $user,
                $request->all(),
                $request->getSchemeAndHttpHost(),
                $request->getHost(),
                'Hardware Passkey'
            );
        } catch (TmaAuthException $exception) {
            return $this->failPasskey(
                $request,
                $user,
                $exception->getMessage(),
                $exception->errorCode === 'duplicate_credential' ? 409 : 422
            );
        }

        if ($request->wantsJson()) {
            return new JsonResponse([
                'ok' => true,
                'passkey' => [
                    'id' => $passkey->id,
                    'credential_id' => $passkey->credential_id,
                    'label' => $passkey->label,
                    'alg' => $passkey->alg,
                ],
            ], 201);
        }

        session()->flash('success', trans('tma::app.passkeys.added'));

        return redirect()->route('admin.tma.pairing.index');
    }

    public function destroyPasskey(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $this->authorizeAccess();

        /** @var User $user */
        $user = auth()->guard('user')->user();

        /** @var UserPasskey|null $passkey */
        $passkey = UserPasskey::query()
            ->whereKey($id)
            ->where('user_id', $user->id)
            ->first();

        if (! $passkey instanceof UserPasskey) {
            if ($request->wantsJson()) {
                return new JsonResponse(['ok' => false, 'error' => ['code' => 'not_found']], 404);
            }

            abort(404);
        }

        $passkey->delete();

        SecurityAuditRecorder::record(
            'tma.passkey.removed',
            'user_passkey',
            $id,
            null,
            $user->id,
            (string) $user->getAttribute('email')
        );

        if ($request->wantsJson()) {
            return new JsonResponse(['ok' => true]);
        }

        session()->flash('success', trans('tma::app.passkeys.removed'));

        return redirect()->route('admin.tma.pairing.index');
    }

    private function authorizeAccess(): void
    {
        abort_unless(
            bouncer()->hasPermission(self::PERMISSION),
            401,
            trans('admin::app.errors.unauthorized')
        );
    }

    private function failPasskey(Request $request, User $user, string $message, int $status): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse([
                'ok' => false,
                'challenge' => $this->verifier->issueChallenge($user->id),
                'error' => ['message' => $message],
            ], $status);
        }

        session()->flash('error', $message);

        return redirect()->route('admin.tma.pairing.index');
    }
}
