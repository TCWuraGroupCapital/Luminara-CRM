<?php

use Illuminate\Support\Facades\Route;
use Webkul\Tma\Http\Controllers\TmaAppController;
use Webkul\Tma\Http\Middleware\TmaFrameHeadersMiddleware;

Route::get('/tma', [TmaAppController::class, 'index'])
    ->middleware([TmaFrameHeadersMiddleware::class])
    ->name('tma.app');
