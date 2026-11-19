<?php

return [
    [
        'key' => 'agent_briefing',
        'name' => 'agent::app.acl.briefing',
        'route' => [
            'admin.agent.briefing.index',
            'admin.agent.briefing.passports.store',
            'admin.agent.briefing.passports.toggle',
            'admin.agent.briefing.playbook.update',
            'admin.agent.briefing.intake',
            'admin.agent.briefing.review',
        ],
        'sort' => 0,
    ], [
        'key' => 'agent_desk',
        'name' => 'agent::app.acl.desk',
        'route' => ['admin.agent.desk.index', 'admin.agent.desk.data'],
        'sort' => 1,
    ], [
        'key' => 'agent_assistant',
        'name' => 'agent::app.acl.assistant',
        'route' => [
            'admin.agent.assistant.index',
            'admin.agent.assistant.send',
            'admin.agent.assistant.stop',
            'admin.agent.assistant.reset',
        ],
        'sort' => 2,
    ], [
        'key' => 'settings.other_settings.agent_approvals',
        'name' => 'agent::app.acl.approvals',
        'route' => [
            'admin.agent.approvals.index',
            'admin.agent.approvals.approve',
            'admin.agent.approvals.reject',
            'admin.agent.approvals.review',
        ],
        'sort' => 7,
    ],
];
