<?php

namespace App\Support;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Counts arrivals: a page load that came from outside the site. Internal
 * navigation is not counted, so the numbers answer "how many times did
 * someone come here, from where, and how did they find it" rather than
 * measuring clicks.
 *
 * Nothing here identifies anyone. The country comes from the CDN's header
 * (never from an IP lookup), the source is a referring host or a bucket,
 * and all that is stored is a daily count per combination.
 */
class VisitTally
{
    /** Pages worth counting: the front door and the boards themselves. */
    public const ROUTES = ['welcome', 'start', 'courses.index', 'course.show'];

    /** Link-preview fetchers and crawlers, so an unfurl in a WhatsApp group is not a visit. */
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|discord|slack|curl|wget|python|headless|lighthouse|monitor/i';

    /** Referring hosts folded into one name, so google.com.gh and google.com.sa read as one. */
    private const KNOWN_SOURCES = [
        'google' => '/(^|\.)google\.|^com\.google\.android\.googlequicksearchbox$/',
        'bing' => '/(^|\.)bing\.com$/',
        'duckduckgo' => '/(^|\.)duckduckgo\.com$/',
        'chatgpt' => '/(^|\.)(chatgpt\.com|openai\.com)$/',
        'whatsapp' => '/whatsapp/',
        'telegram' => '/(^|\.)(t\.me|telegram\.org)$|telegram/',
        'facebook' => '/(^|\.)(facebook\.com|fb\.com|fb\.me)$|facebook\.katana/',
        'instagram' => '/instagram/',
        'x' => '/(^|\.)(t\.co|twitter\.com|x\.com)$/',
        'linkedin' => '/(^|\.)(linkedin\.com|lnkd\.in)$/',
        'github' => '/(^|\.)github\.com$/',
    ];

    public static function shouldCount(Request $request): bool
    {
        if (! $request->isMethod('GET') || ! in_array($request->route()?->getName(), self::ROUTES, true)) {
            return false;
        }

        $agent = (string) $request->userAgent();
        if ($agent === '' || preg_match(self::BOT_PATTERN, $agent)) {
            return false;
        }

        // Moving around inside the site is not an arrival.
        $refHost = self::referrerHost($request);

        return $refHost === null || $refHost !== self::normalizeHost($request->getHost());
    }

    public static function record(Request $request): void
    {
        $workspace = $request->route('workspace');

        DB::table('visit_tallies')->upsert(
            [[
                'day' => now()->toDateString(),
                'workspace_id' => $workspace instanceof Workspace ? $workspace->id : 0,
                'country' => self::country($request),
                'source' => self::source($request),
                'arrivals' => 1,
            ]],
            ['day', 'workspace_id', 'country', 'source'],
            ['arrivals' => DB::raw('arrivals + 1')],
        );
    }

    /**
     * Cloudflare's CF-IPCountry, believed only when the request really came
     * through a trusted proxy: from anyone else it is just a header the
     * client chose. XX is "unknown" and T1 is Tor, both stored as unknown.
     */
    public static function country(Request $request): string
    {
        if (! $request->isFromTrustedProxy()) {
            return '';
        }

        $code = strtoupper((string) $request->header('CF-IPCountry'));

        return preg_match('/^[A-Z]{2}$/', $code) && ! in_array($code, ['XX', 'T1'], true) ? $code : '';
    }

    public static function source(Request $request): string
    {
        // An ambassador link says how it was found better than any referrer.
        if (is_string($request->query('ref')) && $request->query('ref') !== '') {
            return 'ambassador';
        }

        $host = self::referrerHost($request);
        if ($host === null) {
            // Typed, bookmarked, scanned from a QR code, opened from the
            // installed app, or tapped in an app that sends no referrer
            // (WhatsApp usually doesn't).
            return 'direct';
        }

        foreach (self::KNOWN_SOURCES as $name => $pattern) {
            if (preg_match($pattern, $host)) {
                return $name;
            }
        }

        return substr($host, 0, 64);
    }

    private static function referrerHost(Request $request): ?string
    {
        $referer = $request->headers->get('referer');
        if (! is_string($referer) || $referer === '') {
            return null;
        }

        $host = parse_url($referer, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? self::normalizeHost($host) : null;
    }

    private static function normalizeHost(string $host): string
    {
        $host = strtolower($host);

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
