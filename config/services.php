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

    'facebook' => [
        'client_id'     => env('FB_ID'),
        'client_secret' => env('FB_SECRET'),
        'redirect'      => env('FB_REDIRECT'),
    ],

    'twitter' => [
        'client_id'     => env('TW_ID'),
        'client_secret' => env('TW_SECRET'),
        'redirect'      => env('TW_REDIRECT'),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_ID'),
        'client_secret' => env('GOOGLE_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT'),
    ],

    'github' => [
        'client_id'     => env('GITHUB_ID'),
        'client_secret' => env('GITHUB_SECRET'),
        'redirect'      => env('GITHUB_REDIRECT'),
    ],

    'twitch' => [
        'client_id'     => env('TWITCH_KEY'),
        'client_secret' => env('TWITCH_SECRET'),
        'redirect'      => env('TWITCH_REDIRECT_URI'),
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
        'key' => env('ARCLIGHT_API'),
        'url' => env('ARCLIGHT_API_URL', 'https://api.arclight.org/v2/'),
        // arclight service timeout in seconds
        'service_timeout' => env('ARCLIGHT_SERVICE_TIMEOUT', 5)
    ],

    // Testing

    'loaderIo' => [
        'key' => env('LOADER_IO')
    ],

    // CDN server
    'cdn' => [
        'server' => env('CDN_SERVER', 'content.cdn.dbp-prod.dbp4.org'),
        'server_v2' => env('CDN_SERVER_V2', 'fcbhabdm.s3.amazonaws.com'),
        'video_server' => env('CDN_VIDEO_SERVER', 'content.cdn.dbp-vid.dbp4.org'),
        'video_server_v2' => env('CDN_VIDEO_SERVER_V2', 'video.dbt.io'),
        'fonts_server' => env('CDN_FONTS_SERVER', 'cdn.bible.build'),
        'country_image_server' => env('MCDN_COUNTRY_IMAGE', 'dbp-mcdn.s3.us-west-2.amazonaws.com')
    ]

];
