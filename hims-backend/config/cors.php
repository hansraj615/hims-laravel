<?php

$allowedOrigins = array_filter(array_map(
    'trim',
    explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173'))
));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'X-Request-Id',
        'X-XSRF-TOKEN',
        'X-Hospital-Id',
        'X-Branch-Id',
    ],

    'exposed_headers' => [
        'X-Request-Id',
    ],

    'max_age' => 0,

    'supports_credentials' => true,
];
