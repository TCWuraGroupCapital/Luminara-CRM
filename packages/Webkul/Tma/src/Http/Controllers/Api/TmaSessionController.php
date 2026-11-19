<?php

namespace Webkul\Tma\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Webkul\Tma\Exceptions\TmaAuthException;
use Webkul\Tma\Http\Middleware\AuthenticateTmaToken;
use Webkul\Tma\Models\TelegramIdentity;
use Webkul\Tma\Models\UserPasskey;
use Webkul\Tma\Repositories\TelegramIdentityRepository;
use Webkul\Tma\Support\PairingCodeManager;
use Webkul\Tma\Support\TelegramInitDataValidator;
use Webkul\Tma\Support\WebAuthnVerifier;
use Webkul\User\Models\User;

class TmaSessionController extends Controller
{
    public function exchange(
        Request $request,
        TelegramInitDataValidator $validator,
        PairingCodeManager $pairingManager,
        TelegramIdentityRepository $identityRepository,
    ): JsonResponse {
        $initData = (string) ($request->input('init_data') ?? $request->input('initData', ''));
        $linkTicket = (string) ($request->input('link_ticket') ?? $request->input('linkTicket', ''));
        $pairCodeInput = $request->input('pair_code') ?? $request->input('pairCode');
        $pairCode = is_string($pairCodeInput) ? trim($pairCodeInput) : '';

        if ($initData !== '') {
            try {
                $validated = $validator->validate($initData);
            } catch (TmaAuthException $exception) {
                return new JsonResponse([
                    'ok' => false,
                    'error' => [
                        'code' => $exception->errorCode,
                        'message' => $exception->getMessage(),
                    ],
                ], 401);
            }
        } elseif ($linkTicket !== '' && $pairCode !== '') {
            $cached = Cache::pull('tma:link_ticket:'.$linkTicket);

            if (! is_array($cached) || ! is_array($cached['user'] ?? null)) {
                return new JsonResponse([
                    'ok' => false,
                    'error' => [
                        'code' => 'expired_link_ticket',
                        'message' => 'Pairing session expired. Reopen the Telegram Mini App.',
                    ],
                ], 401);
            }

            $validated = $cached;
        } else {
            return new JsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'missing_init_data',
                    'message' => 'Signed Telegram init_data is required.',
                ],
            ], 422);
        }

        /** @var array{id: int, username: string|null, first_name: string|null, last_name: string|null, language_code: string|null} $tgUser */
        $tgUser = $validated['user'];
        $authDate = (int) ($validated['auth_date'] ?? time());
        $startParam = is_string($validated['start_param'] ?? null) ? trim($validated['start_param']) : '';

        /** @var TelegramIdentity|null $identity */
        $identity = null;

        if ($pairCode !== '') {
            $identity = $pairingManager->consumeCode($pairCode, $tgUser + ['auth_date' => $authDate]);

            if (! $identity instanceof TelegramIdentity) {
                $retryTicket = WebAuthnVerifier::base64UrlEncode(random_bytes(24));
                Cache::put('tma:link_ticket:'.$retryTicket, $validated, 300);

                return new JsonResponse([
                    'ok' => false,
                    'linked' => false,
                    'link_ticket' => $retryTicket,
                    'error' => [
                        'code' => 'invalid_pair_code',
                        'message' => 'Invalid or expired pairing code.',
                    ],
                ], 422);
            }
        } else {
            /** @var TelegramIdentity|null $existing */
            $existing = $identityRepository->findOneWhere(['telegram_user_id' => $tgUser['id']]);
            $identity = $existing;

            if (! $identity instanceof TelegramIdentity && preg_match('/^[A-Z0-9]{8}$/i', $startParam) === 1) {
                $identity = $pairingManager->consumeCode($startParam, $tgUser + ['auth_date' => $authDate]);
            }
        }

        if (! $identity instanceof TelegramIdentity) {
            $ticket = WebAuthnVerifier::base64UrlEncode(random_bytes(24));
            Cache::put('tma:link_ticket:'.$ticket, $validated, 300);

            return new JsonResponse([
                'ok' => true,
                'linked' => false,
                'link_ticket' => $ticket,
                'telegram_user' => $tgUser,
            ]);
        }

        /** @var User|null $user */
        $user = User::query()->find($identity->user_id);

        if (! $user instanceof User || ! $user->getAttribute('status')) {
            return new JsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'account_disabled',
                    'message' => 'The linked CRM user account is inactive.',
                ],
            ], 403);
        }

        $identity->update([
            'last_auth_date' => $authDate,
            'telegram_username' => $tgUser['username'] ?? $identity->telegram_username,
            'first_name' => $tgUser['first_name'] ?? $identity->first_name,
        ]);

        $ttlMinutes = max(1, (int) config('tma.token_ttl_minutes', 120));
        $expiresAt = now()->addMinutes($ttlMinutes);
        $user->tokens()
            ->where('name', 'telegram-mini-app')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();
        $accessToken = $user->createToken('telegram-mini-app', [AuthenticateTmaToken::ABILITY], $expiresAt);

        $passkeys = UserPasskey::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get(['id', 'credential_id', 'label', 'alg', 'sign_count', 'last_used_at']);

        return new JsonResponse([
            'ok' => true,
            'linked' => true,
            'token' => $accessToken->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->getAttribute('name'),
                'email' => $user->getAttribute('email'),
            ],
            'telegram' => [
                'telegram_user_id' => $identity->telegram_user_id,
                'username' => $identity->telegram_username,
                'first_name' => $identity->first_name,
            ],
            'passkeys' => $passkeys,
        ]);
    }
}
