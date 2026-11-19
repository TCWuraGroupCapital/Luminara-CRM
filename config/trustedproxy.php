<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated proxy IPs/CIDRs (or "*" behind an isolated Cloudflare
    | Tunnel / private ingress) allowed to forward X-Forwarded-* headers.
    | Allowed header bitmask is configured in bootstrap/app.php.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
