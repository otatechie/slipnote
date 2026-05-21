<?php

namespace App\Providers;

use App\Tenancy\Tenancy;
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
        // Livewire AJAX, assets, and route() calls don't trigger
        // mixed-content blocks in the browser.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
