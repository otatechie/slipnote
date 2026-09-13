<?php

namespace App\Support;

use App\Models\Ambassador;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The ?ref= handshake. A valid ref on any page is kept in a cookie until
 * this browser creates a board, at which point it is written onto that
 * board and the cookie is cleared. First touch wins: an existing cookie is
 * never overwritten, so ambassadors cannot poach each other's recruits by
 * re-sending links.
 */
class Referral
{
    private const COOKIE = 'slipnote_ref';

    /** Long enough to cover "saw the link, made the board next week". */
    private const TTL_MINUTES = 60 * 24 * 30;

    public static function read(Request $request): ?string
    {
        $raw = $request->cookie(self::COOKIE);

        return is_string($raw) && preg_match(Ambassador::SLUG_PATTERN, $raw) ? $raw : null;
    }

    /** A cookie to set when a valid, new ref arrives; null when nothing to do. */
    public static function capture(Request $request): ?Cookie
    {
        $ref = $request->query('ref');
        if (! is_string($ref)) {
            return null;
        }

        $ref = strtolower(trim($ref));
        if (! preg_match(Ambassador::SLUG_PATTERN, $ref) || self::read($request) !== null) {
            return null;
        }

        return cookie(
            name: self::COOKIE,
            value: $ref,
            minutes: self::TTL_MINUTES,
            path: '/',
            secure: (bool) config('session.secure'),
            httpOnly: true,
            sameSite: 'lax',
        );
    }

    public static function forget(): Cookie
    {
        return cookie()->forget(self::COOKIE);
    }
}
