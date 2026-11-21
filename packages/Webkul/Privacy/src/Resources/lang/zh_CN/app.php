<?php

return [
    'acl' => [
        'privacy' => '隐私请求',
    ],
    'panel' => [
        'title' => '隐私请求',
        'intro' => '处理此人查看或删除其数据的请求。',
        'export' => '下载其数据（JSON）',
        'erase-title' => '清除此人',
        'erase-warning' => '删除其姓名、联系方式、邮件、附件、备注、客户信息以及变更账本中的相关数据。商机和报价会保留。此操作无法撤销。',
        'confirm-label' => '输入 :name 以确认',
        'erase-button' => '永久清除',
        'erased-on' => '此人的个人数据已于 :date 清除。',
    ],
    'erased-success' => '个人数据已清除，商机和报价已保留。',
    'confirm-mismatch' => '输入的姓名不匹配，未清除任何内容。',
    'already-erased' => '此人已被清除。',
];
