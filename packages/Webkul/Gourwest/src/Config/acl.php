<?php

return [
    [
        'key' => 'gourwest',
        'name' => 'gourwest::app.acl.title',
        'route' => ['admin.gourwest.index', 'admin.gourwest.overview'],
        'sort' => 2,
    ], [
        'key' => 'gourwest.customers',
        'name' => 'gourwest::app.acl.customers',
        'route' => ['admin.gourwest.seed', 'admin.gourwest.deduplicate'],
        'sort' => 1,
    ], [
        'key' => 'gourwest.integrations',
        'name' => 'gourwest::app.acl.integrations',
        'route' => ['admin.gourwest.integrations.sync', 'admin.gourwest.integrations.replay'],
        'sort' => 2,
    ], [
        'key' => 'gourwest.loyalty',
        'name' => 'gourwest::app.acl.loyalty',
        'route' => 'admin.gourwest.loyalty.adjust',
        'sort' => 3,
    ], [
        'key' => 'gourwest.sla',
        'name' => 'gourwest::app.acl.sla',
        'route' => ['admin.gourwest.tickets.store', 'admin.gourwest.tickets.resolve'],
        'sort' => 4,
    ], [
        'key' => 'gourwest.benchmark',
        'name' => 'gourwest::app.acl.benchmark',
        'route' => 'admin.gourwest.benchmark',
        'sort' => 5,
    ],
];
