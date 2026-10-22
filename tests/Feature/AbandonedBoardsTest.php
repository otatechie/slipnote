<?php

namespace Tests\Feature;

use App\Mail\AbandonedBoardWarning;
use App\Models\Course;
use App\Models\Material;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Boards with no files that nobody opens for 30 days are removed; owners with
 * a recovery email are warned a week before; opening the board resets both.
 */
class AbandonedBoardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        config(['mail.default' => 'smtp']);
    }

    private function board(string $name, int $idleDays, bool $withFile = false, ?string $email = null): Workspace
    {
        [$ws] = Workspace::provision($name);
        $ws->forceFill(['created_at' => now()->subDays($idleDays + 1), 'last_accessed_at' => now()->subDays($idleDays)])->saveQuietly();
        if ($email) {
            $ws->setRecoveryEmail($email);
        }
        if ($withFile) {
            app(\App\Tenancy\Tenancy::class)->set($ws);
            $course = Course::create(['code' => 'X 1', 'title' => 'X', 'slug' => 'x-1']);
            Storage::disk('local')->put('materials/keep.pdf', 'x');
            $course->materials()->create(['section' => 'notes', 'original_filename' => 'keep.pdf',
                'stored_path' => 'materials/keep.pdf', 'download_token' => 'dl-'.$name, 'file_size' => 1]);
        }

        return $ws->fresh();
    }

    public function test_an_empty_board_unopened_for_30_days_is_removed(): void
    {
        $dead = $this->board('Dead', 31);
        $recent = $this->board('Recent', 10);
        $withFile = $this->board('Has A File', 200, withFile: true);

        [$removed] = Workspace::pruneAbandoned();

        $this->assertSame(1, $removed);
        $this->assertNull(Workspace::find($dead->id));
        $this->assertNotNull(Workspace::find($recent->id));
        $this->assertNotNull(Workspace::find($withFile->id));
        Storage::disk('local')->assertExists('materials/keep.pdf');
    }

    public function test_an_owner_with_a_recovery_email_is_warned_and_given_a_week(): void
    {
        $board = $this->board('Warn Me', 31, email: 'owner@example.com');

        // First pass: warned, not removed, even though 30 days have passed.
        [$removed, $warned] = Workspace::pruneAbandoned();
        $this->assertSame([0, 1], [$removed, $warned]);
        Mail::assertSent(AbandonedBoardWarning::class, fn ($m) => $m->hasTo('owner@example.com'));
        $this->assertNotNull($board->fresh()->abandon_warned_at);

        // Six days later: still there. A week later: removed.
        $this->travel(6)->days();
        Workspace::pruneAbandoned();
        $this->assertNotNull(Workspace::find($board->id));

        $this->travel(2)->days();
        Workspace::pruneAbandoned();
        $this->assertNull(Workspace::find($board->id));
        Mail::assertSent(AbandonedBoardWarning::class, 1);
    }

    public function test_opening_the_board_cancels_the_warning_and_resets_the_clock(): void
    {
        $board = $this->board('Saved', 31, email: 'owner@example.com');
        Workspace::pruneAbandoned();

        $this->withHeader('User-Agent', 'Mozilla/5.0')->get('/'.$board->slug)->assertOk();
        $this->assertNull($board->fresh()->abandon_warned_at);

        $this->travel(8)->days();
        Workspace::pruneAbandoned();
        $this->assertNotNull(Workspace::find($board->id));
    }

    public function test_a_link_preview_bot_does_not_count_as_opening_a_board(): void
    {
        $board = $this->board('Previewed', 31);

        $this->withHeader('User-Agent', 'WhatsApp/2.23.20.0')->get('/'.$board->slug)->assertOk();
        $this->assertTrue($board->fresh()->last_accessed_at->lt(now()->subDays(30)));

        Workspace::pruneAbandoned();
        $this->assertNull(Workspace::find($board->id));
    }

    public function test_the_operator_sees_when_an_empty_board_will_be_removed(): void
    {
        config(['noteshare.operator_secret' => 'op-secret']);
        $this->board('Fading', 20);

        $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->get(route('operator.dashboard', ['tab' => 'boards', 'empty' => 1]))
            ->assertSee('removed in 10 days if still empty');
    }

    public function test_the_daily_run_happens_once_a_day(): void
    {
        $this->board('Gone Soon', 31);
        Workspace::pruneAbandonedDaily();
        $this->assertSame(0, Workspace::count());

        $this->board('Gone Tomorrow', 31);
        Workspace::pruneAbandonedDaily();
        $this->assertSame(1, Workspace::count());

        $this->artisan('boards:prune-abandoned')->assertSuccessful();
        $this->assertSame(0, Workspace::count());
        $this->assertSame(0, Material::count());
    }
}
