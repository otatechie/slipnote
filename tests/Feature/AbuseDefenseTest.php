<?php

namespace Tests\Feature;

use App\Models\BlockedUpload;
use App\Models\Course;
use App\Models\Material;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\InteractsWithWorkspace;
use Tests\TestCase;

class AbuseDefenseTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    private Course $course;

    // A minimal valid-PDF body so the mimes:pdf rule passes, while letting us
    // control the exact bytes (and therefore the content hash).
    private function pdf(string $marker): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('doc.pdf', "%PDF-1.4\n{$marker}\n%%EOF");
    }

    private function pdfHash(string $marker): string
    {
        return hash('sha256', "%PDF-1.4\n{$marker}\n%%EOF");
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkspace();
        Storage::fake('local');
        RateLimiter::clear('upload:127.0.0.1');
        $this->course = Course::create(['code' => 'MATH 251', 'title' => 'Calc', 'slug' => 'math-251']);
    }

    private function uploadUrl(): string
    {
        return route('course.upload', $this->wsParams(['slug' => $this->course->slug]));
    }

    public function test_uploads_are_rate_limited_per_ip(): void
    {
        // 30 allowed in the window; the 31st is throttled.
        RateLimiter::clear('upload:127.0.0.1');
        for ($i = 0; $i < 30; $i++) {
            RateLimiter::hit('upload:127.0.0.1', 600);
        }

        $this->post($this->uploadUrl(), [
            'section' => 'notes',
            'files' => [UploadedFile::fake()->create('a.pdf', 50, 'application/pdf')],
        ])->assertSessionHasErrors(['files']);

        $this->assertSame(0, Material::count());
    }

    public function test_removing_a_file_blocklists_its_content_hash(): void
    {
        config(['noteshare.operator_secret' => 'op']);
        $material = $this->course->materials()->create([
            'section' => 'notes',
            'original_filename' => 'bad.pdf',
            'stored_path' => $this->pdf('malicious')->store('materials', 'local'),
            'manage_token' => 'tok-'.str_repeat('a', 36),
        ]);

        $this->withSession(['operator_fp' => hash('sha256', 'op')])
            ->post(route('operator.remove', $material->id))
            ->assertRedirect();

        $this->assertDatabaseHas('blocked_uploads', ['content_hash' => $this->pdfHash('malicious')]);
    }

    public function test_a_blocklisted_file_cannot_be_re_uploaded(): void
    {
        BlockedUpload::create(['content_hash' => $this->pdfHash('malicious')]);

        $this->post($this->uploadUrl(), [
            'section' => 'notes',
            'files' => [$this->pdf('malicious')],
        ])->assertSessionHasErrors(['files']);

        $this->assertSame(0, Material::count());
    }

    public function test_a_clean_file_still_uploads_alongside_a_blocked_one(): void
    {
        BlockedUpload::create(['content_hash' => $this->pdfHash('malicious')]);

        $this->post($this->uploadUrl(), [
            'section' => 'notes',
            'files' => [$this->pdf('malicious'), $this->pdf('totally-fine')],
        ])->assertRedirect();

        // Only the clean file lands.
        $this->assertSame(1, Material::count());
    }

    public function test_workspace_creation_is_rate_limited_per_ip(): void
    {
        RateLimiter::clear('workspace_create:127.0.0.1');
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit('workspace_create:127.0.0.1', 3600);
        }

        $this->post(route('workspaces.store'), ['name' => 'Late Board'])
            ->assertSessionHasErrors(['name']);

        $this->assertNull(Workspace::where('slug', 'late-board')->first());
    }
}
