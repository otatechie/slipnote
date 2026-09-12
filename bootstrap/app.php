<?php

use App\Http\Middleware\ResolveWorkspace;
use App\Http\Middleware\SecureHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trusted proxies are configured in AppServiceProvider from
        // TRUSTED_PROXIES, not here: this closure runs before config is
        // loaded, and '*' (the old value) let any client pick its own IP via
        // X-Forwarded-For, which every per-IP rate limit is keyed on.
        // Generated URLs are already forced to https by the provider, so
        // proxy trust is only about the client IP, not the scheme.

        $middleware->append(SecureHeaders::class);
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'workspace' => ResolveWorkspace::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
