<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        then: function () {
            $controllerNamespace = 'App\Http\Controllers';

            Route::middleware('web')
                ->namespace($controllerNamespace)
                ->group(base_path('routes/web.php'));

            Route::middleware('api')
                ->prefix('api')
                ->namespace($controllerNamespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('api')
                ->namespace($controllerNamespace)
                ->group(base_path('routes/apiV2.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        $middleware->append(\App\Http\Middleware\Cors::class);

        $middleware->group('web', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        ]);

        $middleware->group('api', [
            \App\Http\Middleware\APIVersion::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':1500,5',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        $middleware->alias([
            'auth'                  => \App\Http\Middleware\Authenticate::class,
            'auth.basic'            => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'cache.headers'         => \Illuminate\Http\Middleware\SetCacheHeaders::class,
            'can'                   => \Illuminate\Auth\Middleware\Authorize::class,
            'guest'                 => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'signed'                => \Illuminate\Routing\Middleware\ValidateSignature::class,
            'throttle'              => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified'              => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'APIToken'              => \App\Http\Middleware\APIToken::class,
            'AccessControl'         => \App\Http\Middleware\AccessControl::class,
            'localize'              => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRoutes::class,
            'localizationRedirect'  => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            'localeSessionRedirect' => \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            'localeCookieRedirect'  => \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            'localeViewPath'        => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'deploy/github',
        ]);

        $middleware->trimStrings(except: [
            'password',
            'password_confirmation',
        ]);

        $middleware->priority([
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\Authenticate::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (\Throwable $e, $request) {
            return (new \App\Exceptions\ApiExceptionRenderer())->render($e, $request);
        });

        $exceptions->reportable(function (\Throwable $exception) {
            $enableEmailExceptions = config('exceptions.emailExceptionEnabled');
            if ($enableEmailExceptions === '') {
                $enableEmailExceptions = config('exceptions.emailExceptionEnabledDefault');
            }
            if ($enableEmailExceptions) {
                (new \App\Exceptions\ApiExceptionRenderer())->sendEmail($exception);
            }

            $sentry_dsn = config('sentry.dsn');
            if ($sentry_dsn && config('app.env') == 'production' && app()->bound('sentry')) {
                app('sentry')->captureException($exception);
            }
        });
    })
    ->create();
