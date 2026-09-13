<?php

namespace Tests\Feature;

use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Installable as the board: each board has its own manifest, named after it
 * and opening on it; the site pages get a plain SlipNote one.
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_board_has_its_own_manifest_named_after_it(): void
    {
        [$ws] = Workspace::provision('Physics Level 200 — Semester 1');

        $this->get("/{$ws->slug}/manifest.webmanifest")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJson([
                'id' => "/{$ws->slug}",
                'name' => 'Physics Level 200 — Semester 1',
                'short_name' => 'Physics Lev…',
                'start_url' => "/{$ws->slug}",
                'scope' => '/',
                'display' => 'standalone',
            ])
            ->assertJsonPath('icons.2.purpose', 'maskable');
    }

    public function test_the_board_page_links_its_manifest_and_never_leaks_the_owner_key(): void
    {
        [$ws, $secret] = Workspace::provision('Physics Board');

        // ?owner= unlocks then redirects to the clean URL; follow it.
        $page = $this->followingRedirects()->get("/{$ws->slug}?owner={$secret}")->assertOk();
        $page->assertSee("/{$ws->slug}/manifest.webmanifest", false);
        $page->assertSee('apple-mobile-web-app-title', false);

        // Even when asked with the key, the manifest is built from the slug.
        $this->get("/{$ws->slug}/manifest.webmanifest?owner={$secret}")
            ->assertOk()
            ->assertDontSee('owner=', false)
            ->assertDontSee($secret, false);
    }

    public function test_unknown_boards_have_no_manifest(): void
    {
        $this->get('/no-such-board/manifest.webmanifest')->assertNotFound();
    }

    public function test_site_pages_get_the_site_manifest(): void
    {
        $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertJson(['id' => '/', 'name' => 'SlipNote', 'start_url' => '/']);

        $this->get('/')->assertOk()->assertSee('href="'.route('manifest').'"', false);
    }

    public function test_the_service_worker_and_icons_are_served(): void
    {
        foreach (['sw.js', 'icon-512.png', 'icon-512-maskable.png'] as $file) {
            $this->assertFileExists(public_path($file));
        }
    }
}
