<?php

namespace App\Support;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class RecentWorkspaces
{
    private const COOKIE = 'recent_workspaces';

    // One teacher runs a board per class. Slugs only: the cookie is encrypted,
    // browsers cap it at 4 KB, and the name comes from the database anyway.
    private const MAX_ITEMS = 20;

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

        // Older cookies carry a name beside the slug; it's ignored.
        return array_values(array_filter($decoded, fn ($item) => is_array($item)
            && isset($item['slug'])
            && is_string($item['slug'])));
    }

    public static function add(Request $request, Workspace $workspace): Cookie
    {
        $list = self::read($request);

        // Dedupe by slug so the workspace floats to the top.
        $list = array_values(array_filter($list, fn ($item) => $item['slug'] !== $workspace->slug));

        array_unshift($list, ['slug' => $workspace->slug]);

        $list = array_slice($list, 0, self::MAX_ITEMS);

        return self::makeCookie(json_encode(array_map(fn ($item) => ['slug' => $item['slug']], $list)));
    }

    public static function touch(Request $request, Workspace $workspace): ?Cookie
    {
        $first = self::read($request)[0]['slug'] ?? null;

        return $first === $workspace->slug ? null : self::add($request, $workspace);
    }

    public static function remove(Request $request, string $slug): Cookie
    {
        $list = array_values(array_filter(self::read($request), fn ($item) => $item['slug'] !== $slug));

        return self::makeCookie(json_encode(array_map(fn ($item) => ['slug' => $item['slug']], $list)));
    }

    private static function makeCookie(string $value): Cookie
    {
        return cookie(
            name: self::COOKIE,
            value: $value,
            minutes: self::TTL_MINUTES,
            path: '/',
            secure: (bool) config('session.secure'),
            httpOnly: true,
            sameSite: 'lax',
        );
    }
}
