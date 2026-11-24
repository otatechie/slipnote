<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Material;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The example board: made by a command, opened by /demo, marked by a banner. */
class ExampleBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function makeBoard(): Workspace
    {
        $this->artisan('slipnote:example-board')
            ->expectsOutputToContain('uploads locked')
            ->expectsOutputToContain('DEMO_BOARD=')
            ->assertSuccessful();

        return Workspace::sole();
    }

    public function test_the_command_makes_a_locked_class_with_real_files(): void
    {
        $board = $this->makeBoard();

        $this->assertTrue((bool) $board->uploads_owner_only);
        $courses = Course::withoutWorkspaceScope()->where('workspace_id', $board->id)->get();
        $this->assertSame(['Maths', 'English', 'Biology', 'History'], $courses->sortBy('position')->pluck('code')->all());

        $files = Material::whereIn('course_id', $courses->pluck('id'))->get();
        $this->assertGreaterThanOrEqual(12, $files->count());
        foreach ($files as $file) {
            $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($file->stored_path));
            $this->assertNotNull($file->title);
        }
        // Each section of a course has something in it, so the board looks like a real class.
        $this->assertEqualsCanonicalizing(['notes', 'slides', 'past_papers'], $files->pluck('section')->unique()->values()->all());
    }

    public function test_demo_opens_the_example_board_and_keeps_the_emails_ref(): void
    {
        $board = $this->makeBoard();
        config(['noteshare.demo_board' => $board->slug]);

        $this->get('/demo?ref=via-scotland')
            ->assertRedirect(route('courses.index', ['workspace' => $board->slug]))
            ->assertCookie('slipnote_ref', 'via-scotland');
    }

    public function test_no_demo_until_it_is_set_or_once_it_is_archived(): void
    {
        $this->get('/demo')->assertNotFound();

        config(['noteshare.demo_board' => 'no-such-board-abc123']);
        $this->get('/demo')->assertNotFound();

        $board = $this->makeBoard();
        config(['noteshare.demo_board' => $board->slug]);
        $board->forceFill(['archived_at' => now()])->save();
        $this->get('/demo')->assertNotFound();
    }

    public function test_only_the_example_board_says_it_is_one(): void
    {
        $board = $this->makeBoard();
        config(['noteshare.demo_board' => $board->slug]);
        [$other] = Workspace::provision('Grade 5 English B');

        $this->get('/'.$board->slug)->assertInertia(fn ($page) => $page->where('exampleBoard', true));
        $this->get('/'.$board->slug.'/c/maths')->assertInertia(fn ($page) => $page->where('exampleBoard', true));
        $this->get('/'.$other->slug)->assertInertia(fn ($page) => $page->where('exampleBoard', false));
        $this->get('/start')->assertInertia(fn ($page) => $page->where('exampleBoard', false));
    }

    public function test_the_schools_page_offers_the_example_only_when_there_is_one(): void
    {
        $this->get('/schools')->assertDontSee('See an example class');

        config(['noteshare.demo_board' => $this->makeBoard()->slug]);
        $this->get('/schools')->assertSee('See an example class')->assertSee(route('demo'));
    }
}
