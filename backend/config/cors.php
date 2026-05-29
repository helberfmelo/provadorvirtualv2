<?php

$localOrigins = [
    'http://127.0.0.1:5173',
    'http://127.0.0.1:5174',
    'http://127.0.0.1:5175',
    'http://127.0.0.1:5176',
    'http://127.0.0.1:5177',
    'http://localhost:5173',
    'http://localhost:5174',
    'http://localhost:5175',
    'http://localhost:5176',
    'http://localhost:5177',
];
$allowedOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', env('CORS_ALLOWED_ORIGINS', implode(',', $localOrigins)))
)));

return [
    'paths' => [
        'api/v1/auth/*',
        'api/v1/me',
        'api/v1/merchant/*',
        'api/v1/products*',
        'api/v1/brands*',
        'api/v1/categories*',
        'api/v1/fit-profiles*',
        'api/v1/measurement-*',
        'api/v1/widget-install',
        'api/v1/integrations*',
        'api/v1/imports*',
        'api/v1/ai*',
        'api/v1/analytics*',
        'api/v1/audit-logs',
        'api/v1/saas*',
        'api/v1/health',
        'api/v1/ops/status',
        'api/v1/demo/*',
    ],

    'allowed_methods' => ['*'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => false,
];
