<?php

/**
 * Settings > Configuration > Telegram. Read through Webkul\Tma\Support\TelegramSettings, which
 * prefers each TELEGRAM_* value from the server's environment when it is set there.
 */
return [
    [
        'key' => 'general.telegram',
        'name' => 'tma::app.configuration.title',
        'info' => 'tma::app.configuration.info',
        'icon' => 'icon-setting',
        'sort' => 5,
    ], [
        'key' => 'general.telegram.bot',
        'name' => 'tma::app.configuration.bot.title',
        'info' => 'tma::app.configuration.bot.info',
        'sort' => 1,
        'fields' => [
            [
                'name' => 'bot_token',
                'title' => 'tma::app.configuration.bot.token',
                'info' => 'tma::app.configuration.bot.token-info',
                'type' => 'password',
            ], [
                'name' => 'bot_username',
                'title' => 'tma::app.configuration.bot.username',
                'info' => 'tma::app.configuration.bot.username-info',
                'type' => 'text',
            ], [
                'name' => 'webhook_secret',
                'title' => 'tma::app.configuration.bot.webhook-secret',
                'info' => 'tma::app.configuration.bot.webhook-secret-info',
                'type' => 'password',
            ],
        ],
    ],
];
