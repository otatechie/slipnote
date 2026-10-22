<?php

namespace App\Support;

use Illuminate\Http\Request;

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
