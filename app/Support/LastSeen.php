<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class LastSeen
{
    private const COOKIE = 'slipnote_seen';

    private const MAX_ITEMS = 50;

    private const TTL_MINUTES = 60 * 24 * 365;

    public const FIRST_LOOK_DAYS = 7;

    /** @return array<int, int> course id => unix timestamp */
    public static function read(Request $request): array
    {
        $raw = $request->cookie(self::COOKIE);
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $id => $ts) {
            if (is_numeric($id) && is_int($ts) && $ts > 0) {
                $out[(int) $id] = $ts;
            }
        }

        return $out;
    }

    /** The baseline for one course, or null when this browser has none. */
    public static function for(Request $request, int $courseId): ?int
    {
        return self::read($request)[$courseId] ?? null;
    }

    /** Files after this count as new: the last visit, else the last week. */
    public static function since(Request $request, int $courseId): int
    {
        return self::for($request, $courseId) ?? self::firstLook();
    }

    public static function firstLook(): int
    {
        return now()->subDays(self::FIRST_LOOK_DAYS)->getTimestamp();
    }

    /** Record that this course was just viewed. */
    public static function mark(Request $request, int $courseId): Cookie
    {
        $seen = self::read($request);
        $seen[$courseId] = time();

        // Keep the most recently viewed courses; drop the oldest baselines.
        arsort($seen);
        $seen = array_slice($seen, 0, self::MAX_ITEMS, true);

        return cookie(
            name: self::COOKIE,
            value: json_encode($seen),
            minutes: self::TTL_MINUTES,
            path: '/',
            secure: (bool) config('session.secure'),
            httpOnly: true,
            sameSite: 'lax',
        );
    }
}
