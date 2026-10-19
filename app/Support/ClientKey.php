<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Who a request comes from, for rate-limit keys. IPv4 as is; IPv6 cut to its
 * /64, which is what one home or phone connection is given: keyed on the full
 * address, one client could rotate through billions of keys.
 */
class ClientKey
{
    public static function of(Request $request): string
    {
        $ip = (string) $request->ip();
        $packed = @inet_pton($ip);

        if ($packed !== false && strlen($packed) === 16) {
            return bin2hex(substr($packed, 0, 8)).'::/64';
        }

        return $ip;
    }
}
