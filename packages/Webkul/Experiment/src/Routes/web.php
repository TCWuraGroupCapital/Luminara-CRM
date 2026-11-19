<?php

use Illuminate\Support\Facades\Route;
use Webkul\Experiment\Http\Controllers\PublicExperimentController;
use Webkul\Experiment\Http\Middleware\ExperimentCorsMiddleware;

Route::group([
    'middleware' => ['web', ExperimentCorsMiddleware::class],
    'prefix' => 'experiments',
], function () {
    Route::match(['GET', 'OPTIONS'], 'assign/{code}', [PublicExperimentController::class, 'assign'])
        ->middleware('throttle:120,1')
        ->name('experiments.public.assign');

    Route::match(['POST', 'OPTIONS'], 'track', [PublicExperimentController::class, 'track'])
        ->middleware('throttle:60,1')
        ->name('experiments.public.track');
});
