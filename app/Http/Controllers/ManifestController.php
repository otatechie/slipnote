<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

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
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#f3f5fa',
            'theme_color' => '#f3f5fa',
            'icons' => self::ICONS,
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
