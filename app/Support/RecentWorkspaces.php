<?php

namespace App\Support;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Cookie-side list of workspaces this browser has unlocked in owner mode.
 * UX nicety only — real auth goes through ws_owner_{id} session keys.
 */
class RecentWorkspaces
{
    private const COOKIE = 'recent_workspaces';

    private const MAX_ITEMS = 5;

    private const TTL_MINUTES = 60 * 24 * 365; // 1 year

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

        return array_values(array_filter($decoded, fn ($item) => is_array($item)
            && isset($item['slug'], $item['name'])
            && is_string($item['slug'])
            && is_string($item['name'])));
    }

    public static function add(Request $request, Workspace $workspace): Cookie
    {
        $list = self::read($request);

        // Dedupe by slug so the workspace floats to the top.
        $list = array_values(array_filter($list, fn ($item) => $item['slug'] !== $workspace->slug));

        array_unshift($list, ['slug' => $workspace->slug, 'name' => $workspace->name]);

        $list = array_slice($list, 0, self::MAX_ITEMS);

        return self::makeCookie(json_encode($list));
    }

    public static function remove(Request $request, string $slug): Cookie
    {
        $list = array_values(array_filter(self::read($request), fn ($item) => $item['slug'] !== $slug));

        return self::makeCookie(json_encode($list));
    }

    private static function makeCookie(string $value): Cookie
    {
        return cookie(
            name: self::COOKIE,
            value: $value,
            minutes: self::TTL_MINUTES,
            path: '/',
            // Follow the app's session-cookie security: HTTPS-only in prod,
            // relaxed for local http dev. Avoids hardcoding secure=true, which
            // would silently drop the cookie over http in development.
            secure: (bool) config('session.secure'),
            httpOnly: true,
            sameSite: 'lax',
        );
    }
}
