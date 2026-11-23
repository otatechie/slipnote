<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithWorkspace;
use Tests\TestCase;

/**
 * The owner arranging the board: courses on the board page.
 */
class ReorderTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkspace();
        $this->course = Course::create(['code' => 'BIO 101', 'title' => 'Cell Biology', 'slug' => 'bio-101', 'position' => 0]);
    }

    // --- Courses ---

    public function test_reordering_courses_answers_with_the_board_not_an_empty_reply(): void
    {
        // An empty 204 isn't an Inertia page: the app took it for an error
        // ("That didn't go through") although the order had saved.
        $second = Course::create(['code' => 'CHEM 101', 'title' => 'Chemistry', 'slug' => 'chem-101', 'position' => 1]);
        $this->unlockOwnerSession();

        $this->post(route('courses.reorder', $this->wsParams()), ['ids' => [$second->id, $this->course->id]])
            ->assertRedirect(route('courses.index', $this->wsParams()));

        $this->assertSame(0, $second->fresh()->position);
        $this->assertSame(1, $this->course->fresh()->position);
    }

    public function test_only_the_owner_can_reorder_courses(): void
    {
        $second = Course::create(['code' => 'CHEM 101', 'title' => 'Chemistry', 'slug' => 'chem-101', 'position' => 1]);

        $this->post(route('courses.reorder', $this->wsParams()), ['ids' => [$second->id, $this->course->id]])
            ->assertForbidden();
        $this->assertSame(1, $second->fresh()->position);
    }

    public function test_reordering_courses_cannot_move_another_boards_course(): void
    {
        [$other] = Workspace::provision('Other Board');
        $this->actingInWorkspace($other);
        $theirs = Course::create(['code' => 'LAW 1', 'title' => 'Law', 'slug' => 'law-1', 'position' => 5]);
        $this->actingInWorkspace($this->workspace);
        $this->unlockOwnerSession();

        $this->post(route('courses.reorder', $this->wsParams()), ['ids' => [$theirs->id, $this->course->id]])
            ->assertRedirect();

        $this->assertSame(5, Course::withoutWorkspaceScope()->find($theirs->id)->position);
    }
}
