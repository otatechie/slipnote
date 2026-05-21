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
        // Behind Cloudflare, the origin sees http:// and a private IP as the
        // client. Without this, uploads 401 (signatures computed for https://
        // don't match the http:// URL Laravel reconstructs from the request).
        $middleware->trustProxies(at: '*');

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
