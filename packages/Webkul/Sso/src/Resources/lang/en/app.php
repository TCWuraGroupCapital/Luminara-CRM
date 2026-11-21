<?php

return [
    'link' => 'Sign in with single sign-on',
    'start' => [
        'title' => 'Single sign-on',
        'intro' => 'Enter your work email. We will take you to your organisation\'s sign-in page.',
        'email' => 'Work email',
        'submit' => 'Continue',
        'password' => 'Sign in with a password instead',
    ],
    'redirect' => [
        'title' => 'Taking you to your sign-in provider…',
        'continue' => 'Continue',
    ],
    'errors' => [
        'not-configured' => 'Single sign-on is not set up for this email address. Sign in with your password, or ask your administrator.',
        'expired' => 'This sign-in expired or was already used. Please start again.',
        'cancelled' => 'The sign-in was cancelled at your provider.',
        'provider' => 'We could not reach your sign-in provider. Try again, or ask your administrator to check the single sign-on settings.',
        'token' => 'Your sign-in provider\'s answer could not be verified. Please try again.',
        'unknown-user' => 'That account cannot sign in here. Ask your administrator to add you.',
        'unavailable' => 'This workspace is not available.',
        'use-sso' => 'Your organisation signs in with single sign-on. Continue below.',
    ],
    'configuration' => [
        'title' => 'Single sign-on',
        'info' => 'Let your team sign in with your organisation\'s accounts.',
        'oidc' => [
            'title' => 'OpenID Connect',
            'info' => 'Works with Google Workspace, Microsoft Entra ID, Okta and other OpenID Connect providers. Register this redirect address with your provider: <your CRM address>/admin/sso/callback',
            'enabled' => 'Turn on single sign-on',
            'issuer' => 'Provider address (issuer)',
            'issuer-info' => 'For Google Workspace: https://accounts.google.com',
            'client-id' => 'Client ID',
            'client-secret' => 'Client secret',
            'domains' => 'Email domains',
            'domains-info' => 'Comma-separated, for example example.com. Only addresses in these domains can use single sign-on.',
            'required' => 'Require single sign-on for these domains',
            'required-info' => 'Password sign-in is refused for these addresses. Keep one administrator account outside these domains in case the provider is unavailable.',
            'trust-mfa' => 'The provider enforces two-factor sign-in',
            'trust-mfa-info' => 'Skip the CRM\'s own two-factor step after single sign-on. Leave this off unless your provider requires a second factor for everyone.',
        ],
    ],
];
