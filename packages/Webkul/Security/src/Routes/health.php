<?php

use Illuminate\Support\Facades\Route;
use Webkul\Security\Http\Controllers\ReadinessController;
use Webkul\Security\Http\Middleware\ThrottleReadiness;

Route::get('/health/ready', ReadinessController::class)
    ->middleware(ThrottleReadiness::class)
    ->name('health.ready');
