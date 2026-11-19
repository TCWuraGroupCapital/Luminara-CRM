<?php

use Illuminate\Support\Facades\Route;
use Webkul\Gourwest\Http\Controllers\Admin\GourwestHubController;

Route::group([
    'middleware' => ['web', 'admin_locale', 'user'],
    'prefix' => config('app.admin_path', 'admin').'/gourwest',
], function (): void {
    Route::controller(GourwestHubController::class)->group(function (): void {
        Route::get('/', 'index')->name('admin.gourwest.index');
        Route::get('/overview', 'overview')->name('admin.gourwest.overview');
        Route::post('/seed-demo', 'seedDemo')->name('admin.gourwest.seed');
        Route::post('/deduplicate', 'deduplicate')->name('admin.gourwest.deduplicate');
        Route::post('/integrations/{id}/sync', 'syncIntegration')->whereNumber('id')->name('admin.gourwest.integrations.sync');
        Route::post('/integrations/replay-failed', 'replayFailed')->name('admin.gourwest.integrations.replay');
        Route::post('/loyalty/adjust', 'adjustLoyalty')->name('admin.gourwest.loyalty.adjust');
        Route::post('/tickets', 'storeTicket')->name('admin.gourwest.tickets.store');
        Route::post('/tickets/{id}/resolve', 'resolveTicket')->whereNumber('id')->name('admin.gourwest.tickets.resolve');
        Route::post('/benchmark', 'benchmark')->name('admin.gourwest.benchmark');
    });
});
