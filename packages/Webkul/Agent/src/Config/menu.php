<?php

return [
    [
        'key' => 'agent_briefing',
        'name' => 'agent::app.menu.briefing',
        'route' => 'admin.agent.briefing.index',
        'sort' => 0,
        'icon-class' => 'icon-note',
    ], [
        'key' => 'agent_desk',
        'name' => 'agent::app.menu.desk',
        'route' => 'admin.agent.desk.index',
        'sort' => 1,
        'icon-class' => 'icon-dashboard',
    ], [
        'key' => 'agent_assistant',
        'name' => 'agent::app.menu.assistant',
        'route' => 'admin.agent.assistant.index',
        'sort' => 2,
        'icon-class' => 'icon-activity',
    ], [
        'key' => 'settings.other_settings.agent_approvals',
        'name' => 'agent::app.menu.approvals',
        'info' => 'agent::app.menu.approvals-info',
        'route' => 'admin.agent.approvals.index',
        'sort' => 7,
        'icon-class' => 'icon-setting',
    ],
];
