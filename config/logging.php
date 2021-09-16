<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver'   => 'stack',
            'channels' => ['daily', 'bugsnag'],
        ],

        'bugsnag' => [
            'driver' => 'bugsnag',
        ],

        'single' => [
            'driver' => 'single',
            'path'   => storage_path('logs/laravel.log'),
            'level'  => 'debug',
        ],

        'seed_errors' => [
            'driver' => 'single',
            'path'   => storage_path('logs/seed_errors.log'),
            'level'  => 'debug'
        ],

        'daily' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/laravel.log'),
            'level'  => 'debug',
            'days'   => 7,
        ],

        'api' => [
            'driver'         => 'daily',
            'formatter'      => \App\Logs\EmptyLineFormatter::class,
            'path'           => storage_path('logs/api/api.log')
        ],

        'slack' => [
            'driver'   => 'slack',
            'url'      => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji'    => ':boom:',
            'level'    => 'critical',
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level'  => 'debug',
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level'  => 'debug',
        ],

        'cloudfront_api_key' => [
            'driver' => 'single',
            'tap'    => [App\Logs\CloudfrontApiKeyFormatter::class],
            'path'   => storage_path('logs/cloudfront_api_key.log'),
        ],
    ],

];
