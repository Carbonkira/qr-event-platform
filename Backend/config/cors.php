<?php

// Allows the Vite dev frontend (http://localhost:5173) to call this API.
// Token auth (Sanctum bearer tokens) is used, not cookie auth, so
// supports_credentials stays false.

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
