<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Material;
use App\Models\Suggestion;
use App\Models\Workspace;
use App\Services\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\InteractsWithWorkspace;
use Tests\TestCase;

class OperatorTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkspace();
        Storage::fake('local');
        config(['noteshare.operator_secret' => 'op-secret']);

        $course = Course::create(['code' => 'MATH 251', 'title' => 'Calc', 'slug' => 'math-251']);
        $this->material = $course->materials()->create([
            'section' => 'notes',
            'original_filename' => 'bad.pdf',
            'stored_path' => UploadedFile::fake()->create('bad.pdf', 10)->store('materials', 'local'),
            'manage_token' => 'tok-'.str_repeat('a', 36),
            'download_token' => 'dl-'.str_repeat('a', 37),
        ]);
        $this->material->reports()->create(['reason' => 'spam', 'reporter_ip' => '1.2.3.4']);
    }

    public function test_dashboard_is_hidden_when_no_operator_secret_is_configured(): void
    {
        config(['noteshare.operator_secret' => null]);

        $this->get(route('operator.dashboard'))->assertNotFound();
    }

    public function test_dashboard_shows_a_login_form_until_authenticated(): void
    {
        $this->get(route('operator.dashboard'))
            ->assertOk()
            ->assertSee('Operator secret', false);
    }

    public function test_wrong_secret_is_rejected(): void
    {
        $this->post(route('operator.login'), ['secret' => 'nope'])
            ->assertSessionHasErrors(['secret']);

        $this->assertNull(session('operator_fp'));
    }

    public function test_correct_secret_unlocks_and_lists_reported_files(): void
    {
        $this->post(route('operator.login'), ['secret' => 'op-secret'])
            ->assertRedirect(route('operator.dashboard'));

        $this->assertSame(hash('sha256', 'op-secret'), session('operator_fp'));

        $this->get(route('operator.dashboard'))
            ->assertOk()
            ->assertSee('MATH 251')   // course code leads the title so rows distinguish
            ->assertSee('spam')       // the report reason
            ->assertSee('Review file', false)
            ->assertSee('Test Workspace', false)
            ->assertSee(route('course.show', ['workspace' => $this->workspace->slug, 'slug' => 'math-251']), false);
    }

    public function test_boards_tab_is_kept_in_the_url(): void
    {
        $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->get(route('operator.dashboard', ['tab' => 'boards']))
            ->assertOk()
            ->assertSee(route('operator.dashboard', ['tab' => 'boards']), false)
            ->assertSee(route('courses.index', ['workspace' => $this->workspace->slug]), false)
            ->assertDontSee('Review file', false);
    }

    public function test_the_boards_tab_pages_through_every_board(): void
    {
        // setUp made one board; 24 more, oldest first, so 25 in all.
        for ($i = 1; $i <= 24; $i++) {
            [$ws] = Workspace::provision("Paged Board {$i}");
            $ws->forceFill(['created_at' => now()->subMinutes(100 - $i)])->saveQuietly();
        }
        $this->workspace->forceFill(['created_at' => now()->subDays(1)])->saveQuietly();
        $session = ['operator_fp' => hash('sha256', 'op-secret')];

        $this->withSession($session)->get(route('operator.dashboard', ['tab' => 'boards', 'sort' => 'newest', 'empty' => 1]))
            ->assertOk()
            ->assertSee('1–20 of 25, newest first.', false)
            ->assertSee('Page 1 of 2')
            ->assertSee('Paged Board 24')
            ->assertDontSee('Test Workspace')
            ->assertSee(route('operator.dashboard', ['tab' => 'boards', 'sort' => 'newest', 'empty' => 1, 'page' => 2]));

        // The oldest board, unreachable before without searching for it.
        $this->withSession($session)->get(route('operator.dashboard', ['tab' => 'boards', 'sort' => 'newest', 'empty' => 1, 'page' => 2]))
            ->assertOk()
            ->assertSee('21–25 of 25', false)
            ->assertSee('Test Workspace')
            ->assertDontSee('Paged Board 24');

        // A search pages too, and its links keep the search.
        $this->withSession($session)->get(route('operator.dashboard', ['tab' => 'boards', 'q' => 'Paged']))
            ->assertSee('1–20 of 24 boards matching', false)
            ->assertSee(route('operator.dashboard', ['tab' => 'boards', 'q' => 'Paged', 'page' => 2]));
    }

    public function test_boards_sort_by_most_visited_by_default_then_newest_recently_opened_and_largest(): void
    {
        $session = ['operator_fp' => hash('sha256', 'op-secret')];
        [$visited] = Workspace::provision('Visited Board');
        [$opened] = Workspace::provision('Opened Board');
        [$large] = Workspace::provision('Large Board');
        [$newest] = Workspace::provision('Newest Board');
        $newest->forceFill(['created_at' => now()->addMinute()])->saveQuietly();

        DB::table('visit_tallies')->insert([
            'day' => now()->toDateString(), 'workspace_id' => $visited->id, 'country' => '', 'source' => 'direct', 'arrivals' => 9,
        ]);
        $opened->forceFill(['last_accessed_at' => now()->addMinutes(5)])->saveQuietly();
        $this->actingInWorkspace($large);
        Course::create(['code' => 'BIG 1', 'title' => 'Big', 'slug' => 'big-1'])
            ->materials()->create(['section' => 'notes', 'original_filename' => 'big.pdf',
                'stored_path' => 'materials/big.pdf', 'download_token' => 'dl-big', 'file_size' => 900_000_000]);

        $first = fn (?string $sort) => $this->withSession($session)
            ->get(route('operator.dashboard', array_filter(['tab' => 'boards', 'sort' => $sort, 'empty' => 1])))
            ->assertOk()->viewData('recent')->first()->name;

        $this->assertSame('Visited Board', $first(null));
        $this->assertSame('Newest Board', $first('newest'));
        $this->assertSame('Opened Board', $first('opened'));
        $this->assertSame('Large Board', $first('largest'));
        $this->assertSame('Visited Board', $first('nonsense'));

        $this->withSession($session)->get(route('operator.dashboard', ['tab' => 'boards', 'empty' => 1]))
            ->assertSee('9 visits')
            ->assertSee('most visited first (30 days)');
    }

    public function test_the_active_tile_counts_boards_classmates_opened_this_week(): void
    {
        $session = ['operator_fp' => hash('sha256', 'op-secret')];
        [$byOwner] = Workspace::provision('Owner Only');
        [$byClass] = Workspace::provision('Class Opened');
        $byOwner->forceFill(['last_accessed_at' => now()])->saveQuietly();
        DB::table('board_visit_days')->insert([
            ['workspace_id' => $byClass->id, 'day' => now()->toDateString()],
            ['workspace_id' => $byClass->id, 'day' => now()->subDays(2)->toDateString()],
            ['workspace_id' => $byOwner->id, 'day' => now()->subDays(10)->toDateString()],
        ]);

        $stats = $this->withSession($session)->get(route('operator.dashboard'))->assertOk()->viewData('stats');

        $this->assertSame(1, $stats['active_week']);
        $this->assertTrue($stats['tracking_started']);
    }

    public function test_a_board_with_files_but_no_visitors_says_so_and_is_faded(): void
    {
        $session = ['operator_fp' => hash('sha256', 'op-secret')];
        [$shared] = Workspace::provision('Shared Board');
        [$unshared] = Workspace::provision('Unshared Board');
        foreach ([$shared, $unshared] as $ws) {
            $this->actingInWorkspace($ws);
            Course::create(['code' => 'C 1', 'title' => 'C', 'slug' => 'c-1'])
                ->materials()->create(['section' => 'notes', 'original_filename' => 'a.pdf',
                    'stored_path' => 'materials/'.$ws->slug.'.pdf', 'download_token' => 'dl-'.$ws->slug, 'file_size' => 1]);
        }
        DB::table('visit_tallies')->insert([
            'day' => now()->toDateString(), 'workspace_id' => $shared->id, 'country' => '', 'source' => 'direct', 'arrivals' => 4,
        ]);

        $html = $this->withSession($session)->get(route('operator.dashboard', ['tab' => 'boards']))->assertOk()->getContent();

        $sharedRow = substr($html, strpos($html, 'Shared Board') - 500, 1500);
        $unsharedRow = substr($html, strpos($html, 'Unshared Board') - 500, 1500);
        $this->assertStringContainsString('4 visits', $sharedRow);
        $this->assertStringNotContainsString('op-faded', $sharedRow);
        $this->assertStringContainsString('no visitors in 30 days', $unsharedRow);
        $this->assertStringContainsString('op-faded', $unsharedRow);
        // Shared first, however new the unshared one is.
        $this->assertLessThan(strpos($html, 'Unshared Board'), strpos($html, 'Shared Board'));
    }

    public function test_a_board_row_says_who_is_behind_it(): void
    {
        $session = ['operator_fp' => hash('sha256', 'op-secret')];
        [$ws] = Workspace::provision('Grade 5 English');
        $ws->forceFill([
            'created_at' => now()->subDays(5), 'uploads_owner_only' => true, 'referrer' => 'kwame',
            'recovery_email' => 'teacher@example.com',
        ])->saveQuietly();
        $this->actingInWorkspace($ws);
        $course = Course::create(['code' => 'ENG 5', 'title' => 'English', 'slug' => 'eng-5']);
        $course->materials()->create(['section' => 'notes', 'original_filename' => 'a.pdf', 'stored_path' => 'materials/a.pdf',
            'download_token' => 'dl-a', 'file_size' => 1])->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();
        DB::table('visit_tallies')->insert([
            ['day' => now()->toDateString(), 'workspace_id' => $ws->id, 'country' => 'SA', 'source' => 'direct', 'arrivals' => 30],
            ['day' => now()->toDateString(), 'workspace_id' => $ws->id, 'country' => 'GH', 'source' => 'direct', 'arrivals' => 2],
            ['day' => now()->subDays(40)->toDateString(), 'workspace_id' => $ws->id, 'country' => 'NG', 'source' => 'direct', 'arrivals' => 99],
        ]);
        Suggestion::create(['message' => 'Thanks!', 'page' => '/'.$ws->slug]);
        Suggestion::create(['message' => 'Annotate?', 'page' => '/'.$ws->slug.'/c/eng-5']);
        Suggestion::create(['message' => 'Elsewhere', 'page' => '/'.$ws->slug.'-other']);

        $html = $this->withSession($session)->get(route('operator.dashboard', ['tab' => 'boards']))->assertOk()->getContent();
        $start = strpos($html, 'Grade 5 English') - 500;
        $row = substr($html, $start, strpos($html, '<details', $start) - $start);

        $this->assertStringContainsString('made 5 days ago', $row);
        $this->assertStringContainsString('last file 2 days ago', $row);
        $this->assertStringContainsString('Saudi Arabia', $row);
        $this->assertStringNotContainsString('Nigeria', $row);
        $this->assertStringContainsString('owner uploads only', $row);
        $this->assertStringContainsString('via kwame', $row);
        $this->assertStringContainsString('2 suggestions', $row);
        $this->assertStringContainsString('can be emailed', $row);
        $this->assertStringNotContainsString('teacher@example.com', $html);
    }

    public function test_a_board_row_shows_its_share_of_the_storage_limit(): void
    {
        config(['noteshare.workspace_storage_bytes' => 10 * 1_048_576]);
        $rowFor = function (string $name, float $megabytes, string $slug) {
            [$ws] = Workspace::provision($name);
            $this->actingInWorkspace($ws);
            Course::create(['code' => 'X', 'title' => 'X', 'slug' => $slug])->materials()->create([
                'section' => 'notes', 'original_filename' => 'a.pdf', 'stored_path' => "materials/{$slug}.pdf",
                'download_token' => "dl-{$slug}", 'file_size' => (int) ($megabytes * 1_048_576),
            ]);

            return $name;
        };
        $rowFor('Roomy Board', 5, 'roomy');
        $rowFor('Nearly Full Board', 8.5, 'nearly');
        $rowFor('Full Board', 10, 'full');

        $html = $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->get(route('operator.dashboard', ['tab' => 'boards']))->assertOk()->getContent();
        $row = function (string $name) use ($html) {
            $start = strpos($html, $name) - 500;

            return substr($html, $start, strpos($html, '<details', $start) - $start);
        };

        $this->assertStringContainsString('5.0 MB · 50%', $row('Roomy Board'));
        $this->assertStringNotContainsString('text-amber-600', $row('Roomy Board'));
        $this->assertStringContainsString('8.5 MB · 85%', $row('Nearly Full Board'));
        $this->assertStringContainsString('text-amber-600', $row('Nearly Full Board'));
        $this->assertStringContainsString('10.0 MB · 100%', $row('Full Board'));
        $this->assertStringContainsString('text-danger', $row('Full Board'));
    }

    public function test_a_note_returns_to_the_same_list_and_reopens_on_an_error(): void
    {
        $session = ['operator_fp' => hash('sha256', 'op-secret')];
        $from = route('operator.dashboard', ['tab' => 'boards', 'q' => 'Test', 'sort' => 'largest', 'page' => 2]);

        $this->withSession($session)->withHeader('referer', $from)
            ->post(route('operator.boards.note', $this->workspace->id), ['note' => 'Hello'])
            ->assertRedirect($from);
        $this->assertSame('Hello', $this->workspace->fresh()->operator_note);

        $this->withSession($session)->withHeader('referer', $from)
            ->post(route('operator.boards.note', $this->workspace->id), ['note' => str_repeat('x', 501)])
            ->assertRedirect($from)
            ->assertSessionHasErrors('note')
            ->assertSessionHas('noteFor', $this->workspace->id);

        // A Referer elsewhere still lands on the boards tab, never off-site.
        $this->withSession($session)->withHeader('referer', 'https://evil.example/?tab=boards')
            ->post(route('operator.boards.note', $this->workspace->id), ['note' => 'Hi'])
            ->assertRedirect(route('operator.dashboard', ['tab' => 'boards']));
    }

    public function test_the_operator_deletes_a_board_and_its_files_from_the_list(): void
    {
        $session = ['operator_fp' => hash('sha256', 'op-secret')];
        $from = route('operator.dashboard', ['tab' => 'boards', 'sort' => 'largest']);
        $path = $this->material->stored_path;
        [$other] = Workspace::provision('Keep Me');

        $this->withSession($session)->withHeader('referer', $from)
            ->post(route('operator.boards.delete', $this->workspace->id), ['secret' => 'op-secret'])
            ->assertRedirect($from)
            ->assertSessionHas('done', 'Deleted “Test Workspace” and its 1 file.');

        $this->assertNull(Workspace::find($this->workspace->id));
        $this->assertSame(0, Course::withoutGlobalScopes()->where('workspace_id', $this->workspace->id)->count());
        $this->assertNull(Material::find($this->material->id));
        Storage::disk('local')->assertMissing($path);
        $this->assertNotNull(Workspace::find($other->id));
    }

    public function test_deleting_a_board_needs_the_operator_secret(): void
    {
        $session = ['operator_fp' => hash('sha256', 'op-secret')];
        $from = route('operator.dashboard', ['tab' => 'boards']);

        foreach ([[], ['secret' => 'wrong']] as $input) {
            $this->withSession($session)->withHeader('referer', $from)
                ->post(route('operator.boards.delete', $this->workspace->id), $input)
                ->assertRedirect($from)
                ->assertSessionHasErrors('deleteSecret')
                ->assertSessionHas('deleteFor', $this->workspace->id);
        }

        $this->assertNotNull(Workspace::find($this->workspace->id));
        $this->assertNotNull(Material::find($this->material->id));
    }

    public function test_an_archived_board_is_closed_and_comes_back_whole(): void
    {
        $session = ['operator_fp' => hash('sha256', 'op-secret')];
        $slug = $this->workspace->slug;
        $download = '/download/'.$this->material->download_token;
        Storage::disk('local')->put($this->material->stored_path, 'pdf');

        $this->withSession($session)->post(route('operator.boards.archive', $this->workspace->id))->assertRedirect();

        // Everyone else, including the owner: closed.
        $this->flushSession();
        $this->get('/'.$slug)->assertStatus(410);
        $this->get('/'.$slug.'/c/math-251')->assertStatus(410);
        $this->get($download)->assertStatus(410);
        Storage::disk('local')->assertExists($this->material->stored_path);

        $boards = $this->withSession($session)->get(route('operator.dashboard', ['tab' => 'boards']))->assertOk();
        $boards->assertDontSee('Test Workspace</a>', false);
        $this->get(route('operator.dashboard', ['tab' => 'boards', 'sort' => 'archived']))
            ->assertOk()->assertSee('Test Workspace')->assertSee('Unarchive board');
        $this->get(route('operator.dashboard', ['tab' => 'boards', 'q' => 'Test Workspace']))
            ->assertOk()->assertSee('Archived');

        // The operator can still look, but not change anything.
        $this->withSession($session)->get('/'.$slug)->assertOk();
        $this->withSession($session)->get($download)->assertOk();
        $this->withSession($session)->post(route('courses.store', ['workspace' => $slug]), ['code' => 'X', 'title' => 'X'])->assertStatus(410);

        $this->withSession($session)->post(route('operator.boards.unarchive', $this->workspace->id))->assertRedirect();
        $this->flushSession();
        $this->get('/'.$slug)->assertOk();
        $this->get($download)->assertOk();
    }

    public function test_the_archived_page_is_slipnotes_own(): void
    {
        $this->workspace->forceFill(['archived_at' => now()])->saveQuietly();

        $this->get('/'.$this->workspace->slug)->assertStatus(410)
            ->assertSee('This board is archived')->assertSee('/logo-mark.png', false)->assertDontSee('Illuminate');
    }

    public function test_cleanup_leaves_an_empty_archived_board_alone(): void
    {
        [$ws] = Workspace::provision('Quiet Archive');
        $ws->forceFill(['archived_at' => now(), 'created_at' => now()->subYear(), 'last_accessed_at' => now()->subYear()])->saveQuietly();

        Workspace::pruneAbandoned();

        $this->assertNotNull(Workspace::find($ws->id));
    }

    public function test_only_the_operator_can_archive_a_board(): void
    {
        $this->post(route('operator.boards.archive', $this->workspace->id))->assertForbidden();
        $this->assertNull($this->workspace->fresh()->archived_at);
    }

    public function test_only_the_operator_can_delete_a_board(): void
    {
        $this->post(route('operator.boards.delete', $this->workspace->id))->assertForbidden();
        $this->assertNotNull(Workspace::find($this->workspace->id));
    }

    public function test_at_a_glance_shows_the_activation_rate_and_empty_boards_say_so(): void
    {
        // setUp's workspace holds one file; this one holds nothing at all.
        Workspace::provision('Made And Left');

        $session = ['operator_fp' => hash('sha256', 'op-secret')];

        // "At a glance" lives on the landing tab only.
        $this->withSession($session)
            ->get(route('operator.dashboard'))
            ->assertOk()
            ->assertSee('With files', false)
            ->assertSee('of 2 boards', false);

        // Empty boards are hidden from the list by default, one tap away.
        $this->withSession($session)
            ->get(route('operator.dashboard', ['tab' => 'boards']))
            ->assertOk()
            ->assertDontSee('Made And Left')
            ->assertSee('Show 1 empty board');

        $this->withSession($session)
            ->get(route('operator.dashboard', ['tab' => 'boards', 'empty' => 1]))
            ->assertOk()
            ->assertSee('Made And Left')
            ->assertSee('Nothing added yet', false)
            ->assertSee('Hide empty boards')
            ->assertDontSee('0 courses', false)
            ->assertDontSee('With files', false);

        // A search finds any board, empty or not.
        $this->withSession($session)
            ->get(route('operator.dashboard', ['tab' => 'boards', 'q' => 'Made And']))
            ->assertSee('Made And Left');
    }

    public function test_rotating_the_operator_secret_invalidates_existing_sessions(): void
    {
        // Authenticated against the old secret.
        $this->post(route('operator.login'), ['secret' => 'op-secret'])
            ->assertRedirect(route('operator.dashboard'));

        // Operator rotates the secret in the environment.
        config(['noteshare.operator_secret' => 'rotated-secret']);

        // The old session no longer authenticates — back to the login form.
        $this->get(route('operator.dashboard'))
            ->assertOk()
            ->assertSee('Operator secret', false);
    }

    public function test_operator_can_remove_a_reported_file(): void
    {
        $path = $this->material->stored_path;

        $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->post(route('operator.remove', $this->material->id))
            ->assertRedirect(route('operator.dashboard'));

        $this->assertSame(0, Material::count());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_operator_can_dismiss_reports_without_deleting_the_file(): void
    {
        $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->post(route('operator.dismiss', $this->material->id))
            ->assertRedirect(route('operator.dashboard'));

        $this->assertSame(1, Material::count());          // file stays
        $this->assertSame(0, $this->material->reports()->count()); // reports cleared
    }

    private function reportedFile(string $name, string $reason, ?Course $course = null): Material
    {
        $course ??= $this->material->course;
        $material = $course->materials()->create(['section' => 'notes', 'title' => $name, 'original_filename' => "{$name}.pdf",
            'stored_path' => "materials/{$name}.pdf", 'download_token' => "dl-{$name}"]);
        $material->reports()->create(['reason' => $reason]);

        return $material;
    }

    public function test_reports_group_by_course_with_serious_ones_first_and_apart(): void
    {
        $session = ['operator_fp' => hash('sha256', 'op-secret')];
        $this->material->reports()->delete();
        $this->material->reports()->create(['reason' => 'Not my notes / wrong file']);
        $this->reportedFile('photo-two', 'Not my notes / wrong file');
        $this->reportedFile('nasty', 'Inappropriate or offensive');

        $html = $this->withSession($session)->get(route('operator.dashboard'))->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'photo-two'), strpos($html, 'nasty'));
        $this->assertSame(1, substr_count($html, 'Dismiss all 2?'));
        // The reason both files share is said once, in the group's header.
        $this->assertSame(1, substr_count($html, 'Not my notes / wrong file'));
    }

    public function test_dismiss_all_clears_a_batch_and_can_be_undone(): void
    {
        $session = ['operator_fp' => hash('sha256', 'op-secret')];
        $one = $this->reportedFile('photo-one', 'Not my notes / wrong file');
        $two = $this->reportedFile('photo-two', 'Not my notes / wrong file');

        $this->withSession($session)->post(route('operator.dismiss-many'), ['ids' => [$one->id, $two->id]])
            ->assertRedirect(route('operator.dashboard'));
        $this->assertSame(0, $one->reports()->count() + $two->reports()->count());
        $this->assertSame(2, Material::whereIn('id', [$one->id, $two->id])->count());

        $this->post(route('operator.undo'))->assertRedirect(route('operator.dashboard'));
        $this->assertSame(1, $one->reports()->count());
        $this->assertSame(1, $two->reports()->count());
    }

    public function test_dismiss_all_never_clears_a_serious_report(): void
    {
        $session = ['operator_fp' => hash('sha256', 'op-secret')];
        $wrong = $this->reportedFile('photo-one', 'Not my notes / wrong file');
        $serious = $this->reportedFile('pirated', 'Copyright — shouldn\'t be shared');

        $this->withSession($session)->post(route('operator.dismiss-many'), ['ids' => [$wrong->id, $serious->id]]);

        $this->assertSame(0, $wrong->reports()->count());
        $this->assertSame(1, $serious->reports()->count());
    }

    public function test_dismiss_all_needs_an_operator_session(): void
    {
        $this->post(route('operator.dismiss-many'), ['ids' => [$this->material->id]])->assertForbidden();
        $this->assertSame(1, $this->material->reports()->count());
    }

    public function test_a_removed_file_can_stay_uploadable(): void
    {
        $path = $this->material->stored_path;
        $id = $this->material->id;

        $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->post(route('operator.remove', $id), ['allow_reupload' => 1])
            ->assertRedirect(route('operator.dashboard'));

        $this->assertSame(0, Material::count());
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseCount('blocked_uploads', 0);

        $this->post(route('operator.undo'));
        $this->assertSame($id, Material::first()->id);
        Storage::disk('local')->assertExists($path);
    }

    public function test_remove_is_blocked_without_an_operator_session(): void
    {
        $this->post(route('operator.remove', $this->material->id))->assertForbidden();
        $this->assertSame(1, Material::count());
    }

    public function test_dismiss_can_be_undone(): void
    {
        $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->post(route('operator.dismiss', $this->material->id))
            ->assertRedirect(route('operator.dashboard'));

        $this->assertSame(0, $this->material->reports()->count());

        $this->post(route('operator.undo'))
            ->assertRedirect(route('operator.dashboard'));

        $this->assertSame(1, $this->material->reports()->count());
        $this->assertSame('spam', $this->material->reports()->first()->reason);
    }

    public function test_remove_can_be_undone(): void
    {
        $path = $this->material->stored_path;
        $id = $this->material->id;

        $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->post(route('operator.remove', $id))
            ->assertRedirect(route('operator.dashboard'));

        $this->assertSame(0, Material::count());
        Storage::disk('local')->assertMissing($path);

        $this->post(route('operator.undo'))
            ->assertRedirect(route('operator.dashboard'));

        $this->assertSame(1, Material::count());
        $this->assertSame($id, Material::first()->id);
        $this->assertSame(1, Material::first()->reports()->count());
        Storage::disk('local')->assertExists($path);
        $this->assertDatabaseMissing('blocked_uploads', [
            'content_hash' => hash('sha256', Storage::disk('local')->get($path)),
        ]);
    }

    public function test_the_dashboard_flags_settings_that_hurt_boards(): void
    {
        config(['mail.default' => 'log', 'noteshare.telegram_bot_token' => null]);
        $this->post(route('operator.login'), ['secret' => 'op-secret']);

        $this->withHeader('X-Forwarded-For', '203.0.113.9')
            ->get(route('operator.dashboard'))
            ->assertOk()
            ->assertSee('Fix in .env')
            ->assertSee('MAIL_MAILER=log')
            ->assertSee('reports only reach the log')
            ->assertSee('TRUSTED_PROXIES doesn&#039;t list', false);
    }

    public function test_a_well_set_up_deployment_shows_no_setup_warnings(): void
    {
        config([
            'mail.default' => 'smtp',
            'noteshare.telegram_bot_token' => 'bot',
            'noteshare.telegram_chat_id' => '1',
        ]);
        $this->post(route('operator.login'), ['secret' => 'op-secret']);

        $this->get(route('operator.dashboard'))->assertOk()->assertDontSee('Fix in .env');
    }

    public function test_the_dashboard_says_when_the_last_telegram_alert_failed(): void
    {
        config([
            'mail.default' => 'smtp',
            'noteshare.telegram_bot_token' => 'bot',
            'noteshare.telegram_chat_id' => '1',
        ]);
        Http::fake(['api.telegram.org/*' => Http::sequence()->push('Unauthorized', 401)->push(['ok' => true])]);
        app(TelegramNotifier::class)->notifyReport($this->material, 'spam');

        $this->post(route('operator.login'), ['secret' => 'op-secret']);
        $this->get(route('operator.dashboard'))->assertSee('The last Telegram alert failed');

        app(TelegramNotifier::class)->notifyReport($this->material, 'spam');
        $this->get(route('operator.dashboard'))->assertDontSee('Fix in .env');
    }

    public function test_a_telegram_test_message_clears_the_failure_warning(): void
    {
        config(['mail.default' => 'smtp', 'noteshare.telegram_bot_token' => 'bot', 'noteshare.telegram_chat_id' => '1']);
        Http::fake(['api.telegram.org/*' => Http::sequence()->push('Unauthorized', 401)->push(['ok' => true])]);
        app(TelegramNotifier::class)->notifyReport($this->material, 'spam');
        $this->post(route('operator.login'), ['secret' => 'op-secret']);

        $this->get(route('operator.dashboard'))->assertSee('Send a Telegram test message');

        $this->post(route('operator.telegram-test'))
            ->assertRedirect(route('operator.dashboard'))
            ->assertSessionHas('done', 'Test message sent. Telegram alerts are working.');
        $this->get(route('operator.dashboard'))->assertDontSee('Fix in .env');
    }

    public function test_the_telegram_test_needs_the_operator_session(): void
    {
        Http::fake();
        $this->post(route('operator.telegram-test'))->assertNotFound();
        Http::assertNothingSent();
    }
}
