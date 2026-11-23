<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Material;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\InteractsWithWorkspace;
use Tests\TestCase;

/**
 * The owner arranging the board: courses on the board page, and files
 * within each section of a course.
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
        Storage::fake('local');
        $this->course = Course::create(['code' => 'BIO 101', 'title' => 'Cell Biology', 'slug' => 'bio-101', 'position' => 0]);
    }

    private function addFile(string $name, int $ageSeconds = 0, string $section = 'notes', ?Course $course = null): Material
    {
        Storage::disk('local')->put('materials/'.$name, 'x');
        $m = ($course ?? $this->course)->materials()->create([
            'section' => $section,
            'original_filename' => $name,
            'stored_path' => 'materials/'.$name,
            'download_token' => 'dl-'.md5($name.uniqid()),
            'file_size' => 1,
        ]);
        $m->forceFill(['created_at' => now()->subSeconds($ageSeconds)])->saveQuietly();

        return $m;
    }

    private function arrange(array $files, string $section = 'notes')
    {
        return $this->post(route('course.reorder', $this->wsParams(['slug' => 'bio-101'])), [
            'section' => $section,
            'ids' => array_map(fn ($m) => $m->id, $files),
        ]);
    }

    /** File names in one section, in the order the course page lists them. */
    private function namesOnPage(array $query = [], string $section = 'notes'): array
    {
        $names = [];
        $this->get(route('course.show', $this->wsParams(['slug' => 'bio-101'] + $query)))
            ->assertInertia(function ($page) use (&$names, $section) {
                $rows = array_filter($page->toArray()['props']['materials'], fn ($m) => $m['section'] === $section);
                $names = array_values(array_column($rows, 'original_filename'));
            });

        return $names;
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

    // --- Files within a section ---

    public function test_a_course_nobody_has_arranged_still_shows_newest_first(): void
    {
        $this->addFile('old.pdf', ageSeconds: 3600);
        $this->addFile('new.pdf');

        $this->get(route('course.show', $this->wsParams(['slug' => 'bio-101'])))
            ->assertInertia(fn ($page) => $page->where('sort', 'newest')->where('arranged', false));
        $this->assertSame(['new.pdf', 'old.pdf'], $this->namesOnPage());
        // Asking for a board order that doesn't exist gets newest first, said as such.
        $this->get(route('course.show', $this->wsParams(['slug' => 'bio-101', 'sort' => 'board'])))
            ->assertInertia(fn ($page) => $page->where('sort', 'newest'));
    }

    public function test_the_owner_arranges_a_section_and_the_class_sees_that_order(): void
    {
        $wk1 = $this->addFile('week1.pdf', ageSeconds: 300);
        $wk3 = $this->addFile('week3.pdf', ageSeconds: 200);
        $wk2 = $this->addFile('week2.pdf', ageSeconds: 100);
        $this->unlockOwnerSession();

        $this->arrange([$wk1, $wk2, $wk3])
            ->assertRedirect(route('course.show', $this->wsParams(['slug' => 'bio-101'])));

        session()->forget($this->workspace->ownerSessionKey());
        $this->get(route('course.show', $this->wsParams(['slug' => 'bio-101'])))
            ->assertInertia(fn ($page) => $page->where('sort', 'board')->where('arranged', true));
        $this->assertSame(['week1.pdf', 'week2.pdf', 'week3.pdf'], $this->namesOnPage());
        // The other orders are still there for anyone who wants them.
        $this->assertSame(['week2.pdf', 'week3.pdf', 'week1.pdf'], $this->namesOnPage(['sort' => 'newest']));
    }

    public function test_a_file_added_after_arranging_leads_the_section(): void
    {
        $a = $this->addFile('a.pdf', ageSeconds: 200);
        $b = $this->addFile('b.pdf', ageSeconds: 100);
        $this->unlockOwnerSession();
        $this->arrange([$a, $b]);

        $this->post(route('course.upload', $this->wsParams(['slug' => 'bio-101'])), [
            'section' => 'notes',
            'files' => [UploadedFile::fake()->createWithContent('latest.pdf', 'new content')],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['latest.pdf', 'a.pdf', 'b.pdf'], $this->namesOnPage());
    }

    public function test_pinned_files_stay_on_top_of_the_board_order(): void
    {
        $a = $this->addFile('a.pdf', ageSeconds: 200);
        $b = $this->addFile('b.pdf', ageSeconds: 100);
        $pinned = $this->addFile('pinned.pdf', ageSeconds: 300);
        $pinned->forceFill(['pinned_at' => now()])->save();
        $this->unlockOwnerSession();

        $this->arrange([$pinned, $b, $a]);

        $this->assertSame(['pinned.pdf', 'b.pdf', 'a.pdf'], $this->namesOnPage());
    }

    public function test_arranging_one_section_leaves_the_others_alone(): void
    {
        $n1 = $this->addFile('n1.pdf', ageSeconds: 200);
        $n2 = $this->addFile('n2.pdf', ageSeconds: 100);
        $slides = $this->addFile('slides.pdf', section: 'slides');
        $this->unlockOwnerSession();

        // A slide's id in the notes list is skipped, not given a place in notes.
        $this->arrange([$n1, $slides, $n2])->assertRedirect();

        $this->assertSame(0, $n1->fresh()->position);
        $this->assertSame(1, $n2->fresh()->position);
        $this->assertNull($slides->fresh()->position);
    }

    public function test_a_file_moved_to_another_section_leads_it(): void
    {
        $a = $this->addFile('a.pdf', ageSeconds: 300);
        $b = $this->addFile('b.pdf', ageSeconds: 200);
        $s1 = $this->addFile('s1.pdf', ageSeconds: 500, section: 'slides');
        $s2 = $this->addFile('s2.pdf', ageSeconds: 400, section: 'slides');
        $this->unlockOwnerSession();
        $this->arrange([$a, $b]);
        $this->arrange([$s1, $s2], 'slides');

        $this->patch(route('material.update', $this->wsParams(['slug' => 'bio-101', 'material' => $b->id])), [
            'name' => '', 'section' => 'slides',
        ])->assertSessionHasNoErrors();

        $this->assertNull($b->fresh()->position);
        $this->assertSame(['b.pdf', 's1.pdf', 's2.pdf'], $this->namesOnPage(section: 'slides'));
        $this->assertSame(['a.pdf'], $this->namesOnPage());
    }

    public function test_the_section_must_be_one_of_the_boards_sections(): void
    {
        $a = $this->addFile('a.pdf');
        $this->unlockOwnerSession();

        $this->arrange([$a], 'homework')->assertSessionHasErrors('section');
        $this->assertNull($a->fresh()->position);
    }

    public function test_only_the_owner_can_arrange_files(): void
    {
        $a = $this->addFile('a.pdf');
        $b = $this->addFile('b.pdf');

        $this->arrange([$b, $a])->assertForbidden();
        $this->assertNull($a->fresh()->position);
    }

    public function test_arranging_files_cannot_move_another_boards_files(): void
    {
        [$other, $otherSecret] = Workspace::provision('Other Board');
        $this->actingInWorkspace($other);
        $theirCourse = Course::create(['code' => 'BIO 101', 'title' => 'Theirs', 'slug' => 'bio-101']);
        $theirs = $this->addFile('theirs.pdf', course: $theirCourse);
        $this->actingInWorkspace($this->workspace);
        $mine = $this->addFile('mine.pdf');
        $this->unlockOwnerSession();

        // Same course slug on both boards: my board's URL still only reaches my files.
        $this->arrange([$theirs, $mine])->assertRedirect();

        $this->assertNull($theirs->fresh()->position);
        $this->assertSame(0, $mine->fresh()->position);
    }
}
