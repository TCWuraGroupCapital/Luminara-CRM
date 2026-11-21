<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Configuration\ConfigurationController;
use Webkul\Admin\Http\Controllers\Configuration\SmtpTestController;

/**
 * Registered before the catch-all `{slug}/{slug2}` routes below so it is not read as a settings page.
 */
Route::post('configuration/email/smtp-test', SmtpTestController::class)
    ->middleware('throttle:6,1')
    ->name('admin.configuration.email.smtp_test');

Route::controller(ConfigurationController::class)->prefix('configuration')->group(function () {
    Route::get('search', 'search')->name('admin.configuration.search');

    Route::prefix('{slug?}/{slug2?}')->group(function () {
        Route::get('', 'index')->name('admin.configuration.index');

        Route::post('', 'store')->name('admin.configuration.store');

        Route::get('{path}', 'download')->name('admin.configuration.download');
    });
});
