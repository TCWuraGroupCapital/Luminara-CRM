<?php

use Illuminate\Support\Facades\Route;
use Webkul\Ledger\Http\Controllers\Admin\LedgerController;

Route::group([
    'middleware' => ['web', 'admin_locale', 'user'],
    'prefix' => config('app.admin_path', 'admin').'/ledger',
], function () {
    Route::get('/', [LedgerController::class, 'index'])->name('admin.ledger.index');
    Route::post('/seal', [LedgerController::class, 'seal'])->middleware('throttle:15,1')->name('admin.ledger.seal');
});
