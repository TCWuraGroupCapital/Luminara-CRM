<?php

use Illuminate\Support\Facades\Route;
use Webkul\Ledger\Http\Controllers\PublicReceiptController;

Route::group(['middleware' => ['web', 'throttle:60,1']], function () {
    Route::get('/receipts/{receiptCode}', [PublicReceiptController::class, 'show'])
        ->where('receiptCode', '[A-Za-z0-9\-]+')
        ->name('ledger.receipts.show');
});
