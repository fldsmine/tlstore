<?php

return [
    // CORS for the Flutter web app and local Flutter web development.
    // Public uploads under /storage are served by the web server directly;
    // their CORS headers are configured in the Apache/Nginx rules.
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    // Empty values are dropped: a null origin in this list makes the CORS
    // middleware compare against an empty string.
    'allowed_origins' => array_values(array_filter([
        env('WEB_APP_URL'),
        env('APP_URL'),
        env('ADMIN_APP_URL'),
    ])),
    'allowed_origins_patterns' => [
        '#^http://localhost(:\\d+)?$#',
        '#^http://127\\.0\\.0\\.1(:\\d+)?$#',
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => false,
];
