<?php

return [
    [
        'key' => 'settings.other_settings.tma',
        'name' => 'tma::app.acl.tma',
        'route' => [
            'admin.tma.pairing.index',
            'admin.tma.pairing.code',
            'admin.tma.pairing.destroy',
            'admin.tma.passkeys.challenge',
            'admin.tma.passkeys.store',
            'admin.tma.passkeys.destroy',
        ],
        'sort' => 9,
    ],
];
