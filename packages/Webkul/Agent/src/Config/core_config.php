<?php

/**
 * Settings > Configuration > AI Assistant. Read through Webkul\Agent\Support\AssistantSettings,
 * which prefers LUMINARA_ASSISTANT_* from the server's environment when a key is set there.
 */
return [
    [
        'key' => 'general.assistant',
        'name' => 'agent::app.configuration.title',
        'info' => 'agent::app.configuration.info',
        'icon' => 'icon-setting',
        'sort' => 4,
    ], [
        'key' => 'general.assistant.settings',
        'name' => 'agent::app.configuration.settings.title',
        'info' => 'agent::app.configuration.settings.info',
        'sort' => 1,
        'fields' => [
            [
                'name' => 'api_key',
                'title' => 'agent::app.configuration.settings.api-key',
                'info' => 'agent::app.configuration.settings.api-key-info',
                'type' => 'password',
            ], [
                'name' => 'model',
                'title' => 'agent::app.configuration.settings.model',
                'info' => 'agent::app.configuration.settings.model-info',
                'type' => 'text',
            ], [
                'name' => 'base_url',
                'title' => 'agent::app.configuration.settings.base-url',
                'info' => 'agent::app.configuration.settings.base-url-info',
                'type' => 'text',
            ],
        ],
    ],
];
