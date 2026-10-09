<?php

use Illuminate\Support\Facades\Facade;

return [
    'name'            => env('APP_NAME', 'Mukuba Motors API'),
    'env'             => env('APP_ENV', 'production'),
    'debug'           => (bool) env('APP_DEBUG', false),
    'url'             => env('APP_URL', 'http://localhost:8000'),
    'timezone'        => env('APP_TIMEZONE', 'Africa/Lusaka'),
    'locale'          => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale'    => env('APP_FAKER_LOCALE', 'en_US'),
    'cipher'          => 'AES-256-CBC',
    'key'             => env('APP_KEY'),
    'previous_keys'   => array_filter(explode(',', env('APP_PREVIOUS_KEYS', ''))),

    // Super admin credentials
    'super_admin_email'    => env('SUPER_ADMIN_EMAIL', 'superadmin@platform.dev'),
    'super_admin_password' => env('SUPER_ADMIN_PASSWORD', ''),

    'maintenance' => ['driver' => 'file'],
    'providers'   => Illuminate\Support\ServiceProvider::defaultProviders()->merge([
        App\Providers\AppServiceProvider::class,
    ])->toArray(),
];
