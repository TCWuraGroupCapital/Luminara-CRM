<?php

/**
 * Settings > Configuration > Single sign-on, per business. Read through
 * Webkul\Sso\Support\SsoSettings; the client secret is a password field, so it is stored encrypted.
 */
return [
    [
        'key' => 'general.sso',
        'name' => 'sso::app.configuration.title',
        'info' => 'sso::app.configuration.info',
        'icon' => 'icon-setting',
        'sort' => 5,
    ], [
        'key' => 'general.sso.oidc',
        'name' => 'sso::app.configuration.oidc.title',
        'info' => 'sso::app.configuration.oidc.info',
        'sort' => 1,
        'fields' => [
            [
                'name' => 'enabled',
                'title' => 'sso::app.configuration.oidc.enabled',
                'type' => 'boolean',
            ], [
                'name' => 'issuer',
                'title' => 'sso::app.configuration.oidc.issuer',
                'info' => 'sso::app.configuration.oidc.issuer-info',
                'type' => 'text',
                'default' => 'https://accounts.google.com',
                'validation' => 'nullable|url',
            ], [
                'name' => 'client_id',
                'title' => 'sso::app.configuration.oidc.client-id',
                'type' => 'text',
            ], [
                'name' => 'client_secret',
                'title' => 'sso::app.configuration.oidc.client-secret',
                'type' => 'password',
            ], [
                'name' => 'domains',
                'title' => 'sso::app.configuration.oidc.domains',
                'info' => 'sso::app.configuration.oidc.domains-info',
                'type' => 'text',
            ], [
                'name' => 'required',
                'title' => 'sso::app.configuration.oidc.required',
                'info' => 'sso::app.configuration.oidc.required-info',
                'type' => 'boolean',
            ], [
                'name' => 'trust_mfa',
                'title' => 'sso::app.configuration.oidc.trust-mfa',
                'info' => 'sso::app.configuration.oidc.trust-mfa-info',
                'type' => 'boolean',
            ],
        ],
    ],
];
