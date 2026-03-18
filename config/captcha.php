<?php

return [
    'secret'  => env('CHECKBOX_RECAPTCHA_SECRETKEY', ''),
    'sitekey' => env('CHECKBOX_RECAPTCHA_SITEKEY', ''),
    'options' => [
        'timeout' => 30,
    ],
];
