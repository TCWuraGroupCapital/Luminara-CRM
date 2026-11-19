<?php

return [
    [
        'key' => 'settings.other_settings.experiments',
        'name' => 'experiment::app.acl.title',
        'route' => ['admin.settings.experiments.index', 'admin.settings.experiments.stats'],
        'sort' => 2,
    ], [
        'key' => 'settings.other_settings.experiments.create',
        'name' => 'experiment::app.acl.create',
        'route' => 'admin.settings.experiments.store',
        'sort' => 1,
    ], [
        'key' => 'settings.other_settings.experiments.edit',
        'name' => 'experiment::app.acl.edit',
        'route' => 'admin.settings.experiments.update',
        'sort' => 2,
    ], [
        'key' => 'settings.other_settings.experiments.delete',
        'name' => 'experiment::app.acl.delete',
        'route' => 'admin.settings.experiments.delete',
        'sort' => 3,
    ],
];
