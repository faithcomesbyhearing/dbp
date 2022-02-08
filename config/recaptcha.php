<?php

return [
    'api_site_key'                  => env('CHECKBOX_RECAPTCHA_SITEKEY', ''),
    'api_secret_key'                => env('CHECKBOX_RECAPTCHA_SECRETKEY', ''),
    // changed in v4.0.0
    'version'                       => 'v2', // supported: "v3"|"v2"|"invisible"
    // @since v3.4.3 changed in v4.0.0
    'curl_timeout'                  => 10,
    'skip_ip'                       => [], // array of IP addresses - String: dotted quad format e.g.: "127.0.0.1"
    // @since v3.2.0 changed in v4.0.0
    'default_validation_route'      => 'biscolab-recaptcha/validate',
    // @since v3.2.0 changed in v4.0.0
    'default_token_parameter_name' => 'token',
    // @since v3.6.0 changed in v4.0.0
    'default_language'             => null,
    // @since v4.0.0
    'default_form_id'              => 'biscolab-recaptcha-invisible-form', // Only for "invisible" reCAPTCHA
    // @since v4.0.0
    'explicit'                     => false, // true|false
    // @since v4.3.0
    'api_domain'                   => "www.google.com", // default value is "www.google.com"
    // @since v4.0.0
    'tag_attributes'               => [
        'theme'                    => 'light', // "light"|"dark"
        'size'                     => 'normal', // "normal"|"compact"
        'tabindex'                 => 0,
        'callback'                 => null, // DO NOT SET "biscolabOnloadCallback"
        'expired-callback'         => null, // DO NOT SET "biscolabOnloadCallback"
        'error-callback'           => null, // DO NOT SET "biscolabOnloadCallback"
    ]
];
