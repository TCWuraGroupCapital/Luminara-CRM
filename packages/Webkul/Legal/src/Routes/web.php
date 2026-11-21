<?php

use Illuminate\Support\Facades\Route;
use Webkul\Legal\Http\Controllers\LegalPageController;

Route::middleware(['web', 'throttle:120,1'])->controller(LegalPageController::class)->group(function () {
    Route::get('terms', 'show')->defaults('document', 'terms')->name('legal.terms');

    Route::get('privacy', 'show')->defaults('document', 'privacy')->name('legal.privacy');

    Route::get('legal/dpa', 'show')->defaults('document', 'dpa')->name('legal.dpa');

    Route::get('legal/subprocessors', 'show')->defaults('document', 'subprocessors')->name('legal.subprocessors');
});
