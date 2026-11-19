<?php

use Illuminate\Support\Facades\Route;
use Webkul\Ledger\Http\Controllers\LedgerProofController;

Route::group([
    'middleware' => ['api', 'throttle:120,1'],
    'prefix' => 'api/ledger',
], function () {
    Route::get('/blocks/{codeOrHash}/proof', [LedgerProofController::class, 'show'])
        ->where('codeOrHash', '[A-Za-z0-9\-]+')
        ->name('api.ledger.blocks.proof');
});
