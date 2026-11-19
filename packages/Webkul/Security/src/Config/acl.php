<?php

return [
    [
        'key' => 'settings.other_settings.security_mfa',
        'name' => 'security::app.menu.mfa',
        'route' => 'admin.security.mfa.setup',
        'sort' => 5,
    ],
    [
        'key' => 'settings.other_settings.security_audit',
        'name' => 'security::app.acl.audit-logs',
        'route' => 'admin.security.audit_logs.index',
        'sort' => 6,
    ],
];
