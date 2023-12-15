<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'ses' => [
        'key'    => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => 'us-east-1',
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    // Bible APIs
    'bibleIs' => [
        'key' => env('BIS_API_KEY'),
        'secret' => env('BIS_API_SECRET')
    ],

    'talkingBibles' => [
        'key' => env('TALKING_BIBLES_API')
    ],

    'arclight' => [
        'key' => env('ARCLIGHT_API_KEY'),
        'url' => env('ARCLIGHT_API_URL', 'https://api.arclight.org/v2/'),
        // arclight service timeout in seconds
        'service_timeout' => env('ARCLIGHT_SERVICE_TIMEOUT', 5)
    ],
    'iam' => [
        'url' => env('IAM_API_URL', ''),
        'enabled' => env('IAM_ENABLED', false),
        'service_timeout' => env('IAM_SERVICE_TIMEOUT', 5)
    ],

    // Testing

    'loaderIo' => [
        'key' => env('LOADER_IO')
    ],

    // CDN server
    'cdn' => [
        'server' => env('CDN_SERVER', 'd1gd73roq7kqw6.cloudfront.net'),
        'server_v2' => env('CDN_SERVER_V2', 'cloud.faithcomesbyhearing.com'),
        'video_server_v2' => env('CDN_VIDEO_SERVER_V2', 'video.dbt.io'),
        'fonts_server' => env('CDN_FONTS_SERVER', 'cdn.bible.build'),
        'country_image_server' => env('MCDN_COUNTRY_IMAGE', 'dbp-mcdn.s3.us-west-2.amazonaws.com')
    ]
];
