<?php

namespace Webkul\Tma\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\Tma\Exceptions\TmaAuthException;
use Webkul\Tma\Support\WebAuthnVerifier;
use Webkul\User\Models\User;

class TmaPasskeyController extends Controller
{
    public function challenge(Request $request, WebAuthnVerifier $verifier): JsonResponse
    {
        /** @var User $user */
        $user = auth()->guard('user')->user();
        $callIdRaw = $request->input('call_id');
        $callId = is_numeric($callIdRaw) && (int) $callIdRaw > 0 ? (int) $callIdRaw : null;
        $options = $verifier->challengeOptions($user->id, $request->getHost(), $callId);

        return new JsonResponse([
            'ok' => true,
            'challenge' => $options['challenge'],
            'expires_in' => $options['expires_in'],
            'rp_id' => $options['rp_id'],
            'user' => [
                'id' => WebAuthnVerifier::base64UrlEncode((string) $user->id),
                'name' => $user->getAttribute('email'),
                'displayName' => $user->getAttribute('name'),
            ],
            'allow_credentials' => $options['allow_credentials'],
        ]);
    }

    public function store(Request $request, WebAuthnVerifier $verifier): JsonResponse
    {
        /** @var User $user */
        $user = auth()->guard('user')->user();

        try {
            $passkey = $verifier->registerPasskey(
                $user,
                $request->all(),
                $request->getSchemeAndHttpHost(),
                $request->getHost(),
                'Telegram Passkey'
            );
        } catch (TmaAuthException $exception) {
            return new JsonResponse([
                'ok' => false,
                'error' => [
                    'code' => $exception->errorCode,
                    'message' => $exception->getMessage(),
                ],
            ], $exception->errorCode === 'duplicate_credential' ? 409 : 422);
        }

        return new JsonResponse([
            'ok' => true,
            'passkey' => [
                'id' => $passkey->id,
                'credential_id' => $passkey->credential_id,
                'label' => $passkey->label,
                'alg' => $passkey->alg,
                'sign_count' => $passkey->sign_count,
            ],
        ], 201);
    }
}
