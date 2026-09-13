<?php

namespace App\Http\Middleware;

use App\Support\Referral;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a ?ref= from any page. The landing page is a Route::view with no
 * controller to set a cookie in, and a ref should count wherever it lands.
 */
class CaptureReferral
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && ($cookie = Referral::capture($request)) !== null) {
            Cookie::queue($cookie);
        }

        return $next($request);
    }
}
