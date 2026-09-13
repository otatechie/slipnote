<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

/**
 * Web app manifests. One per board, not one for the site: a student's "app"
 * is their class's board, so the home-screen icon carries the board's name
 * and opens on the board -- from wherever inside it they installed. The
 * landing and legal pages get the plain site manifest.
 */
class ManifestController extends Controller
{
    private const ICONS = [
        ['src' => '/favicon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => '/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ['src' => '/icon-512-maskable.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ];

    public function site(): JsonResponse
    {
        return $this->manifest([
            'id' => '/',
            'name' => 'SlipNote',
            'short_name' => 'SlipNote',
            'start_url' => '/',
        ]);
    }

    /**
     * Built from the slug only. The owner key travels in ?owner= on the page
     * that links here and must never end up as a start_url.
     */
    public function board(Workspace $workspace): JsonResponse
    {
        return $this->manifest([
            'id' => '/'.$workspace->slug,
            'name' => $workspace->name,
            // Under the icon there is room for about twelve characters.
            'short_name' => mb_strimwidth($workspace->name, 0, 12, '…'),
            'start_url' => '/'.$workspace->slug,
        ]);
    }

    private function manifest(array $fields): JsonResponse
    {
        return response()->json($fields + [
            // Site-wide, so courses, previews and downloads stay inside the
            // installed app instead of bouncing out to the browser.
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#f3f5fa',
            'theme_color' => '#f3f5fa',
            'icons' => self::ICONS,
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
