<?php

namespace Tests\Feature;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->get(route('courses.index', $this->wsParams()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CoursesPage')
                ->has('courses', 2)
            );
    }

    public function test_create_form_is_hidden_for_non_owners(): void
    {
        // Non-owner sees isOwner=false in props
        $this->get(route('courses.index', $this->wsParams()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CoursesPage')
                ->where('isOwner', false)
            );
    }

    public function test_inertia_requests_work_with_route_middleware(): void
    {
        // Regression: tenant is resolved from the route binding before the controller runs.
        Course::create(['code' => 'MATH 251', 'title' => 'Calc', 'slug' => 'math-251']);

        $this->get(route('courses.index', array_merge($this->wsParams(), ['search' => 'math'])))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('courses', 1));
    }

    public function test_share_button_is_available_to_everyone_and_does_not_leak_owner_secret(): void
    {
        $page = $this->get(route('courses.index', $this->wsParams()))
            ->assertOk();

        // The owner secret must not appear in the JSON response
        $content = $page->getContent();
        $this->assertStringNotContainsString($this->ownerSecret, $content);
    }

    public function test_empty_board_tells_a_non_owner_how_courses_get_added(): void
    {
        $this->get(route('courses.index', $this->wsParams()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CoursesPage')
                ->where('totalCourses', 0)
                ->where('isOwner', false)
            );
    }

    public function test_returning_owner_can_unlock_with_the_raw_secret(): void
    {
        $this->post(
            route('courses.unlock', $this->wsParams()),
            ['ownerInput' => $this->ownerSecret]
        )->assertRedirect(route('courses.index', $this->wsParams()));

        $this->assertTrue(session($this->workspace->ownerSessionKey()));
    }

    public function test_returning_owner_can_unlock_by_pasting_the_full_owner_link(): void
    {
        $ownerUrl = route('courses.index', $this->wsParams()).'?owner='.$this->ownerSecret;

        $this->post(
            route('courses.unlock', $this->wsParams()),
            ['ownerInput' => $ownerUrl]
        )->assertRedirect(route('courses.index', $this->wsParams()));

        $this->assertTrue(session($this->workspace->ownerSessionKey()));
    }

    public function test_owner_query_link_unlocks_then_redirects_to_the_clean_workspace_url(): void
    {
        $this->get(route('courses.index', $this->wsParams()).'?owner='.$this->ownerSecret)
            ->assertRedirect(route('courses.index', $this->wsParams()));

        $this->assertTrue(session($this->workspace->ownerSessionKey()));
    }

    public function test_a_wrong_owner_secret_is_rejected_and_does_not_unlock(): void
    {
        $this->post(
            route('courses.unlock', $this->wsParams()),
            ['ownerInput' => 'definitely-not-the-secret']
        )->assertSessionHasErrors(['ownerInput']);

        $this->assertNotSame(true, session($this->workspace->ownerSessionKey()));
    }

    public function test_owner_unlock_affordance_is_hidden_once_in_owner_mode(): void
    {
        $this->unlockOwnerSession();

        $this->get(route('courses.index', $this->wsParams()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('isOwner', true));
    }

    public function test_empty_board_prompts_the_owner_to_add_the_first_course(): void
    {
        $this->unlockOwnerSession();

        $this->get(route('courses.index', $this->wsParams()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('isOwner', true)
                ->where('totalCourses', 0)
            );
    }

    public function test_owner_sees_the_create_form_and_can_create_a_course(): void
    {
        $this->unlockOwnerSession();

        $this->post(route('courses.store', $this->wsParams()), [
            'code' => 'PHYS 101',
            'title' => 'Introductory Physics',
        ])->assertRedirect();

        $course = Course::firstWhere('code', 'PHYS 101');
        $this->assertNotNull($course);
        $this->assertSame('phys-101', $course->slug);
        $this->assertSame('Introductory Physics', $course->title);
        $this->assertSame($this->workspace->id, $course->workspace_id);
    }

    public function test_a_non_owner_cannot_create_even_by_calling_the_action(): void
    {
        $this->post(route('courses.store', $this->wsParams()), [
            'code' => 'HACK 999',
            'title' => 'Sneaky',
        ])->assertForbidden();

        $this->assertSame(0, Course::count());
    }

    public function test_colliding_codes_get_a_unique_slug(): void
    {
        Course::create(['code' => 'PHYS 101', 'title' => 'First', 'slug' => 'phys-101']);

        $this->unlockOwnerSession();

        $this->post(route('courses.store', $this->wsParams()), [
            'code' => 'PHYS 101',
            'title' => 'Second section',
        ]);

        $this->assertSame('phys-101-2', Course::where('title', 'Second section')->value('slug'));
    }

    public function test_a_created_course_is_reachable_at_its_page(): void
    {
        $this->unlockOwnerSession();

        $this->post(route('courses.store', $this->wsParams()), [
            'code' => 'CHEM 110',
            'title' => 'General Chemistry',
        ]);

        $this->get(route('course.show', $this->wsParams(['slug' => 'chem-110'])))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CoursePage')
                ->where('course.code', 'CHEM 110')
            );
    }

    public function test_search_filters_courses_by_code_and_title(): void
    {
        Course::create(['code' => 'MATH 251', 'title' => 'Calculus II', 'slug' => 'math-251']);
        Course::create(['code' => 'PHYS 101', 'title' => 'Intro Physics', 'slug' => 'phys-101']);
        Course::create(['code' => 'CHEM 110', 'title' => 'General Chemistry', 'slug' => 'chem-110']);

        $this->get(route('courses.index', array_merge($this->wsParams(), ['search' => 'phys'])))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('courses', 1)
                ->where('courses.0.code', 'PHYS 101')
            );

        // matches on title too
        $this->get(route('courses.index', array_merge($this->wsParams(), ['search' => 'chemistry'])))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('courses', 1)
                ->where('courses.0.code', 'CHEM 110')
            );
    }

    public function test_a_course_with_no_files_shows_zero_materials_count(): void
    {
        Course::create(['code' => 'NEW 100', 'title' => 'Brand New', 'slug' => 'new-100']);

        $this->get(route('courses.index', $this->wsParams()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('courses.0.materials_count', 0)
            );
    }

    public function test_no_search_box_shown_via_prop_threshold(): void
    {
        // The prop totalCourses drives the Vue v-if for search; assert the prop value.
        Course::create(['code' => 'MATH 251', 'title' => 'Calculus II', 'slug' => 'math-251']);

        $this->get(route('courses.index', $this->wsParams()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('totalCourses', 1));
    }

    public function test_active_sort_orders_courses_by_most_recent_upload(): void
    {
        $old = Course::create(['code' => 'OLD 100', 'title' => 'Old', 'slug' => 'old-100']);
        $fresh = Course::create(['code' => 'ZZZ 999', 'title' => 'Fresh', 'slug' => 'zzz-999']);

        $m1 = $old->materials()->create(['section' => 'notes', 'original_filename' => 'a.pdf', 'stored_path' => 'x/a.pdf']);
        $m1->forceFill(['created_at' => now()->subMonth()])->saveQuietly();
        $m2 = $fresh->materials()->create(['section' => 'notes', 'original_filename' => 'b.pdf', 'stored_path' => 'x/b.pdf']);
        $m2->forceFill(['created_at' => now()])->saveQuietly();

        // Default sort "active": ZZZ 999 (just uploaded) should be first
        $this->get(route('courses.index', $this->wsParams()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('courses.0.code', 'ZZZ 999')
                ->where('courses.1.code', 'OLD 100')
            );

        // A–Z flips it back to alphabetical
        $this->get(route('courses.index', array_merge($this->wsParams(), ['sort' => 'az'])))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('courses.0.code', 'OLD 100')
                ->where('courses.1.code', 'ZZZ 999')
            );
    }

    public function test_a_course_with_files_has_a_materials_max_created_at(): void
    {
        $course = Course::create(['code' => 'MATH 251', 'title' => 'Calculus II', 'slug' => 'math-251']);
        $course->materials()->create(['section' => 'notes', 'original_filename' => 'a.pdf', 'stored_path' => 'x/a.pdf']);

        $this->get(route('courses.index', $this->wsParams()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->whereNot('courses.0.materials_max_created_at', null)
            );
    }
}
