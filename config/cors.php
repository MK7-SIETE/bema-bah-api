<?php

// The websites that may ask this API for data (CORS).
// The list of live websites is kept in the CORS_ALLOWED_ORIGINS setting on the server (Render > Environment),
// separated by commas, for example:
//   CORS_ALLOWED_ORIGINS=https://bema-bah.pages.dev,https://bemabah.com,https://www.bemabah.com
// so adding a website later needs no code change. Write each address exactly as the browser shows it:
// https://, no path, no "/" at the end.
$fromEnv = array_values(array_filter(array_map(
    fn ($origin) => rtrim(trim($origin), '/'),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
)));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_merge([
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'https://goldmotors-rho.vercel.app',
    ], $fromEnv))),

    'allowed_origins_patterns' => [
        '#^https://.*\.vercel\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
