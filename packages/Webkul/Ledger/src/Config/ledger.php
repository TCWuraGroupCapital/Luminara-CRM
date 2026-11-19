<?php

use Webkul\Ledger\Support\LocalSignedAnchorDriver;

return [
    'stream_key' => 'global',

    'signing_seed' => env('LUMINARA_LEDGER_SEED'),

    'anchor_driver' => env('LEDGER_ANCHOR_DRIVER', 'local'),

    'drivers' => [
        'local' => LocalSignedAnchorDriver::class,
    ],

    'epoch_max_blocks' => 1000,
];
