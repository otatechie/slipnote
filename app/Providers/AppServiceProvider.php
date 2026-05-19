<?php

namespace App\Providers;

use App\Tenancy\Tenancy;
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
        //
    }
}
