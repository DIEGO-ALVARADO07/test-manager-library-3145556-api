<?php

/** Only the configured frontend origin may call the API (api-contract §4). */
return [
    'paths' => ['api/*', 'health'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [env('CORS_ORIGIN', 'http://localhost:5173')],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => false,
];
