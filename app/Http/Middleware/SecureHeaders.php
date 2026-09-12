<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecureHeaders
{
    /**
     * Routes that opt in to search indexing — the marketing landing and the
     * legal pages. Mirrors :indexable="true" in the Blade layout and the
     * Allow list in public/robots.txt; keep the three in sync.
     */
    private const INDEXABLE_ROUTES = ['welcome', 'privacy', 'terms'];

    public function handle(Request $request, Closure $next): Response
    {
        // One CSP nonce per request, minted BEFORE the view renders so the
        // @vite tags and the layouts' inline theme script carry the same
        // value the header below declares. Done here rather than in a
        // provider's boot(): anything that replaces the Vite instance after
        // boot (the test harness's withoutVite() does) would drop a nonce
        // minted there, and an empty 'nonce-' blocks every script.
        Vite::useCspNonce();

        $response = $next($request);

        // Everything except the public pages is noindex. The Blade/Inertia
        // layouts already emit a robots <meta>, but file downloads are PDFs
        // and slides — no HTML to put a tag in, so the header is the only
        // way to mark them. Without it a leaked /download/{token} link is
        // indexable, and robots.txt alone is advisory and per-crawler.
        if (! in_array($request->route()?->getName(), self::INDEXABLE_ROUTES, true)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // HSTS in production only — forces HTTPS for a year (incl. subdomains)
        // so the browser never downgrades to http:// after the first visit.
        // Dev runs over http://, where this header would be harmful.
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // CSP in production only — Vite dev server runs off a separate
        // origin we don't want polluting the prod policy.
        // Scripts: self plus this request's nonce (AppServiceProvider calls
        // Vite::useCspNonce(); @vite tags and the layouts' inline theme
        // script carry it). Never 'unsafe-inline' here.
        // Fonts are self-hosted (see @font-face in app.css), so no font CDN
        // is allowed; 'unsafe-inline' for styles is needed by the inline
        // style="" attributes Vue and the landing page use.
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
