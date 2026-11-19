<?php

use Illuminate\Support\Facades\Route;
use Webkul\Experiment\Http\Controllers\Admin\ExperimentController;

Route::group([
    'middleware' => ['web', 'admin_locale', 'user'],
    'prefix' => config('app.admin_path'),
], function () {
    Route::controller(ExperimentController::class)
        ->prefix('settings/experiments')
        ->group(function () {
            Route::get('', 'index')->name('admin.settings.experiments.index');
            Route::get('{id}/stats', 'stats')->whereNumber('id')->name('admin.settings.experiments.stats');
            Route::get('propensity/{leadId}', 'leadPropensity')->whereNumber('leadId')->name('admin.settings.experiments.propensity');
            Route::post('', 'store')->name('admin.settings.experiments.store');
            Route::put('{id}', 'update')->whereNumber('id')->name('admin.settings.experiments.update');
            Route::delete('{id}', 'destroy')->whereNumber('id')->name('admin.settings.experiments.delete');
        });
});
