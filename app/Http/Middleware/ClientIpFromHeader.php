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
                $request->server->set('REMOTE_ADDR', $ip);
            }
        }

        return $next($request);
    }

    public static function trustedProxies(): array
    {
        return array_values(array_filter(
            config('noteshare.trusted_proxies', []),
            fn ($p) => $p !== '*' && $p !== '**',
        ));
    }
}
