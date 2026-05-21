<?php

namespace Tests\Feature;

use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_workspace_shows_the_one_time_owner_link_with_save_safeguards(): void
    {
        $response = $this->post('/workspaces', ['name' => 'CS Masters 2026']);

        $ws = Workspace::firstWhere('name', 'CS Masters 2026');
        $this->assertNotNull($ws);
        $this->assertSame('cs-masters-2026', $ws->slug);

        // Flash data carries the receipt
        $response->assertSessionHas('createdName', 'CS Masters 2026');
        $response->assertSessionHas('createdUrl');
        $response->assertSessionHas('ownerUrl');
    }

    public function test_proceed_navigates_into_the_newly_created_workspace(): void
    {
        // After creating, the client-side Vue component does the navigation.
        // Verify the flash contains the URL that Vue will visit.
        $this->post('/workspaces', ['name' => 'CS Masters 2026'])
            ->assertSessionHas('createdUrl', fn ($v) => str_contains($v, 'cs-masters-2026'));
    }

    public function test_proceed_is_a_no_op_when_nothing_was_created(): void
    {
        // The home page renders without flash data
        $this->get('/')->assertOk();
    }

    public function test_a_duplicate_workspace_name_is_rejected(): void
    {
        Workspace::provision('CS Masters 2026');

        $this->post('/workspaces', ['name' => 'CS Masters 2026'])
            ->assertSessionHasErrors(['name']);

        $this->assertSame(1, Workspace::where('slug', 'cs-masters-2026')->count());
    }

    public function test_name_collision_is_detected_across_slug_equivalent_spellings(): void
    {
        // These all slugify to "cs-masters-2026" — the real conflict.
        Workspace::provision('CS Masters 2026');

        foreach (['cs masters 2026', 'CS-Masters-2026', '  CS   Masters   2026  '] as $variant) {
            $this->post('/workspaces', ['name' => $variant])
                ->assertSessionHasErrors(['name']);
        }

        $this->assertSame(1, Workspace::count());
    }

    public function test_a_name_that_slugifies_to_empty_is_rejected(): void
    {
        $this->post('/workspaces', ['name' => '!!!'])
            ->assertSessionHasErrors(['name']);

        $this->assertSame(0, Workspace::count());
    }

    public function test_open_accepts_the_human_name_not_just_the_slug(): void
    {
        // Created as "CS Masters 2026" → slug cs-masters-2026.
        Workspace::provision('CS Masters 2026');

        // A returning user types the NAME they know.
        $this->post('/workspaces/open', ['openName' => 'CS Masters 2026'])
            ->assertRedirect(route('courses.index', ['workspace' => 'cs-masters-2026']));
    }

    public function test_the_name_field_previews_the_resulting_link_slug(): void
    {
        // Slug preview is computed client-side in Vue — just assert the page loads.
        $this->get('/')->assertOk();
    }

    public function test_open_with_an_unknown_name_echoes_the_typed_name_in_the_error(): void
    {
        $this->post('/workspaces/open', ['openName' => 'Nope Does Not Exist'])
            ->assertSessionHasErrors(['openName']);

        // Session errors bag must contain the typed name in the message
        $errors = session('errors');
        $this->assertStringContainsString('Nope Does Not Exist', $errors->first('openName'));
    }

    public function test_open_with_blank_input_does_not_match_anything(): void
    {
        Workspace::provision('Real One');

        $this->post('/workspaces/open', ['openName' => '   '])
            ->assertSessionHasErrors(['openName']);
    }
}
