<?php

$env = env('APP_ENV', 'production');
$isLocalOrTesting = in_array($env, ['local', 'testing'], true);

$configuredOrigins = array_values(array_filter(
    array_map('trim', explode(',', (string) env('FRONTEND_URL', env('APP_FRONTEND_URL', ''))))
));

$localOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://localhost:4173',
    'http://127.0.0.1:4173',
];

$allowedOrigins = $isLocalOrTesting
    ? array_values(array_unique(array_merge($configuredOrigins, $localOrigins)))
    : $configuredOrigins;

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
