<?php

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', 'https://www.sos-expat.com,https://sos-expat.com'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'Accept',
        'X-Requested-With',
        'X-Engine-Secret',
    ],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,
];
