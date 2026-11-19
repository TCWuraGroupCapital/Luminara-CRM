<?php

return [
    [
        'key' => 'settings.other_settings.security_mfa',
        'name' => 'security::app.menu.mfa',
        'info' => 'security::app.menu.mfa-info',
        'route' => 'admin.security.mfa.setup',
        'sort' => 5,
        'icon-class' => 'icon-setting',
    ],
    [
        'key' => 'settings.other_settings.security_audit',
        'name' => 'security::app.menu.audit-logs',
        'info' => 'security::app.menu.audit-logs-info',
        'route' => 'admin.security.audit_logs.index',
        'sort' => 6,
        'icon-class' => 'icon-setting',
    ],
];
