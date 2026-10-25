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
 * What lets a lecturer's own files stand out (pinning to the top of a
 * section, and a record of what they uploaded), and deleting a board.
 */
class OwnerMarksTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkspace();
        Storage::fake('local');
        $this->course = Course::create(['code' => 'BIO 101', 'title' => 'Cell Biology', 'slug' => 'bio-101']);
    }

    private function addFile(string $name, int $ageSeconds = 0): Material
    {
        Storage::disk('local')->put('materials/'.$name, 'x');
        $m = $this->course->materials()->create([
            'section' => 'notes',
            'original_filename' => $name,
            'stored_path' => 'materials/'.$name,
            'download_token' => 'dl-'.md5($name),
            'file_size' => 1,
        ]);
        $m->forceFill(['created_at' => now()->subSeconds($ageSeconds)])->saveQuietly();

        return $m;
    }

    private function upload(): void
    {
        $this->post(route('course.upload', $this->wsParams(['slug' => 'bio-101'])), [
            'section' => 'notes',
            'files' => [UploadedFile::fake()->createWithContent('wk'.uniqid().'.pdf', uniqid())],
        ])->assertSessionHasNoErrors()->assertSessionHas('uploaded', 'File added.');
    }

    // --- Uploaded in owner mode (recorded, not shown on the row) ---

    public function test_a_file_uploaded_in_owner_mode_is_marked_as_the_owners(): void
    {
        $this->unlockOwnerSession();
        $this->upload();

        $this->assertTrue(Material::first()->by_owner);
    }

    public function test_a_classmates_upload_is_not_marked(): void
    {
        $this->upload();

        $this->assertFalse(Material::first()->by_owner);
    }

    public function test_the_course_page_says_which_files_are_pinned_but_not_whose_they_are(): void
    {
        $this->addFile('a.pdf')->forceFill(['by_owner' => true, 'pinned_at' => now()])->save();

        $this->get(route('course.show', $this->wsParams(['slug' => 'bio-101'])))
            ->assertInertia(fn ($page) => $page
                ->where('materials.0.pinned', true)
                ->missing('materials.0.by_owner'));
    }

    // --- Pinning ---

    public function test_the_owner_can_pin_a_file_to_the_top_whatever_the_sort(): void
    {
        $old = $this->addFile('old.pdf', ageSeconds: 3600);
        $this->addFile('new.pdf');
        $this->unlockOwnerSession();

        $this->post(route('material.pin', $this->wsParams(['slug' => 'bio-101', 'material' => $old->id])), ['pinned' => true])
            ->assertRedirect()
            ->assertSessionHas('notice', 'Pinned to the top of Notes')
            // Not an upload, so not the upload receipt (Undo, Tell the class).
            ->assertSessionMissing('uploaded');

        foreach (['newest', 'oldest', 'az'] as $sort) {
            $this->get(route('course.show', $this->wsParams(['slug' => 'bio-101', 'sort' => $sort])))
                ->assertInertia(fn ($page) => $page->where('materials.0.original_filename', 'old.pdf'));
        }

        $this->post(route('material.pin', $this->wsParams(['slug' => 'bio-101', 'material' => $old->id])), ['pinned' => false]);
        $this->assertNull($old->fresh()->pinned_at);
    }

    public function test_a_section_holds_at_most_three_pins(): void
    {
        $files = collect(range(1, 4))->map(fn ($i) => $this->addFile("f{$i}.pdf"));
        $this->unlockOwnerSession();
        $pin = fn (Material $m) => $this->post(route('material.pin', $this->wsParams(['slug' => 'bio-101', 'material' => $m->id])), ['pinned' => true]);

        $files->take(3)->each(fn ($m) => $pin($m)->assertSessionHasNoErrors());
        $pin($files[3])->assertSessionHasErrors(['pin' => 'Up to 3 pinned files per section. Unpin one first.']);
        $this->assertNull($files[3]->fresh()->pinned_at);

        // Re-pinning one already pinned isn't a fourth, and keeps its place.
        $first = $files[0]->fresh()->pinned_at;
        $pin($files[0])->assertSessionHasNoErrors();
        $this->assertEquals($first, $files[0]->fresh()->pinned_at);

        // Another section has its own three.
        $other = $this->course->materials()->create([
            'section' => 'slides', 'original_filename' => 's.pdf', 'stored_path' => 'materials/s.pdf',
            'download_token' => 'dl-s', 'file_size' => 1,
        ]);
        $pin($other)->assertSessionHasNoErrors();

        $this->get(route('course.show', $this->wsParams(['slug' => 'bio-101', 'search' => 'f4'])))
            ->assertInertia(fn ($page) => $page
                ->where('pinnedCounts.notes', 3)
                ->where('pinnedCounts.slides', 1)
                ->where('maxPinned', 3));
    }

    // --- Editing a file's name and section ---

    private function edit(Material $m, array $data)
    {
        return $this->patch(route('material.update', $this->wsParams(['slug' => 'bio-101', 'material' => $m->id])), $data);
    }

    public function test_the_owner_can_rename_a_file_and_move_it_to_another_section(): void
    {
        $m = $this->addFile('scan_0042.pdf');
        $this->unlockOwnerSession();

        $this->edit($m, ['name' => '  Week 3 <b>past</b> paper ', 'section' => 'past_papers'])
            ->assertRedirect()
            ->assertSessionHas('notice', 'Moved to Past papers');

        $m->refresh();
        $this->assertSame(['Week 3 past paper', 'past_papers'], [$m->title, $m->section]);
        $this->assertSame('Week 3 past paper', $m->displayName());

        $this->edit($m, ['name' => 'Week 3 paper', 'section' => 'past_papers'])->assertSessionHas('notice', 'Saved');
        $this->assertSame('Week 3 paper', $m->fresh()->title);
    }

    public function test_an_empty_name_goes_back_to_the_original_filename(): void
    {
        $m = $this->addFile('lecture-7.pdf');
        $m->forceFill(['title' => 'Something wrong'])->save();
        $this->unlockOwnerSession();

        $this->edit($m, ['name' => '   ', 'section' => 'notes'])->assertSessionHasNoErrors();

        $this->assertNull($m->fresh()->title);
        $this->assertSame('lecture-7', $m->fresh()->displayName());
    }

    public function test_moving_a_pinned_file_into_a_full_section_unpins_it(): void
    {
        $this->unlockOwnerSession();
        foreach (range(1, 3) as $i) {
            $this->addFile("p{$i}.pdf")->forceFill(['section' => 'slides', 'pinned_at' => now()])->save();
        }
        $pinned = $this->addFile('mine.pdf');
        $pinned->forceFill(['pinned_at' => now()])->save();

        $this->edit($pinned, ['name' => 'mine', 'section' => 'slides'])
            ->assertSessionHas('notice', 'Moved to Slides and unpinned. Slides already has 3 pinned.');
        $this->assertNull($pinned->fresh()->pinned_at);

        $kept = $this->addFile('kept.pdf');
        $kept->forceFill(['pinned_at' => now()])->save();
        $this->edit($kept, ['name' => 'kept', 'section' => 'past_papers'])->assertSessionHas('notice', 'Moved to Past papers');
        $this->assertNotNull($kept->fresh()->pinned_at);
    }

    public function test_only_the_owner_can_edit_a_file_and_only_on_their_board(): void
    {
        $m = $this->addFile('a.pdf');

        $this->edit($m, ['name' => 'Hacked', 'section' => 'slides'])->assertForbidden();
        $this->assertSame([null, 'notes'], [$m->fresh()->title, $m->fresh()->section]);

        $this->unlockOwnerSession();
        $this->edit($m, ['name' => 'x', 'section' => 'homework'])->assertSessionHasErrors('section');
        $this->edit($m, ['name' => str_repeat('a', 121), 'section' => 'notes'])->assertSessionHasErrors('name');

        $other = Workspace::create(['name' => 'Other', 'slug' => 'other-board', 'owner_secret_hash' => 'x']);
        app(\App\Tenancy\Tenancy::class)->set($other);
        $theirs = Course::create(['code' => 'X 1', 'title' => 'X', 'slug' => 'bio-101']);
        $foreign = $theirs->materials()->create([
            'section' => 'notes', 'original_filename' => 'f.pdf', 'stored_path' => 'materials/f.pdf', 'download_token' => 'dl-f', 'file_size' => 1,
        ]);
        app(\App\Tenancy\Tenancy::class)->set($this->workspace);

        $this->edit($foreign, ['name' => 'Hacked', 'section' => 'notes'])->assertNotFound();
        $this->assertNull($foreign->fresh()->title);
    }

    public function test_only_the_owner_can_pin(): void
    {
        $m = $this->addFile('a.pdf');

        $this->post(route('material.pin', $this->wsParams(['slug' => 'bio-101', 'material' => $m->id])), ['pinned' => true])
            ->assertForbidden();
        $this->assertNull($m->fresh()->pinned_at);
    }

    public function test_owning_one_board_cannot_pin_a_file_on_another(): void
    {
        $theirs = $this->addFile('theirs.pdf');

        [$mine] = Workspace::provision('My Own Board');
        $this->actingInWorkspace($mine);
        Course::create(['code' => 'BIO 101', 'title' => 'Mine', 'slug' => 'bio-101']);
        $this->unlockOwnerSession($mine);

        // Through my board's URL, their file isn't found in my course.
        $this->post(route('material.pin', ['workspace' => $mine->slug, 'slug' => 'bio-101', 'material' => $theirs->id]), ['pinned' => true])
            ->assertNotFound();
        $this->assertNull($theirs->fresh()->pinned_at);
    }

    // --- Deleting a board ---

    public function test_the_owner_deletes_the_board_and_every_file_by_typing_its_name(): void
    {
        $file = $this->addFile('a.pdf');
        $this->unlockOwnerSession();

        $this->delete(route('courses.destroy', $this->wsParams()), ['confirmName' => '  test workspace '])
            ->assertRedirect(route('start'))
            ->assertSessionHas('boardDeleted', 'Test Workspace');

        $this->assertNull(Workspace::find($this->workspace->id));
        $this->assertSame(0, Course::withoutGlobalScopes()->where('workspace_id', $this->workspace->id)->count());
        $this->assertNull(Material::find($file->id));
        Storage::disk('local')->assertMissing('materials/a.pdf');
        $this->assertFalse(session()->has($this->workspace->ownerSessionKey()));

        $this->get('/'.$this->workspace->slug)->assertNotFound();
    }

    public function test_a_wrong_name_deletes_nothing(): void
    {
        $this->addFile('a.pdf');
        $this->unlockOwnerSession();

        $this->delete(route('courses.destroy', $this->wsParams()), ['confirmName' => 'Test'])
            ->assertSessionHasErrors('confirmName');

        $this->assertNotNull(Workspace::find($this->workspace->id));
        Storage::disk('local')->assertExists('materials/a.pdf');
    }

    public function test_only_the_owner_can_delete_a_board(): void
    {
        $this->delete(route('courses.destroy', $this->wsParams()), ['confirmName' => 'Test Workspace'])
            ->assertForbidden();

        $this->assertNotNull(Workspace::find($this->workspace->id));
    }

    public function test_deleting_one_board_leaves_another_untouched(): void
    {
        [$other] = Workspace::provision('Other Board');
        $this->actingInWorkspace($other);
        $otherCourse = Course::create(['code' => 'CHEM 1', 'title' => 'Chem', 'slug' => 'chem-1']);
        Storage::disk('local')->put('materials/keep.pdf', 'x');
        $kept = $otherCourse->materials()->create([
            'section' => 'notes', 'original_filename' => 'keep.pdf', 'stored_path' => 'materials/keep.pdf',
            'download_token' => 'dl-keep', 'file_size' => 1,
        ]);

        $this->actingInWorkspace($this->workspace);
        $this->addFile('a.pdf');
        $this->unlockOwnerSession();
        $this->delete(route('courses.destroy', $this->wsParams()), ['confirmName' => 'Test Workspace']);

        $this->assertNotNull(Workspace::find($other->id));
        $this->assertNotNull(Course::withoutGlobalScopes()->find($otherCourse->id));
        $this->assertNotNull(Material::find($kept->id));
        Storage::disk('local')->assertExists('materials/keep.pdf');
    }
}
