<?php

namespace Tests\Feature;

use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceLandingTest extends TestCase
{
    use RefreshDatabase;

    /** A board's link: its name, then a 6-character random code. */
    private const LINK = '/^cs-masters-2026-[a-hj-km-np-z2-9]{6}$/';

    public function test_creating_a_workspace_shows_the_one_time_owner_link_with_save_safeguards(): void
    {
        $response = $this->post('/workspaces', ['name' => 'CS Masters 2026']);

        $ws = Workspace::firstWhere('name', 'CS Masters 2026');
        $this->assertNotNull($ws);
        $this->assertMatchesRegularExpression(self::LINK, $ws->slug);

        // Flash data carries the receipt
        $response->assertSessionHas('createdName', 'CS Masters 2026');
        $response->assertSessionHas('createdUrl', fn ($v) => str_ends_with($v, '/'.$ws->slug));
        $response->assertSessionHas('ownerUrl');
    }

    public function test_proceed_is_a_no_op_when_nothing_was_created(): void
    {
        // The home page renders without flash data
        $this->get('/')->assertOk();
    }

    public function test_a_board_link_cannot_be_worked_out_from_its_name(): void
    {
        // The name alone used to be the link, so anyone could reach a board
        // by guessing "econ-level-200". Now two boards with one name (any
        // spelling) are both allowed, and neither sits at the bare name.
        foreach (['CS Masters 2026', 'cs masters 2026', '  CS   Masters   2026  '] as $name) {
            $this->post('/workspaces', ['name' => $name])->assertSessionHasNoErrors();
        }

        $slugs = Workspace::pluck('slug');
        $this->assertCount(3, $slugs->unique());
        $slugs->each(fn ($s) => $this->assertMatchesRegularExpression(self::LINK, $s));
        $this->get('/cs-masters-2026')->assertNotFound();
    }

    public function test_a_name_that_slugifies_to_empty_is_rejected(): void
    {
        $this->post('/workspaces', ['name' => '!!!'])
            ->assertSessionHasErrors(['name']);

        $this->assertSame(0, Workspace::count());
    }

    public function test_open_takes_the_boards_link_in_any_form(): void
    {
        [$ws] = Workspace::provision('CS Masters 2026');
        $target = route('courses.index', ['workspace' => $ws->slug]);

        foreach ([
            'https://slipnote.co/'.$ws->slug,
            'slipnote.co/'.$ws->slug.'/c/some-course',
            $ws->slug,
            '  '.strtoupper($ws->slug).'  ',
        ] as $pasted) {
            $this->post('/workspaces/open', ['openLink' => $pasted])->assertRedirect($target);
        }
    }

    public function test_open_does_not_find_a_board_by_its_name(): void
    {
        Workspace::provision('CS Masters 2026');

        $this->post('/workspaces/open', ['openLink' => 'CS Masters 2026'])
            ->assertSessionHasErrors(['openLink']);
    }

    public function test_open_with_blank_or_unknown_input_says_so(): void
    {
        $this->post('/workspaces/open', ['openLink' => '   '])->assertSessionHasErrors(['openLink']);
        $this->post('/workspaces/open', ['openLink' => ['x']])->assertSessionHasErrors(['openLink']);
        $this->post('/workspaces/open', ['openLink' => 'nope-abcdef'])->assertSessionHasErrors(['openLink']);
    }

    public function test_trying_many_addresses_is_cut_off(): void
    {
        // Each miss is counted per client: walking through addresses to find
        // boards stops after 60 in ten minutes.
        for ($i = 0; $i < 60; $i++) {
            $this->get('/no-such-board-'.$i)->assertNotFound();
        }
        $this->get('/no-such-board-x')->assertStatus(429);
    }

    public function test_start_lists_managed_boards_with_their_counts_and_drops_deleted_ones(): void
    {
        [$ws] = Workspace::provision('Nursing Level 200');
        $course = $ws->courses()->create(['code' => 'NUR 201', 'title' => 'T', 'slug' => 'nur-201', 'workspace_id' => $ws->id]);
        $course->materials()->create(['section' => 'notes', 'original_filename' => 'a.pdf',
            'stored_path' => 'x/a.pdf', 'download_token' => 'dl-a', 'file_size' => 1]);

        $cookie = json_encode([
            ['slug' => $ws->slug, 'name' => 'Old name'],
            ['slug' => 'deleted-board', 'name' => 'Gone'],
        ]);

        $this->withCookie('recent_workspaces', $cookie)
            ->get('/start')
            ->assertInertia(fn ($page) => $page
                ->has('recent', 1)
                ->where('recent.0.slug', $ws->slug)
                ->where('recent.0.name', 'Nursing Level 200')
                ->where('recent.0.courses', 1)
                ->where('recent.0.files', 1));
    }

    public function test_opening_a_board_as_owner_moves_it_to_the_top_of_start(): void
    {
        [$a] = Workspace::provision('Board A');
        [$b] = Workspace::provision('Board B');
        $cookie = json_encode([
            ['slug' => $a->slug, 'name' => 'Board A'],
            ['slug' => $b->slug, 'name' => 'Board B'],
        ]);

        // Owner of B opens B: B moves first.
        $this->withCookie('recent_workspaces', $cookie)
            ->withSession([$b->ownerSessionKey() => $b->ownerSessionValue()])
            ->get('/'.$b->slug)
            ->assertOk()
            ->assertCookie('recent_workspaces', json_encode([
                ['slug' => $b->slug, 'name' => 'Board B'],
                ['slug' => $a->slug, 'name' => 'Board A'],
            ]));
    }

    public function test_a_visitor_opening_a_board_does_not_reorder_start(): void
    {
        [$a] = Workspace::provision('Board A');
        [$b] = Workspace::provision('Board B');
        $cookie = json_encode([['slug' => $a->slug, 'name' => 'Board A']]);

        $this->withCookie('recent_workspaces', $cookie)
            ->get('/'.$b->slug)
            ->assertOk()
            ->assertCookieMissing('recent_workspaces');
    }

    public function test_a_board_never_takes_the_address_of_a_site_page(): void
    {
        // These pages are routed before the board catch-all; a board at their
        // address would be unreachable. The code keeps it off them.
        foreach (['Privacy', 'Terms', 'Start', 'Lecturers', 'Operator'] as $name) {
            [$ws] = Workspace::provision($name);
            $this->assertStringStartsWith(strtolower($name).'-', $ws->slug);
            $this->get('/'.$ws->slug)->assertOk();
        }

        $this->get('/lecturers')->assertOk()->assertSee('For lecturers');
    }

    public function test_the_landing_page_tells_teachers_uploads_can_be_locked(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Can I stop others adding files?')
            ->assertSee('lock uploads so only they add files');
    }
}
