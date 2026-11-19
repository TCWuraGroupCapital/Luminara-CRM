<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),

    'bot_username' => env('TELEGRAM_BOT_USERNAME', ''),

    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', ''),

    'init_data_ttl_seconds' => 300,

    'token_ttl_minutes' => 120,

    'ton_relayer_url' => env('TON_ANCHOR_RELAYER_URL', ''),

    'ton_contract_address' => env('TON_ANCHOR_CONTRACT', ''),
];
