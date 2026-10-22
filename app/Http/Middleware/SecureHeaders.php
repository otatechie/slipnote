<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecureHeaders
{
    private const INDEXABLE_ROUTES = ['welcome', 'privacy', 'terms', 'lecturers'];

    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        if (! in_array($request->route()?->getName(), self::INDEXABLE_ROUTES, true)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (app()->environment('production')) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-".Vite::cspNonce()."'",
                "style-src 'self' 'unsafe-inline'",
                "font-src 'self'",
                "img-src 'self' data:",
                "connect-src 'self'",
                "frame-ancestors 'self'",
                "base-uri 'self'",
                "form-action 'self'",
            ]));
        }

        return $response;
    }
}
