<?php

use Illuminate\Support\Facades\Route;
use Webkul\Privacy\Http\Controllers\PersonPrivacyController;

Route::group([
    'middleware' => ['web', 'admin_locale', 'user'],
    'prefix' => config('app.admin_path', 'admin').'/contacts/persons/{id}/privacy',
], function () {
    Route::get('export', [PersonPrivacyController::class, 'export'])
        ->whereNumber('id')
        ->middleware('throttle:20,1')
        ->name('admin.contacts.persons.privacy.export');

    Route::post('erase', [PersonPrivacyController::class, 'erase'])
        ->whereNumber('id')
        ->middleware('throttle:10,1')
        ->name('admin.contacts.persons.privacy.erase');
});
