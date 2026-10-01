<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | The React storefront lives on its own domain (localhost:5173 in dev,
    | *.vercel.app in production), so the API must answer preflights for it.
    |
    | Allowed origins come from the environment:
    |   FRONTEND_URL             — the storefront's primary origin
    |   CORS_ALLOWED_ORIGINS     — optional comma-separated extra origins
    |   CORS_ALLOWED_ORIGINS_PATTERNS — optional comma-separated regexes,
    |                              e.g. #^https://mlc-shop.*\.vercel\.app$#
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', (string) env('FRONTEND_URL', ''))),
    ))),

    'allowed_origins_patterns' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS_PATTERNS', '')),
    ))),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // The SPA authenticates with a Bearer token, not cookies.
    'supports_credentials' => false,

];
