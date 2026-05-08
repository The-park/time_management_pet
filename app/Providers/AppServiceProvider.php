<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force every URL the framework generates (route(), url(), asset())
        // to use https:// when running in production. cPanel/shared hosts
        // terminate TLS at the proxy and we trust X-Forwarded-Proto via
        // trustProxies(); pairing it with this URL::forceScheme guarantees
        // mixed-content-free pages even when env detection lags.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
