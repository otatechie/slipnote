<?php

namespace Tests\Feature;

use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The printable classroom poster: a board's name and a QR code that opens it. */
class BoardPosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_poster_shows_the_board_and_its_link(): void
    {
        [$ws] = Workspace::provision('Grade 5 English B');

        $this->get('/'.$ws->slug.'/poster')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('BoardPoster')
                ->where('workspace.name', 'Grade 5 English B')
                ->where('url', config('app.url').'/'.$ws->slug)
                ->where('uploadsOpen', true));
    }

    public function test_the_link_on_the_poster_never_comes_from_the_host_header(): void
    {
        [$ws] = Workspace::provision('Grade 5 English B');

        // Printed and pinned on a wall: a forged Host must not end up on it.
        $this->withHeaders(['Host' => 'evil.example'])
            ->get('/'.$ws->slug.'/poster')
            ->assertInertia(fn ($page) => $page->where('url', config('app.url').'/'.$ws->slug));
    }

    public function test_a_board_with_uploads_locked_doesnt_invite_uploads(): void
    {
        [$ws] = Workspace::provision('Teacher board');
        $ws->forceFill(['uploads_owner_only' => true])->save();

        $this->get('/'.$ws->slug.'/poster')
            ->assertInertia(fn ($page) => $page->where('uploadsOpen', false));
    }

    public function test_each_board_prints_only_its_own_poster(): void
    {
        [$a] = Workspace::provision('Board A');
        [$b] = Workspace::provision('Board B');

        $this->get('/'.$a->slug.'/poster')
            ->assertInertia(fn ($page) => $page
                ->where('workspace.slug', $a->slug)
                ->where('workspace.name', 'Board A'))
            ->assertDontSee($b->slug);
    }

    public function test_no_poster_for_a_board_that_is_gone_or_archived(): void
    {
        $this->get('/no-such-board-abc123/poster')->assertNotFound();

        [$ws] = Workspace::provision('Closed board');
        $ws->forceFill(['archived_at' => now()])->save();
        $this->get('/'.$ws->slug.'/poster')->assertStatus(410);
    }
}
