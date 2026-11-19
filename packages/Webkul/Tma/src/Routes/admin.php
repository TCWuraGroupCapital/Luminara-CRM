<?php

use Illuminate\Support\Facades\Route;
use Webkul\Tma\Http\Controllers\Admin\TmaPairingController;

Route::group([
    'middleware' => ['web', 'admin_locale', 'user'],
    'prefix' => config('app.admin_path', 'admin').'/tma',
], function (): void {
    Route::get('/pairing', [TmaPairingController::class, 'index'])->name('admin.tma.pairing.index');
    Route::post('/pairing/code', [TmaPairingController::class, 'issueCode'])->name('admin.tma.pairing.code');
    Route::delete('/pairing', [TmaPairingController::class, 'unlink'])->name('admin.tma.pairing.destroy');
    Route::post('/passkeys/challenge', [TmaPairingController::class, 'challenge'])->name('admin.tma.passkeys.challenge');
    Route::post('/passkeys', [TmaPairingController::class, 'storePasskey'])->name('admin.tma.passkeys.store');
    Route::delete('/passkeys/{id}', [TmaPairingController::class, 'destroyPasskey'])->whereNumber('id')->name('admin.tma.passkeys.destroy');
});
