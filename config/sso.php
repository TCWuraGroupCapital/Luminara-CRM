<?php

return [
    /**
     * Shows "Sign in with single sign-on" and answers /admin/sso. Each business then turns it
     * on and enters its provider under Settings > Configuration > Single sign-on.
     */
    'enabled' => (bool) env('LUMINARA_SSO', false),

    /**
     * Provider addresses must be public HTTPS hosts, so a business cannot make the server call
     * into its own network. Only for a local test provider (for example Keycloak in Docker).
     */
    'allow_private_issuers' => (bool) env('LUMINARA_SSO_ALLOW_PRIVATE_ISSUERS', false),

    /**
     * How long a started sign-in may take before the provider sends the user back.
     */
    'max_age_seconds' => 600,
];
