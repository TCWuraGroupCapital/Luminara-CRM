<?php

use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Webkul\Gourwest\Http\Controllers\Api\WebhookController;

Route::group([
    'middleware' => ['api', 'throttle:120,1'],
    'prefix' => 'api/gourwest',
], function (): void {
    Route::post('/webhooks/{systemCode}', [WebhookController::class, 'ingest'])
        ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class])
        ->where('systemCode', '[a-z0-9_]+')
        ->name('api.gourwest.webhooks.ingest');
});
