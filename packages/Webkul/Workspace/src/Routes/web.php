<?php

use Illuminate\Support\Facades\Route;
use Webkul\Workspace\Http\Controllers\SignUpController;

Route::middleware(['web'])->controller(SignUpController::class)->prefix('signup')->group(function () {
    Route::get('', 'create')->name('workspace.signup.create');

    /**
     * Each sign-up builds a whole database, so the form is limited per address well below the
     * rate of the ordinary sign-in form.
     */
    Route::post('', 'store')->middleware('throttle:6,60')->name('workspace.signup.store');

    Route::get('verify/{token}', 'verify')->middleware('throttle:30,1')->name('workspace.signup.verify');

    Route::get('status/{token}', 'status')->middleware('throttle:120,1')->name('workspace.signup.status');
});
