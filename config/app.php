<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    */

    'name'        => env('APP_NAME', 'digital_bible_platform'),
    'server_name' => env('APP_SERVER_NAME', 'Biblebrain dev'),
    'contact'     => env('APP_SITE_CONTACT', 'info@fcbhmail.net'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    */

    'env' => env('APP_ENV', 'prod'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    */

    'debug' => env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    */

    'url' => env('APP_URL', 'https://4.dbt.io'),
    'url_podcast' => env('APP_URL_PODCAST', 'https://4.dbt.io'),
    'api_url' => env('API_URL', 'https://b4.dbt.io'),
    'get_started_url' => env('GET_STARTED_URL', 'https://github.com/faithcomesbyhearing/dbp/blob/master/doc/STARTING.md'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    */

    'locale' => 'eng',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    */

    'fallback_locale' => 'eng',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    */

    'aliases' => [
        'Socialite'    => Laravel\Socialite\Facades\Socialite::class,
        'Image'        => Intervention\Image\Laravel\Facades\Image::class,
        'i18n'         => Mcamara\LaravelLocalization\Facades\LaravelLocalization::class,
        'LaravelLocalization' => Mcamara\LaravelLocalization\Facades\LaravelLocalization::class,
        'Bugsnag'      => Bugsnag\BugsnagLaravel\Facades\Bugsnag::class,
    ],

];
