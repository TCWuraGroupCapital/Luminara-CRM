<?php

use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Webkul\Tma\Http\Controllers\Api\TelegramBotWebhookController;
use Webkul\Tma\Http\Controllers\Api\TmaBriefingController;
use Webkul\Tma\Http\Controllers\Api\TmaDecisionController;
use Webkul\Tma\Http\Controllers\Api\TmaIntakeController;
use Webkul\Tma\Http\Controllers\Api\TmaPasskeyController;
use Webkul\Tma\Http\Controllers\Api\TmaSessionController;
use Webkul\Tma\Http\Middleware\AuthenticateTmaToken;

/**
 * Stateless TMA & Telegram Bot endpoints: `EnsureFrontendRequestsAreStateful` is excluded so
 * requests authenticate strictly via short-lived Bearer tokens or Telegram HMAC/secret headers.
 */
Route::group([
    'middleware' => ['api', 'throttle:120,1'],
    'prefix' => 'api/tma',
    'excluded_middleware' => [EnsureFrontendRequestsAreStateful::class],
], function (): void {
    Route::post('/session', [TmaSessionController::class, 'exchange'])->name('api.tma.session.exchange');
    Route::post('/bot/webhook', [TelegramBotWebhookController::class, 'handle'])->name('api.tma.bot.webhook');

    Route::group([
        'middleware' => [AuthenticateTmaToken::class],
    ], function (): void {
        Route::get('/briefing', [TmaBriefingController::class, 'show'])->name('api.tma.briefing');
        Route::post('/intake', [TmaIntakeController::class, 'store'])->name('api.tma.intake');
        Route::post('/calls/{id}/decide', [TmaDecisionController::class, 'decide'])->whereNumber('id')->name('api.tma.calls.decide');
        Route::post('/passkeys/challenge', [TmaPasskeyController::class, 'challenge'])->name('api.tma.passkeys.challenge');
        Route::post('/passkeys', [TmaPasskeyController::class, 'store'])->name('api.tma.passkeys.store');
    });
});
