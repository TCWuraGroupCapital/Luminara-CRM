<?php

return [
    'webhook_secret' => env('GOURWEST_WEBHOOK_SECRET', ''),

    'sla_target_ms' => (int) env('GOURWEST_SLA_TARGET_MS', 3000),

    'uptime_target_percent' => (float) env('GOURWEST_UPTIME_TARGET_PERCENT', 99.9),

    'capacity_target_records' => (int) env('GOURWEST_CAPACITY_TARGET_RECORDS', 5000000),

    'loyalty_tiers' => [
        'Platinum' => 5000,
        'Gold' => 2000,
        'Silver' => 750,
        'Bronze' => 0,
    ],
];
