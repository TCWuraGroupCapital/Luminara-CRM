<?php

return [
    'link' => 'Entrar com login único',
    'start' => [
        'title' => 'Login único',
        'intro' => 'Digite seu e-mail de trabalho. Vamos levar você à página de login da sua organização.',
        'email' => 'E-mail de trabalho',
        'submit' => 'Continuar',
        'password' => 'Entrar com senha',
    ],
    'redirect' => [
        'title' => 'Levando você ao seu provedor de login…',
        'continue' => 'Continuar',
    ],
    'errors' => [
        'not-configured' => 'O login único não está configurado para este e-mail. Entre com sua senha ou fale com o administrador.',
        'expired' => 'Este login expirou ou já foi usado. Comece de novo.',
        'cancelled' => 'O login foi cancelado no seu provedor.',
        'provider' => 'Não conseguimos contatar seu provedor de login. Tente novamente ou peça ao administrador para verificar as configurações.',
        'token' => 'Não foi possível verificar a resposta do seu provedor de login. Tente novamente.',
        'unknown-user' => 'Essa conta não pode entrar aqui. Peça ao administrador para adicionar você.',
        'unavailable' => 'Este espaço de trabalho não está disponível.',
        'use-sso' => 'Sua organização usa login único. Continue abaixo.',
    ],
    'configuration' => [
        'title' => 'Login único',
        'info' => 'Permita que sua equipe entre com as contas da sua organização.',
        'oidc' => [
            'title' => 'OpenID Connect',
            'info' => 'Funciona com Google Workspace, Microsoft Entra ID, Okta e outros provedores OpenID Connect. Registre este endereço de redirecionamento no seu provedor: <endereço do seu CRM>/admin/sso/callback',
            'enabled' => 'Ativar login único',
            'issuer' => 'Endereço do provedor (issuer)',
            'issuer-info' => 'Para Google Workspace: https://accounts.google.com',
            'client-id' => 'ID do cliente',
            'client-secret' => 'Segredo do cliente',
            'domains' => 'Domínios de e-mail',
            'domains-info' => 'Separados por vírgula, por exemplo example.com. Somente endereços desses domínios podem usar o login único.',
            'required' => 'Exigir login único para estes domínios',
            'required-info' => 'O login com senha é recusado para esses endereços. Mantenha uma conta de administrador fora desses domínios caso o provedor fique indisponível.',
            'trust-mfa' => 'O provedor exige verificação em duas etapas',
            'trust-mfa-info' => 'Pula a verificação em duas etapas do CRM após o login único. Deixe desativado, a menos que o provedor exija um segundo fator de todos.',
        ],
    ],
];
