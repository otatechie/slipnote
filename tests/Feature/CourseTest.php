<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\InteractsWithWorkspace;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkspace();
    }

    public function test_the_list_page_renders_existing_courses(): void
    {
        Course::create(['code' => 'MATH 251', 'title' => 'Calculus II', 'slug' => 'math-251']);
        Course::create(['code' => 'PHYS 101', 'title' => 'Intro Physics', 'slug' => 'phys-101']);

        Livewire::test('courses-page')
            ->assertSee('MATH 251')
            ->assertSee('PHYS 101');
    }

    public function test_create_form_is_hidden_for_non_owners(): void
    {
        Livewire::test('courses-page')
            ->assertDontSee('New course');
    }

    public function test_livewire_followup_requests_work_without_route_middleware(): void
    {
        // Regression: the POST /livewire/update endpoint bypasses the
        // {workspace} route + ResolveWorkspace middleware. Simulate that by
        // forgetting the resolved tenant AFTER mount, then driving an action.
        // Without hydrate() re-establishing it, a scoped re-render throws
        // "No current workspace" (the reported 500).
        Course::create(['code' => 'MATH 251', 'title' => 'Calc', 'slug' => 'math-251']);

        $component = Livewire::test('courses-page');

        // Drop the singleton so only hydrate() can restore tenant context.
        app()->forgetInstance(Tenancy::class);

        $component->set('search', 'math')   // triggers a scoped re-render
            ->assertSee('MATH 251')
            ->set('sort', 'az')
            ->assertSee('MATH 251');
    }

    public function test_share_button_is_available_to_everyone_and_copies_the_plain_link(): void
    {
        $html = Livewire::test('courses-page') // non-owner
            ->assertSee('Share with classmates')
            ->html();

        // The share action embeds the bare workspace URL...
        $this->assertStringContainsString($this->workspace->slug, $html);
        // ...and the real leak risk — the owner secret value itself — must
        // never appear anywhere in what this control renders. (Substring
        // grep for "?owner=" is avoided: it legitimately appears in source
        // comments; the secret value is the thing that actually matters.)
        $this->assertStringNotContainsString($this->ownerSecret, $html);
    }

    public function test_empty_board_tells_a_non_owner_how_courses_get_added(): void
    {
        // The disorienting case: visit the bare workspace URL, no courses,
        // not in owner mode — must explain the owner link, not dead-end.
        Livewire::test('courses-page')
            ->assertSee('No courses yet')
            ->assertSee('owner link')
            ->assertDontSee('New course');
    }

    public function test_returning_owner_can_unlock_with_the_raw_secret(): void
    {
        Livewire::test('courses-page')
            ->set('ownerInput', $this->ownerSecret)
            ->call('unlockOwner')
            ->assertHasNoErrors()
            ->assertSee('New course'); // owner UI now visible

        $this->assertTrue(session($this->workspace->ownerSessionKey()));
    }

    public function test_returning_owner_can_unlock_by_pasting_the_full_owner_link(): void
    {
        $ownerUrl = route('courses.index', ['workspace' => $this->workspace->slug])
            .'?owner='.$this->ownerSecret;

        Livewire::test('courses-page')
            ->set('ownerInput', $ownerUrl)
            ->call('unlockOwner')
            ->assertHasNoErrors();

        $this->assertTrue(session($this->workspace->ownerSessionKey()));
    }

    public function test_owner_query_link_unlocks_then_redirects_to_the_clean_workspace_url(): void
    {
        Livewire::withQueryParams(['owner' => $this->ownerSecret])
            ->test('courses-page')
            ->assertRedirect(route('courses.index', $this->wsParams()));

        $this->assertTrue(session($this->workspace->ownerSessionKey()));
    }

    public function test_a_wrong_owner_secret_is_rejected_and_does_not_unlock(): void
    {
        Livewire::test('courses-page')
            ->set('ownerInput', 'definitely-not-the-secret')
            ->call('unlockOwner')
            ->assertHasErrors(['ownerInput'])
            ->assertDontSee('New course');

        $this->assertNotSame(true, session($this->workspace->ownerSessionKey()));
    }

    public function test_owner_unlock_affordance_is_hidden_once_in_owner_mode(): void
    {
        $this->unlockOwnerSession();

        Livewire::test('courses-page')
            ->assertDontSee('I’m the owner of this board');
    }

    public function test_empty_board_prompts_the_owner_to_add_the_first_course(): void
    {
        $this->unlockOwnerSession();

        Livewire::test('courses-page')
            ->assertSee('No courses yet')
            ->assertSee('Add the first one')
            ->assertSee('New course');
    }

    public function test_owner_sees_the_create_form_and_can_create_a_course(): void
    {
        $this->unlockOwnerSession();

        Livewire::test('courses-page')
            ->assertSee('New course')
            ->set('code', 'PHYS 101')
            ->set('title', 'Introductory Physics')
            ->call('createCourse');

        $course = Course::firstWhere('code', 'PHYS 101');
        $this->assertNotNull($course);
        $this->assertSame('phys-101', $course->slug);
        $this->assertSame('Introductory Physics', $course->title);
        $this->assertSame($this->workspace->id, $course->workspace_id);
    }

    public function test_a_non_owner_cannot_create_even_by_calling_the_action(): void
    {
        Livewire::test('courses-page')
            ->set('code', 'HACK 999')
            ->set('title', 'Sneaky')
            ->call('createCourse')
            ->assertForbidden();

        $this->assertSame(0, Course::count());
    }

    public function test_colliding_codes_get_a_unique_slug(): void
    {
        Course::create(['code' => 'PHYS 101', 'title' => 'First', 'slug' => 'phys-101']);

        $this->unlockOwnerSession();

        Livewire::test('courses-page')
            ->set('code', 'PHYS 101')
            ->set('title', 'Second section')
            ->call('createCourse');

        $this->assertSame('phys-101-2', Course::where('title', 'Second section')->value('slug'));
    }

    public function test_a_created_course_is_reachable_at_its_page(): void
    {
        $this->unlockOwnerSession();

        Livewire::test('courses-page')
            ->set('code', 'CHEM 110')
            ->set('title', 'General Chemistry')
            ->call('createCourse');

        $this->get(route('course.show', $this->wsParams(['slug' => 'chem-110'])))
            ->assertOk()
            ->assertSee('CHEM 110');
    }

    public function test_search_filters_courses_by_code_and_title(): void
    {
        Course::create(['code' => 'MATH 251', 'title' => 'Calculus II', 'slug' => 'math-251']);
        Course::create(['code' => 'PHYS 101', 'title' => 'Intro Physics', 'slug' => 'phys-101']);
        Course::create(['code' => 'CHEM 110', 'title' => 'General Chemistry', 'slug' => 'chem-110']);

        Livewire::test('courses-page')
            ->set('search', 'phys')
            ->assertSee('PHYS 101')
            ->assertDontSee('MATH 251')
            ->assertDontSee('CHEM 110');

        // matches on title too
        Livewire::test('courses-page')
            ->set('search', 'chemistry')
            ->assertSee('CHEM 110')
            ->assertDontSee('MATH 251');
    }

    public function test_a_course_with_no_files_shows_the_be_the_first_hint(): void
    {
        Course::create(['code' => 'NEW 100', 'title' => 'Brand New', 'slug' => 'new-100']);

        Livewire::test('courses-page')
            ->assertSee('No files yet — be the first');
    }

    public function test_no_search_box_until_there_are_enough_courses(): void
    {
        Course::create(['code' => 'MATH 251', 'title' => 'Calculus II', 'slug' => 'math-251']);

        Livewire::test('courses-page')
            ->assertDontSee('Search')
            ->assertDontSeeHtml('type="search"');
    }

    public function test_active_sort_orders_courses_by_most_recent_upload(): void
    {
        $old = Course::create(['code' => 'OLD 100', 'title' => 'Old', 'slug' => 'old-100']);
        $fresh = Course::create(['code' => 'ZZZ 999', 'title' => 'Fresh', 'slug' => 'zzz-999']);

        // Force created_at explicitly (Eloquent would otherwise stamp "now").
        $m1 = $old->materials()->create(['section' => 'notes', 'original_filename' => 'a.pdf', 'stored_path' => 'x/a.pdf']);
        $m1->forceFill(['created_at' => now()->subMonth()])->saveQuietly();
        $m2 = $fresh->materials()->create(['section' => 'notes', 'original_filename' => 'b.pdf', 'stored_path' => 'x/b.pdf']);
        $m2->forceFill(['created_at' => now()])->saveQuietly();

        // Default sort is "active": ZZZ 999 (just uploaded) must precede OLD 100.
        $html = Livewire::test('courses-page')->html();
        $this->assertLessThan(
            strpos($html, 'OLD 100'),
            strpos($html, 'ZZZ 999'),
            'Course with the most recent upload should sort first under "active"',
        );

        // A–Z flips it back to alphabetical.
        $htmlAz = Livewire::test('courses-page')->set('sort', 'az')->html();
        $this->assertLessThan(strpos($htmlAz, 'ZZZ 999'), strpos($htmlAz, 'OLD 100'));
    }

    public function test_a_course_with_files_shows_an_updated_timestamp(): void
    {
        $course = Course::create(['code' => 'MATH 251', 'title' => 'Calculus II', 'slug' => 'math-251']);
        $course->materials()->create(['section' => 'notes', 'original_filename' => 'a.pdf', 'stored_path' => 'x/a.pdf']);

        Livewire::test('courses-page')->assertSee('Updated');
    }
}
