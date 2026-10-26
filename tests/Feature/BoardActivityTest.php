<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\InteractsWithWorkspace;
use Tests\TestCase;

class BoardActivityTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkspace();
        Storage::fake('local');
        $this->course = Course::create([
            'code' => 'MATH 251',
            'title' => 'Calculus II',
            'slug' => 'math-251',
        ]);
    }

    public function test_a_new_board_has_never_been_accessed(): void
    {
        $this->assertNull($this->workspace->last_accessed_at);
    }

    public function test_viewing_a_board_records_the_access(): void
    {
        $this->get('/'.$this->workspace->slug)->assertOk();

        $this->assertNotNull($this->workspace->fresh()->last_accessed_at);
    }

    public function test_downloading_a_file_records_access_on_its_board(): void
    {
        // The signal that matters: a past-papers archive gets no uploads for a
        // year and is downloaded every exam week. This route sits outside the
        // workspace middleware group, so it needs its own touch.
        $material = $this->uploadOne();
        $this->workspace->forceFill(['last_accessed_at' => null])->saveQuietly();

        $this->get(route('material.download', ['token' => $material->download_token]))
            ->assertOk();

        $this->assertNotNull($this->workspace->fresh()->last_accessed_at);
    }

    public function test_access_is_throttled_to_the_hour(): void
    {
        // Answering "touched in months?" doesn't need per-request writes.
        $this->get('/'.$this->workspace->slug)->assertOk();
        $first = $this->workspace->fresh()->last_accessed_at;

        $this->travel(5)->minutes();
        $this->get('/'.$this->workspace->slug)->assertOk();

        $this->assertTrue($first->equalTo($this->workspace->fresh()->last_accessed_at));

        $this->travel(2)->hours();
        $this->get('/'.$this->workspace->slug)->assertOk();

        $this->assertTrue($this->workspace->fresh()->last_accessed_at->gt($first));
    }

    public function test_recording_access_does_not_look_like_an_edit(): void
    {
        // updated_at is the "was this board changed" signal; a visit isn't one.
        $before = $this->workspace->updated_at;

        $this->travel(2)->hours();
        $this->get('/'.$this->workspace->slug)->assertOk();

        $this->assertTrue($before->equalTo($this->workspace->fresh()->updated_at));
    }

    public function test_a_classmate_visit_is_noted_once_a_day(): void
    {
        $this->get('/'.$this->workspace->slug)->assertOk();
        $this->get('/'.$this->workspace->slug)->assertOk();

        $this->assertSame(1, $this->visitorDays());

        $this->travel(1)->days();
        $this->get('/'.$this->workspace->slug)->assertOk();

        $this->assertSame(2, $this->visitorDays());
    }

    public function test_the_owners_visits_keep_the_board_but_are_not_classmate_visits(): void
    {
        $this->unlockOwnerSession();

        $this->get('/'.$this->workspace->slug)->assertOk();

        $this->assertNotNull($this->workspace->fresh()->last_accessed_at);
        $this->assertSame(0, $this->visitorDays());
    }

    public function test_a_classmate_download_is_a_visit(): void
    {
        $this->unlockOwnerSession();
        $material = $this->uploadOne();
        $this->flushSession();

        $this->get(route('material.download', ['token' => $material->download_token]))->assertOk();

        $this->assertSame(1, $this->visitorDays());
    }

    public function test_purging_a_board_removes_its_visitor_days(): void
    {
        $this->get('/'.$this->workspace->slug)->assertOk();

        $this->workspace->fresh()->purge();

        $this->assertSame(0, \DB::table('board_visit_days')->count());
    }

    private function visitorDays(): int
    {
        return \DB::table('board_visit_days')->where('workspace_id', $this->workspace->id)->count();
    }

    public function test_access_is_scoped_to_the_board_that_was_visited(): void
    {
        [$other] = \App\Models\Workspace::provision('Other Board');

        $this->get('/'.$this->workspace->slug)->assertOk();

        $this->assertNull($other->fresh()->last_accessed_at);
    }

    private function uploadOne(): Material
    {
        $this->post(route('course.upload', $this->wsParams(['slug' => $this->course->slug])), [
            'section' => 'notes',
            'files' => [UploadedFile::fake()->create('lecture.pdf', 10, 'application/pdf')],
        ]);

        return Material::firstOrFail();
    }
}
