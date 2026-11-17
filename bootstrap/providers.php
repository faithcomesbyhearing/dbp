<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\IAMAPIProvider::class,
    SocialiteProviders\Manager\ServiceProvider::class,
    SocialiteProviders\Generators\GeneratorsServiceProvider::class,
    Intervention\Image\Laravel\ServiceProvider::class,
    Bugsnag\BugsnagLaravel\BugsnagServiceProvider::class,

    Mcamara\LaravelLocalization\LaravelLocalizationServiceProvider::class,
];
