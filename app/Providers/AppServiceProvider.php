<?php

namespace App\Providers;

use App\Tenancy\Tenancy;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One resolved workspace per request/lifecycle. ResolveWorkspace
        // middleware sets it; the WorkspaceScope global scope reads it.
        $this->app->scoped(Tenancy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Behind Cloudflare (or any HTTPS-terminating proxy), the origin
        // request arrives as http://. Force generated URLs to https so
        // Inertia POSTs, assets, and route() calls don't trigger
        // mixed-content blocks in the browser.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Which proxies may speak for the client (see config/noteshare.php).
        // Set here rather than in bootstrap/app.php because config is not
        // loaded yet when that closure runs.
        $proxies = config('noteshare.trusted_proxies', []);
        if ($proxies !== []) {
            TrustProxies::at($proxies);
        }
    }
}
