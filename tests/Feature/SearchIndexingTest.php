<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\InteractsWithWorkspace;
use Tests\TestCase;

class SearchIndexingTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkspace();
        Storage::fake('local');
    }

    public function test_the_public_pages_stay_indexable(): void
    {
        foreach (['/', '/privacy', '/terms'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertHeaderMissing('X-Robots-Tag');
        }
    }

    public function test_workspace_pages_are_kept_out_of_search(): void
    {
        $this->get('/'.$this->workspace->slug)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_a_board_page_renders_a_preview_card_with_the_board_name(): void
    {
        // Server-rendered: preview fetchers don't run JS.
        $this->get('/'.$this->workspace->slug)
            ->assertOk()
            ->assertSee('property="og:title" content="Test Workspace · SlipNote"', false)
            ->assertSee('property="og:image" content="'.url('/og.png').'"', false);
    }

    public function test_the_preview_card_never_carries_the_owner_secret(): void
    {
        // A rep opening ?owner=SECRET is redirected to the clean URL before
        // any HTML renders, so the secret can't reach a preview card.
        $this->get('/'.$this->workspace->slug.'?owner='.$this->ownerSecret)
            ->assertRedirect(route('courses.index', ['workspace' => $this->workspace->slug]));

        // And the page it lands on advertises the shareable URL, not the
        // owner one — og:url travels into every chat the link is pasted in.
        $this->get('/'.$this->workspace->slug)
            ->assertOk()
            ->assertSee('property="og:url" content="'.url('/'.$this->workspace->slug).'"', false)
            ->assertDontSee($this->ownerSecret, false);
    }

    public function test_the_preview_card_exposes_no_course_or_file_names(): void
    {
        Course::create([
            'code' => 'MATH 251',
            'title' => 'Calculus II',
            'slug' => 'math-251',
        ]);

        $html = $this->get('/'.$this->workspace->slug)->assertOk()->getContent();
        $head = substr($html, 0, strpos($html, '</head>'));

        $this->assertStringNotContainsString('MATH 251', $head);
        $this->assertStringNotContainsString('Calculus II', $head);
    }

    public function test_the_marketing_page_keeps_its_own_card(): void
    {
        // The Blade landing has its own OG block; the Inertia shell must not
        // interfere with it.
        $this->get('/')
            ->assertOk()
            ->assertSee('property="og:title" content="SlipNote: Never ask', false);
    }

    public function test_a_downloaded_file_carries_the_noindex_header(): void
    {
        // The case a robots <meta> tag can't cover: the response is a PDF,
        // not HTML, so the header is the only signal a crawler would see.
        $course = Course::create([
            'code' => 'MATH 251',
            'title' => 'Calculus II',
            'slug' => 'math-251',
        ]);

        $this->post(route('course.upload', $this->wsParams(['slug' => $course->slug])), [
            'section' => 'notes',
            'files' => [UploadedFile::fake()->create('lecture.pdf', 10, 'application/pdf')],
        ]);

        $material = Material::firstOrFail();

        $this->get(route('material.download', ['token' => $material->manage_token]))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
