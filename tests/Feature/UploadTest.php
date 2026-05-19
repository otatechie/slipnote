<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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

    public function test_it_uploads_a_valid_file_and_creates_a_material(): void
    {
        $file = UploadedFile::fake()->create('lecture.pdf', 100, 'application/pdf');

        Livewire::test('course-page', ['slug' => $this->course->slug])
            ->set('section', 'notes')
            ->set('uploaderName', 'Alex')
            ->set('file', $file)
            ->call('save')
            ->assertHasNoErrors();

        $material = Material::first();
        $this->assertNotNull($material);
        $this->assertSame('notes', $material->section);
        $this->assertSame('lecture.pdf', $material->original_filename);
        $this->assertSame('Alex', $material->uploader_name);
        Storage::disk('public')->assertExists($material->stored_path);
    }

    public function test_it_rejects_a_file_that_is_too_large(): void
    {
        $big = UploadedFile::fake()->create('huge.pdf', 12000, 'application/pdf'); // 12 MB > 10 MB cap

        Livewire::test('course-page', ['slug' => $this->course->slug])
            ->set('file', $big)
            ->call('save')
            ->assertHasErrors(['file']);

        $this->assertSame(0, Material::count());
    }

    public function test_it_rejects_a_disallowed_file_type(): void
    {
        $exe = UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream');

        Livewire::test('course-page', ['slug' => $this->course->slug])
            ->set('file', $exe)
            ->call('save')
            ->assertHasErrors(['file']);

        $this->assertSame(0, Material::count());
    }

    public function test_it_saves_an_optional_title_with_html_stripped(): void
    {
        $file = UploadedFile::fake()->create('wk7.pdf', 50, 'application/pdf');

        Livewire::test('course-page', ['slug' => $this->course->slug])
            ->set('title', '<b>Week 7</b> solutions')
            ->set('file', $file)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Week 7 solutions', Material::first()->title);
    }

    public function test_search_filters_by_title_and_filename_keeping_sections(): void
    {
        $this->course->materials()->create(['section' => 'notes', 'title' => 'Chain Rule notes', 'original_filename' => 'a.pdf', 'stored_path' => 'x/a.pdf']);
        $this->course->materials()->create(['section' => 'slides', 'title' => null, 'original_filename' => 'optimization-lecture.pdf', 'stored_path' => 'x/b.pdf']);
        $this->course->materials()->create(['section' => 'notes', 'title' => 'Limits', 'original_filename' => 'c.pdf', 'stored_path' => 'x/c.pdf']);

        // Search still matches on the full filename internally, even though
        // the displayed name has its extension stripped.
        Livewire::test('course-page', ['slug' => $this->course->slug])
            ->set('search', 'optim')
            ->assertSee('optimization-lecture') // display name (extension stripped)
            ->assertDontSee('Chain Rule notes')
            ->assertDontSee('Limits');
    }

    public function test_sort_az_orders_by_display_label(): void
    {
        $this->course->materials()->create(['section' => 'notes', 'title' => 'Zebra', 'original_filename' => 'z.pdf', 'stored_path' => 'x/z.pdf']);
        $this->course->materials()->create(['section' => 'notes', 'title' => 'Apple', 'original_filename' => 'a.pdf', 'stored_path' => 'x/a.pdf']);

        $component = Livewire::test('course-page', ['slug' => $this->course->slug])->set('sort', 'az');

        $html = $component->html();
        $this->assertLessThan(strpos($html, 'Zebra'), strpos($html, 'Apple'), 'Apple should render before Zebra when sorted A–Z');
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

        Livewire::test('course-page', ['slug' => $this->course->slug])
            ->set('uploaderName', '<script>x</script>Bob')
            ->set('file', $file)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('xBob', Material::first()->uploader_name);
    }

    public function test_upload_generates_a_manage_token(): void
    {
        $file = UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf');

        Livewire::test('course-page', ['slug' => $this->course->slug])
            ->set('file', $file)
            ->call('save')
            ->assertHasNoErrors();

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

        // Even guessing an empty/any token must not delete a tokenless (seed) row.
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

        // No token knowledge — authorised purely by the per-workspace
        // owner session.
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

        // Visiting with a wrong ?owner= must not set the session flag...
        Livewire::withQueryParams(['owner' => 'not-the-secret'])
            ->test('course-page', ['slug' => $this->course->slug]);
        $this->assertNotSame(true, session($this->workspace->ownerSessionKey()));

        // ...and the destroy route still rejects without owner/token.
        $this->delete(route('material.destroy', ['material' => $material->id, 'token' => 'owner']))
            ->assertForbidden();
        $this->assertSame(1, Material::count());
    }

    public function test_correct_owner_secret_unlocks_owner_mode(): void
    {
        Livewire::withQueryParams(['owner' => $this->ownerSecret])
            ->test('course-page', ['slug' => $this->course->slug])
            ->assertRedirect(route('course.show', $this->wsParams(['slug' => $this->course->slug])));

        $this->assertTrue(session($this->workspace->ownerSessionKey()));
    }

    public function test_uploads_are_open_when_no_passphrase_is_configured(): void
    {
        // Workspace has no passphrase by default → uploads open.
        $file = UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf');

        Livewire::test('course-page', ['slug' => $this->course->slug])
            ->set('file', $file)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Material::count());
    }

    public function test_wrong_passphrase_blocks_the_upload(): void
    {
        $this->workspace->update(['upload_passphrase' => 'sesame']);
        $file = UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf');

        Livewire::test('course-page', ['slug' => $this->course->slug])
            ->set('file', $file)
            ->set('passphrase', 'wrong')
            ->call('save')
            ->assertHasErrors(['passphrase']);

        $this->assertSame(0, Material::count());
    }

    public function test_correct_passphrase_allows_upload_and_unlocks_session(): void
    {
        $this->workspace->update(['upload_passphrase' => 'sesame']);
        $this->assertTrue(Hash::check('sesame', $this->workspace->fresh()->upload_passphrase));

        $file = UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf');

        Livewire::test('course-page', ['slug' => $this->course->slug])
            ->set('file', $file)
            ->set('passphrase', 'sesame')
            ->call('save')
            ->assertHasNoErrors();

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

        Livewire::test('course-page', ['slug' => $this->course->slug])
            ->set('file', $file)
            ->set('passphrase', 'sesame')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('sesame', $this->workspace->fresh()->upload_passphrase));
    }
}
