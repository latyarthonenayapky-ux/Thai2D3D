<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Only the reverse proxy sitting in front of the app may set X-Forwarded-*
    | headers. Cloudflare Tunnel and nginx run on the same host as the app, so
    | only 127.0.0.1 is trusted by default. Widen this with the comma-separated
    | TRUSTED_PROXIES environment variable (IP addresses or CIDR ranges) when
    | the proxy runs elsewhere. Never set it to '*' on a public deployment:
    | any client could then spoof its own IP through X-Forwarded-For.
    |
    */

    'proxies' => env('TRUSTED_PROXIES', '127.0.0.1'),

];
