<?php
 
namespace App\Providers;
 
use App\Services\IAMAPI\IAMAPIClientService as Client;
use Illuminate\Support\ServiceProvider;
 
class IAMAPIProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Client::class, function () {
            return new Client(
                config('services.iam.url'),
                config('services.iam.enabled'),
                config('services.iam.service_timeout')
            );
        });
    }
}
