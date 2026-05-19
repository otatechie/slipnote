<?php

namespace Tests\Feature;

use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkspaceLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_workspace_shows_the_one_time_owner_link_with_save_safeguards(): void
    {
        Livewire::test('workspaces-landing')
            ->set('name', 'CS Masters 2026')
            ->call('create')
            ->assertSet('createdName', 'CS Masters 2026')
            // The receipt is shown and frames the link as unrecoverable.
            ->assertSee('not recoverable')
            // Frictionless save: copy + download (option 2).
            ->assertSee('Copy link')
            ->assertSee('Download .txt')
            // Blocking confirmation gate before continuing (option 1).
            ->assertSee('I’ve saved the owner link', false)
            ->assertSeeHtml('wire:click="proceed"');

        $ws = Workspace::firstWhere('name', 'CS Masters 2026');
        $this->assertNotNull($ws);
        $this->assertSame('cs-masters-2026', $ws->slug);
    }

    public function test_proceed_navigates_into_the_newly_created_workspace(): void
    {
        Livewire::test('workspaces-landing')
            ->set('name', 'CS Masters 2026')
            ->call('create')
            ->call('proceed')
            ->assertRedirect(route('courses.index', ['workspace' => 'cs-masters-2026']));
    }

    public function test_proceed_is_a_no_op_when_nothing_was_created(): void
    {
        // Defensive: calling proceed() without a created workspace must not
        // redirect anywhere (no createdUrl).
        Livewire::test('workspaces-landing')
            ->call('proceed')
            ->assertNoRedirect();
    }

    public function test_a_duplicate_workspace_name_is_rejected(): void
    {
        Workspace::provision('CS Masters 2026');

        Livewire::test('workspaces-landing')
            ->set('name', 'CS Masters 2026')
            ->call('create')
            ->assertHasErrors(['name'])
            ->assertSet('ownerUrl', null);

        $this->assertSame(1, Workspace::where('slug', 'cs-masters-2026')->count());
    }

    public function test_name_collision_is_detected_across_slug_equivalent_spellings(): void
    {
        // These all slugify to "cs-masters-2026" — the real conflict, since
        // "open by name" would otherwise send users to the wrong board.
        Workspace::provision('CS Masters 2026');

        foreach (['cs masters 2026', 'CS-Masters-2026', '  CS   Masters   2026  '] as $variant) {
            Livewire::test('workspaces-landing')
                ->set('name', $variant)
                ->call('create')
                ->assertHasErrors(['name']);
        }

        $this->assertSame(1, Workspace::count());
    }

    public function test_a_name_that_slugifies_to_empty_is_rejected(): void
    {
        Livewire::test('workspaces-landing')
            ->set('name', '!!!')
            ->call('create')
            ->assertHasErrors(['name'])
            ->assertSet('ownerUrl', null);

        $this->assertSame(0, Workspace::count());
    }

    public function test_open_accepts_the_human_name_not_just_the_slug(): void
    {
        // Created as "CS Masters 2026" → slug cs-masters-2026.
        Workspace::provision('CS Masters 2026');

        // A returning user types the NAME they know, with spaces and caps —
        // they must not need to know what a slug is.
        Livewire::test('workspaces-landing')
            ->set('openName', 'CS Masters 2026')
            ->call('open')
            ->assertRedirect(route('courses.index', ['workspace' => 'cs-masters-2026']));
    }

    public function test_the_name_field_previews_the_resulting_link_slug(): void
    {
        Livewire::test('workspaces-landing')
            ->assertDontSee('/cs-masters-2026')
            ->set('name', 'CS Masters 2026')
            ->assertSee('Your link:')
            ->assertSee('/cs-masters-2026');
    }

    public function test_open_with_an_unknown_name_echoes_the_typed_name_in_the_error(): void
    {
        Livewire::test('workspaces-landing')
            ->set('openName', 'Nope Does Not Exist')
            ->call('open')
            ->assertHasErrors(['openName'])
            ->assertNoRedirect()
            // Regression: the error must name what they typed, never blank
            // quotes (was “Couldn't find ""”).
            ->assertSee('Nope Does Not Exist');
    }

    public function test_open_with_blank_input_does_not_match_anything(): void
    {
        Workspace::provision('Real One');

        Livewire::test('workspaces-landing')
            ->set('openName', '   ')
            ->call('open')
            ->assertHasErrors(['openName']);
    }
}
