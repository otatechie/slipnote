<?php

use App\Http\Middleware\CaptureReferral;
use App\Http\Middleware\ClientIpFromHeader;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveWorkspace;
use App\Http\Middleware\SecureHeaders;
use App\Http\Middleware\TallyArrival;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        // First, so sessions and rate limits all see the visitor's address.
        $middleware->prepend(ClientIpFromHeader::class);
        $middleware->append(SecureHeaders::class);
        $middleware->web(append: [
            HandleInertiaRequests::class,
            CaptureReferral::class,
            TallyArrival::class,
        ]);
        $middleware->alias([
            'workspace' => ResolveWorkspace::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Inertia shows any other error response as a raw page in a pop-up. A
        // form sent from a page left open past the session (419), or one that
        // hit a server error, goes back to that page with a message instead.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($request->isMethod('GET') || ! $request->header('X-Inertia')) {
                return $response;
            }

            $message = match (true) {
                $response->getStatusCode() === 419 => 'This page was open a long time, so that didn’t go through. Please try again.',
                $response->getStatusCode() >= 500 && ! config('app.debug') => 'Something went wrong on our side. Please try again.',
                default => null,
            };
            if ($message === null) {
                return $response;
            }

            // The upload form shows its own errors and opens itself for them.
            return str_ends_with($request->path(), '/upload')
                ? back()->withErrors(['files' => $message])
                : back()->with('notice', $message);
        });
    })->create();
