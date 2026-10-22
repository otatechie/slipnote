<?php

namespace App\Providers;

use App\Http\Middleware\ClientIpFromHeader;
use App\Tenancy\Tenancy;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Tenancy::class);
    }

    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        $proxies = ClientIpFromHeader::trustedProxies();
        if ($proxies !== []) {
            TrustProxies::at($proxies);
        }
    }
}
