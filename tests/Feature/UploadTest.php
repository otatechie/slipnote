<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\InteractsWithWorkspace;
use Tests\TestCase;

class UploadTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkspace();
        Storage::fake('public');
        $this->course = Course::create([
            'code' => 'MATH 251',
            'title' => 'Calculus II',
            'slug' => 'math-251',
        ]);
    }

    private function uploadUrl(): string
    {
        return route('course.upload', $this->wsParams(['slug' => $this->course->slug]));
    }

    public function test_it_uploads_a_valid_file_and_creates_a_material(): void
    {
        $file = UploadedFile::fake()->create('lecture.pdf', 100, 'application/pdf');

        $this->post($this->uploadUrl(), [
            'section' => 'notes',
            'uploaderName' => 'Alex',
            'files' => [$file],
        ])->assertRedirect();

        $material = Material::first();
        $this->assertNotNull($material);
        $this->assertSame('notes', $material->section);
        $this->assertSame('lecture.pdf', $material->original_filename);
        $this->assertSame('Alex', $material->uploader_name);
        Storage::disk('public')->assertExists($material->stored_path);
    }

    public function test_a_file_downloads_via_its_unguessable_token(): void
    {
        $material = $this->course->materials()->create([
            'section' => 'notes',
            'original_filename' => 'lecture.pdf',
            'stored_path' => UploadedFile::fake()->create('lecture.pdf', 10)->store('materials', 'public'),
            'manage_token' => 'tok-'.str_repeat('a', 36),
        ]);

        $this->get(route('material.download', ['token' => $material->manage_token]))
            ->assertOk()
            ->assertDownload('lecture.pdf');
    }

    public function test_files_cannot_be_enumerated_by_sequential_id(): void
    {
        $material = $this->course->materials()->create([
            'section' => 'notes',
            'original_filename' => 'secret.pdf',
            'stored_path' => 'x/secret.pdf',
            'manage_token' => 'the-only-valid-token-aaaaaaaaaaaaaaaaaa',
        ]);

        // The numeric id is no longer a valid download path — only the token is.
        $this->get('/download/'.$material->id)->assertNotFound();
        $this->get('/download/guessed-token')->assertNotFound();
    }

    public function test_course_page_shares_a_csrf_token_for_the_native_delete_form(): void
    {
        // The per-row owner delete is a native <form> POST that reads
        // $page.props.csrf_token; without it the form submits an empty token
        // and is rejected as a CSRF failure (419).
        $this->get(route('course.show', $this->wsParams(['slug' => $this->course->slug])))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('csrf_token', fn ($t) => is_string($t) && $t !== ''));
    }

    public function test_it_uploads_multiple_files_at_once(): void
    {
        $this->post($this->uploadUrl(), [
            'section' => 'slides',
            'uploaderName' => 'Sam',
            'files' => [
                UploadedFile::fake()->create('wk1.pdf', 50, 'application/pdf'),
                UploadedFile::fake()->create('wk2.pdf', 50, 'application/pdf'),
                UploadedFile::fake()->create('wk3.pdf', 50, 'application/pdf'),
            ],
        ])->assertRedirect();

        $this->assertSame(3, Material::count());
        $this->assertSame(['wk1.pdf', 'wk2.pdf', 'wk3.pdf'], Material::pluck('original_filename')->sort()->values()->all());
        // Each gets its own manage token and the shared section/uploader.
        $this->assertSame(3, Material::whereNotNull('manage_token')->count());
        $this->assertSame(3, Material::where('section', 'slides')->where('uploader_name', 'Sam')->count());
    }

    public function test_a_title_is_ignored_when_several_files_are_uploaded(): void
    {
        $this->post($this->uploadUrl(), [
            'section' => 'notes',
            'title' => 'My title',
            'files' => [
                UploadedFile::fake()->create('a.pdf', 50, 'application/pdf'),
                UploadedFile::fake()->create('b.pdf', 50, 'application/pdf'),
            ],
        ])->assertRedirect();

        // A shared title can't apply to several files — each keeps its own name.
        $this->assertSame(2, Material::whereNull('title')->count());
    }

    public function test_files_that_exceed_the_cap_are_skipped_but_others_save(): void
    {
        config(['noteshare.workspace_storage_bytes' => 1024 * 1024]); // 1 MB cap

        $this->post($this->uploadUrl(), [
            'section' => 'notes',
            'files' => [
                UploadedFile::fake()->create('fits.pdf', 600, 'application/pdf'),   // 600 KB — fits
                UploadedFile::fake()->create('toobig.pdf', 600, 'application/pdf'), // would exceed remaining
            ],
        ])->assertRedirect();

        // Only the first fit under the running cap.
        $this->assertSame(1, Material::count());
        $this->assertSame('fits.pdf', Material::first()->original_filename);
    }

    public function test_it_rejects_a_file_that_is_too_large(): void
    {
        $big = UploadedFile::fake()->create('huge.pdf', 12000, 'application/pdf'); // 12 MB > 10 MB cap

        $this->post($this->uploadUrl(), [
            'section' => 'notes',
            'files' => [$big],
        ])->assertSessionHasErrors(['files.0']);

        $this->assertSame(0, Material::count());
    }

    public function test_it_rejects_a_disallowed_file_type(): void
    {
        $exe = UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream');

        $this->post($this->uploadUrl(), [
            'section' => 'notes',
            'files' => [$exe],
        ])->assertSessionHasErrors(['files.0']);

        $this->assertSame(0, Material::count());
    }

    public function test_it_records_file_size_on_upload(): void
    {
        // 100 KB file → 102400 bytes
        $file = UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf');

        $this->post($this->uploadUrl(), [
            'section' => 'notes',
            'files' => [$file],
        ])->assertRedirect();

        $this->assertSame(102400, Material::first()->file_size);
        $this->assertSame(102400, $this->workspace->storageBytes());
    }

    public function test_it_rejects_upload_that_would_exceed_workspace_cap(): void
    {
        // Tighten the cap to 1 MB for this test.
        config(['noteshare.workspace_storage_bytes' => 1024 * 1024]);

        // Pre-fill 900 KB of existing material.
        $this->course->materials()->create([
            'section' => 'notes',
            'original_filename' => 'old.pdf',
            'stored_path' => 'materials/old.pdf',
            'file_size' => 900 * 1024,
        ]);

        $file = UploadedFile::fake()->create('big.pdf', 200, 'application/pdf'); // 200 KB → over cap

        $this->post($this->uploadUrl(), [
            'section' => 'notes',
            'files' => [$file],
        ])->assertSessionHasErrors(['files']);

        // Only the pre-seeded row exists.
        $this->assertSame(1, Material::count());
    }

    public function test_it_saves_an_optional_title_with_html_stripped(): void
    {
        $file = UploadedFile::fake()->create('wk7.pdf', 50, 'application/pdf');

        $this->post($this->uploadUrl(), [
            'section' => 'notes',
            'title' => '<b>Week 7</b> solutions',
            'files' => [$file],
        ])->assertRedirect();

        $this->assertSame('Week 7 solutions', Material::first()->title);
    }

    public function test_search_filters_by_title_and_filename_keeping_sections(): void
    {
        $this->course->materials()->create(['section' => 'notes', 'title' => 'Chain Rule notes', 'original_filename' => 'a.pdf', 'stored_path' => 'x/a.pdf']);
        $this->course->materials()->create(['section' => 'slides', 'title' => null, 'original_filename' => 'optimization-lecture.pdf', 'stored_path' => 'x/b.pdf']);
        $this->course->materials()->create(['section' => 'notes', 'title' => 'Limits', 'original_filename' => 'c.pdf', 'stored_path' => 'x/c.pdf']);

        $this->get(route('course.show', array_merge($this->wsParams(['slug' => $this->course->slug]), ['search' => 'optim'])))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('materials', 1)
                ->where('materials.0.original_filename', 'optimization-lecture.pdf')
            );
    }

    public function test_sort_az_orders_by_display_label(): void
    {
        $this->course->materials()->create(['section' => 'notes', 'title' => 'Zebra', 'original_filename' => 'z.pdf', 'stored_path' => 'x/z.pdf']);
        $this->course->materials()->create(['section' => 'notes', 'title' => 'Apple', 'original_filename' => 'a.pdf', 'stored_path' => 'x/a.pdf']);

        $this->get(route('course.show', array_merge($this->wsParams(['slug' => $this->course->slug]), ['sort' => 'az'])))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('materials.0.displayName', 'Apple')
                ->where('materials.1.displayName', 'Zebra')
            );
    }

    public function test_display_name_prefers_title_then_strips_extension(): void
    {
        $titled = new Material(['title' => 'Week 7 solutions', 'original_filename' => 'wk7.pdf']);
        $this->assertSame('Week 7 solutions', $titled->displayName());

        $untitled = new Material(['title' => null, 'original_filename' => 'lecture-1-limits.pdf']);
        $this->assertSame('lecture-1-limits', $untitled->displayName());

        $noExt = new Material(['title' => null, 'original_filename' => 'README']);
        $this->assertSame('README', $noExt->displayName());

        // stored filename is untouched (downloads keep the real name)
        $this->assertSame('lecture-1-limits.pdf', $untitled->original_filename);
    }

    public function test_file_type_buckets_by_extension(): void
    {
        $cases = [
            'notes.pdf' => 'pdf',
            'paper.PDF' => 'pdf',
            'essay.docx' => 'doc',
            'old.doc' => 'doc',
            'deck.pptx' => 'ppt',
            'scan.jpg' => 'image',
            'photo.PNG' => 'image',
            'data.zip' => 'file',
            'noext' => 'file',
        ];

        foreach ($cases as $name => $expected) {
            $m = new Material(['original_filename' => $name]);
            $this->assertSame($expected, $m->fileType(), "for {$name}");
        }
    }

    public function test_it_strips_html_from_the_uploader_name(): void
    {
        $file = UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf');

        $this->post($this->uploadUrl(), [
            'section' => 'notes',
            'uploaderName' => '<script>x</script>Bob',
            'files' => [$file],
        ])->assertRedirect();

        $this->assertSame('xBob', Material::first()->uploader_name);
    }

    public function test_upload_generates_a_manage_token(): void
    {
        $file = UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf');

        $this->post($this->uploadUrl(), [
            'section' => 'notes',
            'files' => [$file],
        ])->assertRedirect();

        $this->assertNotEmpty(Material::first()->manage_token);
    }

    public function test_correct_token_deletes_the_material_and_its_file(): void
    {
        $material = $this->course->materials()->create([
            'section' => 'notes',
            'original_filename' => 'a.pdf',
            'stored_path' => UploadedFile::fake()->create('a.pdf', 10)->store('materials', 'public'),
            'manage_token' => 'secret-token-value',
        ]);
        Storage::disk('public')->assertExists($material->stored_path);

        $this->delete(route('material.destroy', ['material' => $material->id, 'token' => 'secret-token-value']))
            ->assertRedirect(route('course.show', $this->wsParams(['slug' => $this->course->slug])));

        $this->assertSame(0, Material::count());
        Storage::disk('public')->assertMissing($material->stored_path);
    }

    public function test_wrong_token_does_not_delete(): void
    {
        $material = $this->course->materials()->create([
            'section' => 'notes',
            'original_filename' => 'a.pdf',
            'stored_path' => 'x/a.pdf',
            'manage_token' => 'the-real-token',
        ]);

        $this->delete(route('material.destroy', ['material' => $material->id, 'token' => 'wrong-token']))
            ->assertForbidden();

        $this->assertSame(1, Material::count());
    }

    public function test_a_row_without_a_token_is_not_deletable(): void
    {
        $material = $this->course->materials()->create([
            'section' => 'notes',
            'original_filename' => 'a.pdf',
            'stored_path' => 'x/a.pdf',
            'manage_token' => null,
        ]);

        $this->delete(route('material.destroy', ['material' => $material->id, 'token' => 'anything']))
            ->assertForbidden();

        $this->assertSame(1, Material::count());
    }

    public function test_owner_session_can_delete_any_file_without_its_token(): void
    {
        $material = $this->course->materials()->create([
            'section' => 'notes',
            'original_filename' => 'a.pdf',
            'stored_path' => 'x/a.pdf',
            'manage_token' => 'someone-elses-token',
        ]);

        $this->withSession([$this->workspace->ownerSessionKey() => true])
            ->delete(route('material.destroy', ['material' => $material->id, 'token' => 'owner']))
            ->assertRedirect(route('course.show', $this->wsParams(['slug' => $this->course->slug])));

        $this->assertSame(0, Material::count());
    }

    public function test_wrong_owner_secret_does_not_unlock_owner_mode(): void
    {
        $material = $this->course->materials()->create([
            'section' => 'notes',
            'original_filename' => 'a.pdf',
            'stored_path' => 'x/a.pdf',
            'manage_token' => 'tok',
        ]);

        // Visiting with a wrong ?owner= must not set the session flag
        $this->get(route('course.show', $this->wsParams(['slug' => $this->course->slug])).'?owner=not-the-secret');
        $this->assertNotSame(true, session($this->workspace->ownerSessionKey()));

        // and the destroy route still rejects without owner/token
        $this->delete(route('material.destroy', ['material' => $material->id, 'token' => 'owner']))
            ->assertForbidden();
        $this->assertSame(1, Material::count());
    }

    public function test_correct_owner_secret_unlocks_owner_mode(): void
    {
        $this->get(route('course.show', $this->wsParams(['slug' => $this->course->slug])).'?owner='.$this->ownerSecret)
            ->assertRedirect(route('course.show', $this->wsParams(['slug' => $this->course->slug])));

        $this->assertTrue(session($this->workspace->ownerSessionKey()));
    }

    public function test_uploads_are_open_when_no_passphrase_is_configured(): void
    {
        $file = UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf');

        $this->post($this->uploadUrl(), [
            'section' => 'notes',
            'files' => [$file],
        ])->assertRedirect();

        $this->assertSame(1, Material::count());
    }

    public function test_wrong_passphrase_blocks_the_upload(): void
    {
        $this->workspace->update(['upload_passphrase' => 'sesame']);
        $file = UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf');

        $this->post($this->uploadUrl(), [
            'section' => 'notes',
            'files' => [$file],
            'passphrase' => 'wrong',
        ])->assertSessionHasErrors(['passphrase']);

        $this->assertSame(0, Material::count());
    }

    public function test_correct_passphrase_allows_upload_and_unlocks_session(): void
    {
        $this->workspace->update(['upload_passphrase' => 'sesame']);
        $this->assertTrue(Hash::check('sesame', $this->workspace->fresh()->upload_passphrase));

        $file = UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf');

        $this->post($this->uploadUrl(), [
            'section' => 'notes',
            'files' => [$file],
            'passphrase' => 'sesame',
        ])->assertRedirect();

        $this->assertSame(1, Material::count());
        $this->assertTrue(session($this->workspace->uploadUnlockKey()));
    }

    public function test_legacy_plaintext_passphrase_is_upgraded_after_successful_entry(): void
    {
        DB::table('workspaces')
            ->where('id', $this->workspace->id)
            ->update(['upload_passphrase' => 'sesame']);
        $this->actingInWorkspace($this->workspace = $this->workspace->fresh());
        $file = UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf');

        $this->post($this->uploadUrl(), [
            'section' => 'notes',
            'files' => [$file],
            'passphrase' => 'sesame',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('sesame', $this->workspace->fresh()->upload_passphrase));
    }
}
