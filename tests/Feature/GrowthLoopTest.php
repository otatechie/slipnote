<?php

namespace Tests\Feature;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\InteractsWithWorkspace;
use Tests\TestCase;

/**
 * The share-and-return loop: a course link unfurls as the course, the board
 * page knows whether it has anything to share, and a returning browser sees
 * what landed since it was last here.
 */
class GrowthLoopTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkspace();
        Storage::fake('local');
        $this->course = Course::create(['code' => 'CS 101', 'title' => 'Intro to Computer Science', 'slug' => 'cs-101']);
    }

    private function addFile(string $name, int $ageSeconds = 0): void
    {
        $m = $this->course->materials()->create([
            'section' => 'notes',
            'original_filename' => $name,
            'stored_path' => 'materials/'.$name,
            'download_token' => 'dl-'.md5($name),
            'file_size' => 10,
        ]);
        $m->forceFill(['created_at' => now()->subSeconds($ageSeconds)])->saveQuietly();
    }

    // --- Link previews ---

    public function test_a_course_link_unfurls_as_the_course_not_just_the_board(): void
    {
        $this->get(route('course.show', $this->wsParams(['slug' => 'cs-101'])))
            ->assertOk()
            ->assertSee('<meta property="og:title" content="CS 101 · Test Workspace · SlipNote">', false)
            ->assertSee('Intro to Computer Science — notes, slides and past papers', false)
            ->assertSee('<meta property="og:url" content="'.route('course.show', $this->wsParams(['slug' => 'cs-101'])).'">', false);
    }

    public function test_a_board_link_still_unfurls_as_the_board_with_no_counts(): void
    {
        $this->addFile('a.pdf');

        $this->get(route('courses.index', $this->wsParams()))
            ->assertOk()
            ->assertSee('<meta property="og:title" content="Test Workspace · SlipNote">', false)
            ->assertDontSee('1 file', false);
    }

    // --- Activation ---

    public function test_the_board_page_knows_whether_there_is_anything_to_share(): void
    {
        $this->get(route('courses.index', $this->wsParams()))
            ->assertInertia(fn ($page) => $page->where('totalCourses', 1)->where('totalFiles', 0));

        $this->addFile('a.pdf');

        $this->get(route('courses.index', $this->wsParams()))
            ->assertInertia(fn ($page) => $page->where('totalFiles', 1));
    }

    // --- New since last visit ---

    public function test_a_first_visit_marks_nothing_as_new_but_sets_the_baseline(): void
    {
        $this->addFile('a.pdf');

        $this->get(route('course.show', $this->wsParams(['slug' => 'cs-101'])))
            ->assertOk()
            ->assertCookie('slipnote_seen')
            ->assertInertia(fn ($page) => $page->where('materials.0.is_new', false));
    }

    public function test_a_return_visit_marks_only_what_landed_since(): void
    {
        $this->addFile('old.pdf', ageSeconds: 7200);
        $this->addFile('new.pdf', ageSeconds: 60);
        $seenAnHourAgo = json_encode([$this->course->id => now()->subHour()->getTimestamp()]);

        $this->withCookie('slipnote_seen', $seenAnHourAgo)
            ->get(route('course.show', $this->wsParams(['slug' => 'cs-101'])))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('materials.0.original_filename', 'new.pdf')
                ->where('materials.0.is_new', true)
                ->where('materials.1.original_filename', 'old.pdf')
                ->where('materials.1.is_new', false));
    }

    public function test_the_board_page_counts_new_files_per_course(): void
    {
        $other = Course::create(['code' => 'CS 102', 'title' => 'Data Structures', 'slug' => 'cs-102']);
        $this->addFile('a.pdf', ageSeconds: 60);
        $this->addFile('b.pdf', ageSeconds: 60);
        $other->materials()->create([
            'section' => 'notes', 'original_filename' => 'c.pdf', 'stored_path' => 'materials/c.pdf',
            'download_token' => 'dl-c', 'file_size' => 10,
        ]);

        // Baseline for CS 101 only; CS 102 has never been opened here.
        $seen = json_encode([$this->course->id => now()->subHour()->getTimestamp()]);

        $this->withCookie('slipnote_seen', $seen)
            ->get(route('courses.index', $this->wsParams()))
            ->assertInertia(fn ($page) => $page
                ->where('courses.0.code', 'CS 101')->where('courses.0.new_count', 2)
                ->where('courses.1.code', 'CS 102')->where('courses.1.new_count', 0));
    }

    public function test_a_malformed_last_seen_cookie_is_ignored(): void
    {
        $this->addFile('a.pdf');

        $this->withCookie('slipnote_seen', '{"not":"a map", "1": "yesterday"}')
            ->get(route('course.show', $this->wsParams(['slug' => 'cs-101'])))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('materials.0.is_new', false));
    }
}
