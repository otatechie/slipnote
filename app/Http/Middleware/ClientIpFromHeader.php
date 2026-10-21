<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Behind Cloudflare and Dokploy's Traefik, the request reaches the app from
 * Traefik, which by default replaces X-Forwarded-For with the Cloudflare
 * server's address: one address for everyone that server serves, so every
 * per-client rate limit would be shared by a whole city. Cloudflare sends the
 * visitor's own address in CF-Connecting-IP.
 *
 * With CLIENT_IP_HEADER set (CF-Connecting-IP), that header becomes the
 * client's address, but only when the request comes straight from a trusted
 * proxy (TRUSTED_PROXIES): a request sent to the app any other way can't name
 * its own address with it. Runs first, so everything after it, sessions,
 * rate limits and the reports log, sees the same address.
 */
class ClientIpFromHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) config('noteshare.client_ip_header', '');
        $peer = (string) $request->server('REMOTE_ADDR', '');
        $proxies = self::trustedProxies();

        if ($header !== '' && $proxies !== [] && IpUtils::checkIp($peer, $proxies)) {
            $ip = trim((string) $request->headers->get($header, ''));
            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                // The visitor becomes the peer, which no proxy list trusts, so
                // $request->ip() returns it and X-Forwarded-For is never read.
                $request->server->set('REMOTE_ADDR', $ip);
            }
        }

        return $next($request);
    }

    /**
     * TRUSTED_PROXIES as a list, with '*' dropped, never honoured: trusting
     * every hop lets any client pick its own IP and step around every per-IP
     * rate limit.
     *
     * @return list<string>
     */
    public static function trustedProxies(): array
    {
        return array_values(array_filter(
            config('noteshare.trusted_proxies', []),
            fn ($p) => $p !== '*' && $p !== '**',
        ));
    }
}
