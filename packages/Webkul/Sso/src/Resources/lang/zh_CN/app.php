<?php

return [
    'link' => '使用单点登录',
    'start' => [
        'title' => '单点登录',
        'intro' => '输入你的工作邮箱，我们会带你前往所在组织的登录页面。',
        'email' => '工作邮箱',
        'submit' => '继续',
        'password' => '改用密码登录',
    ],
    'redirect' => [
        'title' => '正在前往你的登录服务…',
        'continue' => '继续',
    ],
    'errors' => [
        'not-configured' => '此邮箱地址未设置单点登录。请使用密码登录，或联系管理员。',
        'expired' => '此次登录已过期或已被使用，请重新开始。',
        'cancelled' => '登录已在你的登录服务处取消。',
        'provider' => '无法连接你的登录服务。请重试，或请管理员检查单点登录设置。',
        'token' => '无法验证登录服务的响应，请重试。',
        'unknown-user' => '该账户无法在此登录，请让管理员添加你。',
        'unavailable' => '此工作区不可用。',
        'use-sso' => '你的组织使用单点登录，请在下方继续。',
    ],
    'configuration' => [
        'title' => '单点登录',
        'info' => '让团队使用组织账户登录。',
        'oidc' => [
            'title' => 'OpenID Connect',
            'info' => '支持 Google Workspace、Microsoft Entra ID、Okta 及其他 OpenID Connect 服务。请在服务商处登记此回调地址：<你的 CRM 地址>/admin/sso/callback',
            'enabled' => '启用单点登录',
            'issuer' => '服务商地址（issuer）',
            'issuer-info' => 'Google Workspace 请填：https://accounts.google.com',
            'client-id' => '客户端 ID',
            'client-secret' => '客户端密钥',
            'domains' => '邮箱域名',
            'domains-info' => '用逗号分隔，例如 example.com。只有这些域名的地址可以使用单点登录。',
            'required' => '这些域名必须使用单点登录',
            'required-info' => '这些地址将无法使用密码登录。请在这些域名之外保留一个管理员账户，以防服务商不可用。',
            'trust-mfa' => '服务商已强制两步验证',
            'trust-mfa-info' => '单点登录后跳过 CRM 自身的两步验证。除非服务商要求所有人使用第二因素，否则请保持关闭。',
        ],
    ],
];
