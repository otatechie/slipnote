<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

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
                // Remembered here: once REMOTE_ADDR is the visitor's, the
                // request no longer looks like it came through the proxy.
                $request->attributes->set(self::VIA_PROXY, true);
                $request->server->set('REMOTE_ADDR', $ip);
            }
        }

        return $next($request);
    }

    private const VIA_PROXY = 'via_trusted_proxy';

    /** Whether the request reached us through a trusted proxy, even after its address was rewritten. */
    public static function viaTrustedProxy(Request $request): bool
    {
        return $request->attributes->get(self::VIA_PROXY, false) || $request->isFromTrustedProxy();
    }

    public static function trustedProxies(): array
    {
        return array_values(array_filter(
            config('noteshare.trusted_proxies', []),
            fn ($p) => $p !== '*' && $p !== '**',
        ));
    }
}
