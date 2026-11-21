<?php

use Illuminate\Support\Facades\Route;
use Webkul\Sso\Http\Controllers\SsoController;

Route::middleware(['web', 'admin_locale'])
    ->prefix(config('app.admin_path', 'admin').'/sso')
    ->controller(SsoController::class)
    ->group(function () {
        Route::get('', 'create')->name('admin.sso.create');

        Route::post('', 'start')->middleware('throttle:10,1')->name('admin.sso.start');

        Route::get('callback', 'callback')->middleware('throttle:30,1')->name('admin.sso.callback');
    });
