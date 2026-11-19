<?php

namespace Webkul\Tma\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\Agent\Support\ActionExecutor;
use Webkul\Agent\Support\ActionRegistry;
use Webkul\Tma\Models\TelegramIdentity;
use Webkul\Tma\Repositories\TelegramIdentityRepository;
use Webkul\Tma\Support\IntakeParamMapper;
use Webkul\Tma\Support\PairingCodeManager;
use Webkul\Tma\Support\TelegramSettings;
use Webkul\User\Models\User;

class TelegramBotWebhookController extends Controller
{
    public function handle(
        Request $request,
        PairingCodeManager $pairingManager,
        TelegramIdentityRepository $identityRepository,
        ActionExecutor $executor,
        ActionRegistry $registry,
    ): JsonResponse {
        $configuredSecret = TelegramSettings::webhookSecret();
        $providedSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if ($configuredSecret === '' || $providedSecret === '' || ! hash_equals($configuredSecret, $providedSecret)) {
            return new JsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'invalid_webhook_secret',
                    'message' => 'Invalid Telegram Bot API secret token.',
                ],
            ], 401);
        }

        $message = $request->input('message') ?? $request->input('edited_message');

        if (! is_array($message)) {
            return new JsonResponse(['ok' => true, 'ignored' => true]);
        }

        $from = is_array($message['from'] ?? null) ? $message['from'] : [];
        $telegramUserId = isset($from['id']) && is_numeric($from['id']) ? (int) $from['id'] : 0;
        $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
        $chatId = isset($chat['id']) && is_numeric($chat['id']) ? (int) $chat['id'] : null;
        $rawText = trim((string) ($message['text'] ?? $message['caption'] ?? ''));
        $tmaUrl = url('/tma');

        $replyMarkup = [
            'inline_keyboard' => [[
                [
                    'text' => trans('tma::app.bot.open-app'),
                    'web_app' => ['url' => $tmaUrl],
                ],
            ]],
        ];

        if ($telegramUserId <= 0 || $chatId === null) {
            return new JsonResponse(['ok' => true, 'ignored' => true]);
        }

        if (preg_match('/^\/start(?:@\S+)?(?:\s+(\S+))?$/i', $rawText, $matches) === 1) {
            $code = isset($matches[1]) ? trim($matches[1]) : '';

            if ($code !== '') {
                $identity = $pairingManager->consumeCode($code, [
                    'id' => $telegramUserId,
                    'username' => $from['username'] ?? null,
                    'first_name' => $from['first_name'] ?? null,
                ], $chatId);

                if ($identity instanceof TelegramIdentity) {
                    return new JsonResponse([
                        'ok' => true,
                        'paired' => true,
                        'user_id' => $identity->user_id,
                        'method' => 'sendMessage',
                        'chat_id' => $chatId,
                        'text' => trans('tma::app.bot.paired-success', [
                            'name' => $identity->user?->getAttribute('name') ?? '#'.$identity->user_id,
                        ]),
                        'reply_markup' => $replyMarkup,
                    ]);
                }

                return new JsonResponse([
                    'ok' => false,
                    'paired' => false,
                    'error' => 'invalid_pair_code',
                    'method' => 'sendMessage',
                    'chat_id' => $chatId,
                    'text' => trans('tma::app.bot.paired-invalid'),
                    'reply_markup' => $replyMarkup,
                ]);
            }

            /** @var TelegramIdentity|null $existing */
            $existing = $identityRepository->findOneWhere(['telegram_user_id' => $telegramUserId]);

            if ($existing instanceof TelegramIdentity && $existing->chat_id !== $chatId) {
                $existing->update(['chat_id' => $chatId]);
            }

            return new JsonResponse([
                'ok' => true,
                'linked' => $existing instanceof TelegramIdentity,
                'method' => 'sendMessage',
                'chat_id' => $chatId,
                'text' => trans($existing instanceof TelegramIdentity ? 'tma::app.bot.welcome-linked' : 'tma::app.bot.unlinked'),
                'reply_markup' => $replyMarkup,
            ]);
        }

        if ($rawText === '') {
            return new JsonResponse([
                'ok' => true,
                'ignored' => true,
                'reply_markup' => $replyMarkup,
            ]);
        }

        /** @var TelegramIdentity|null $identity */
        $identity = $identityRepository->findOneWhere(['telegram_user_id' => $telegramUserId]);
        $linkedUser = $identity?->user;

        if (! $identity instanceof TelegramIdentity || ! $linkedUser instanceof User || ! $linkedUser->getAttribute('status')) {
            return new JsonResponse([
                'ok' => false,
                'linked' => false,
                'method' => 'sendMessage',
                'chat_id' => $chatId,
                'text' => trans('tma::app.bot.unlinked'),
                'reply_markup' => $replyMarkup,
            ]);
        }

        if ($identity->chat_id !== $chatId) {
            $identity->update(['chat_id' => $chatId]);
        }

        $guard = auth()->guard('user');
        $previousUser = $guard->user();
        $guard->setUser($linkedUser);

        try {
            $idempotencyKey = isset($message['message_id']) && is_numeric($message['message_id'])
                ? 'tg-msg-'.$telegramUserId.'-'.(int) $message['message_id']
                : null;

            $params = IntakeParamMapper::build($registry, $rawText, 'telegram_bot');
            $outcome = $executor->execute('intake_capture', $params, $idempotencyKey);
        } finally {
            if ($previousUser instanceof User) {
                $guard->setUser($previousUser);
            } elseif (method_exists($guard, 'forgetUser')) {
                $guard->forgetUser();
            }
        }

        $callId = $outcome->body['call_id'] ?? null;
        $status = (string) ($outcome->body['status'] ?? ($outcome->body['error']['code'] ?? 'failed'));
        $succeeded = (bool) ($outcome->body['ok'] ?? false);

        $replyText = $succeeded
            ? trans('tma::app.bot.intake-captured', ['id' => (string) ($callId ?? '—'), 'status' => $status])
            : trans('tma::app.bot.intake-failed', ['reason' => (string) ($outcome->body['error']['message'] ?? $status)]);

        return new JsonResponse([
            'ok' => $succeeded,
            'linked' => true,
            'call_id' => $callId,
            'action' => 'intake_capture',
            'status' => $status,
            'outcome' => $outcome->body,
            'method' => 'sendMessage',
            'chat_id' => $chatId,
            'text' => $replyText,
            'reply_markup' => $replyMarkup,
        ]);
    }
}
