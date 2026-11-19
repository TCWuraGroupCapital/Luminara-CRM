<?php

use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Webkul\Agent\Http\Controllers\Api\AgentController;
use Webkul\Agent\Http\Middleware\AuthenticateAgentToken;

/**
 * Token-only: the stateful-frontend middleware is removed so a browser session can never reach
 * these routes, and with no session there is nothing for a cross-site request to ride on.
 */
Route::group([
    'middleware' => ['api', 'throttle:120,1', AuthenticateAgentToken::class],
    'prefix' => 'api/agent',
    'excluded_middleware' => [EnsureFrontendRequestsAreStateful::class],
], function (): void {
    Route::get('/context', [AgentController::class, 'context'])->name('api.agent.context');
    Route::post('/actions/{action}', [AgentController::class, 'execute'])->name('api.agent.actions.execute');
    Route::get('/calls/{id}', [AgentController::class, 'call'])->whereNumber('id')->name('api.agent.calls.show');
    Route::get('/events', [AgentController::class, 'events'])->name('api.agent.events');
    Route::post('/mcp', [AgentController::class, 'mcp'])->name('api.agent.mcp');
});
