<?php

return [
    'link' => 'Iniciar sesión con inicio de sesión único',
    'start' => [
        'title' => 'Inicio de sesión único',
        'intro' => 'Escribe tu correo de trabajo. Te llevaremos a la página de inicio de sesión de tu organización.',
        'email' => 'Correo de trabajo',
        'submit' => 'Continuar',
        'password' => 'Iniciar sesión con contraseña',
    ],
    'redirect' => [
        'title' => 'Te llevamos a tu proveedor de inicio de sesión…',
        'continue' => 'Continuar',
    ],
    'errors' => [
        'not-configured' => 'El inicio de sesión único no está configurado para este correo. Inicia sesión con tu contraseña o consulta a tu administrador.',
        'expired' => 'Este inicio de sesión caducó o ya se usó. Vuelve a empezar.',
        'cancelled' => 'El inicio de sesión se canceló en tu proveedor.',
        'provider' => 'No pudimos contactar con tu proveedor de inicio de sesión. Inténtalo de nuevo o pide a tu administrador que revise la configuración.',
        'token' => 'No se pudo verificar la respuesta de tu proveedor de inicio de sesión. Inténtalo de nuevo.',
        'unknown-user' => 'Esa cuenta no puede iniciar sesión aquí. Pide a tu administrador que te añada.',
        'unavailable' => 'Este espacio de trabajo no está disponible.',
        'use-sso' => 'Tu organización usa inicio de sesión único. Continúa abajo.',
    ],
    'configuration' => [
        'title' => 'Inicio de sesión único',
        'info' => 'Permite que tu equipo inicie sesión con las cuentas de tu organización.',
        'oidc' => [
            'title' => 'OpenID Connect',
            'info' => 'Funciona con Google Workspace, Microsoft Entra ID, Okta y otros proveedores OpenID Connect. Registra esta dirección de redirección en tu proveedor: <dirección de tu CRM>/admin/sso/callback',
            'enabled' => 'Activar el inicio de sesión único',
            'issuer' => 'Dirección del proveedor (issuer)',
            'issuer-info' => 'Para Google Workspace: https://accounts.google.com',
            'client-id' => 'ID de cliente',
            'client-secret' => 'Secreto de cliente',
            'domains' => 'Dominios de correo',
            'domains-info' => 'Separados por comas, por ejemplo example.com. Solo las direcciones de estos dominios pueden usar el inicio de sesión único.',
            'required' => 'Exigir inicio de sesión único para estos dominios',
            'required-info' => 'Se rechaza el inicio de sesión con contraseña para estas direcciones. Mantén una cuenta de administrador fuera de estos dominios por si el proveedor no está disponible.',
            'trust-mfa' => 'El proveedor exige verificación en dos pasos',
            'trust-mfa-info' => 'Omite la verificación en dos pasos del CRM tras el inicio de sesión único. Déjalo desactivado salvo que tu proveedor exija un segundo factor a todos.',
        ],
    ],
];
