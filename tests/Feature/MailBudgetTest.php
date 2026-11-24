<?php

namespace Tests\Feature;

use App\Mail\AbandonedBoardWarning;
use App\Models\Workspace;
use App\Support\MailBudget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Anyone can make a board and save any address as its recovery email, so the
 * site caps mail per address across boards, and in total, to keep a stranger's
 * inbox, and the sending mailbox's standing, safe from a flood.
 */
class MailBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'smtp']);
        Mail::fake();
    }

    /** @return Collection<int, Workspace> */
    private function boardsMailing(array $emails, int $idleDays = 0): Collection
    {
        return collect($emails)->values()->map(function (string $email, int $i) use ($idleDays) {
            [$ws] = Workspace::provision('Board '.($i + 1));
            $ws->setRecoveryEmail($email);
            if ($idleDays) {
                $ws->forceFill(['created_at' => now()->subDays($idleDays + 1), 'last_accessed_at' => now()->subDays($idleDays)])->saveQuietly();
            }

            return $ws->fresh();
        });
    }

    private function askForRecovery(Workspace $ws, string $email, string $ip = '127.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('workspace.recover.store', ['workspace' => $ws->slug]), ['email' => $email]);
    }

    /** Sends happen after the response and replay in tests, so count what the limiters let through. */
    private function linksSent(Collection $boards): int
    {
        return $boards->sum(fn (Workspace $ws) => RateLimiter::attempts('recovery_board:'.$ws->id));
    }

    public function test_one_address_gets_at_most_five_mails_a_day_across_every_board(): void
    {
        $boards = $this->boardsMailing(array_fill(0, 3, 'stranger@example.com'));

        // Three asks per board, each well inside that board's own five an hour.
        foreach ($boards as $ws) {
            for ($i = 0; $i < 3; $i++) {
                $this->askForRecovery($ws, 'stranger@example.com', '203.0.113.'.$i)->assertSessionHas('done', true);
            }
        }
        $this->assertSame(MailBudget::PER_ADDRESS_A_DAY, $this->linksSent($boards));

        // The next day the address can be mailed again.
        $this->travel(25)->hours();
        $this->askForRecovery($boards[0], 'stranger@example.com');
        $this->assertSame(1, RateLimiter::attempts('recovery_board:'.$boards[0]->id));
    }

    public function test_a_held_back_link_looks_the_same_to_whoever_asked(): void
    {
        config(['noteshare.mail_daily_limit' => 0]);
        [$ws] = $this->boardsMailing(['owner@example.com']);

        $this->askForRecovery($ws, 'owner@example.com')->assertRedirect()->assertSessionHas('done', true);
        $this->assertSame(0, RateLimiter::attempts('recovery_board:'.$ws->id));
    }

    public function test_the_site_wide_daily_limit_holds_mail_back_and_tells_the_operator(): void
    {
        config(['noteshare.mail_daily_limit' => 2, 'noteshare.operator_secret' => 'op-secret']);
        $boards = $this->boardsMailing(['a@example.com', 'b@example.com', 'c@example.com']);

        foreach ($boards as $ws) {
            $this->askForRecovery($ws, $ws->recovery_email);
        }
        $this->assertSame(2, $this->linksSent($boards));
        $this->assertSame(0, RateLimiter::attempts('recovery_board:'.$boards[2]->id));

        $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->get(route('operator.dashboard'))
            ->assertOk()
            ->assertSee('The daily mail limit (2) was reached')
            ->assertSee('MAIL_DAILY_LIMIT');
    }

    public function test_the_operator_is_not_warned_while_mail_is_under_the_limit(): void
    {
        config(['noteshare.operator_secret' => 'op-secret']);
        [$ws] = $this->boardsMailing(['owner@example.com']);
        $this->askForRecovery($ws, 'owner@example.com');

        $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->get(route('operator.dashboard'))
            ->assertOk()
            ->assertDontSee('The daily mail limit');
    }

    public function test_an_empty_board_over_the_limit_waits_for_its_warning_and_is_never_removed_unwarned(): void
    {
        config(['noteshare.mail_daily_limit' => 1]);
        [$first, $second] = $this->boardsMailing(['a@example.com', 'b@example.com'], idleDays: 40);

        $this->assertSame([0, 1], Workspace::pruneAbandoned());
        Mail::assertSent(AbandonedBoardWarning::class, 1);
        $waiting = $first->fresh()->abandon_warned_at ? $second : $first;
        $this->assertNull($waiting->fresh()->abandon_warned_at);

        // Long past removal age, but unwarned: it's still there the next day, and warned then.
        $this->travel(25)->hours();
        $this->assertSame([0, 1], Workspace::pruneAbandoned());
        $this->assertNotNull($waiting->fresh()->abandon_warned_at);
        Mail::assertSent(AbandonedBoardWarning::class, 2);
    }

    public function test_an_address_is_capped_across_board_warnings_too(): void
    {
        $boards = $this->boardsMailing(array_fill(0, MailBudget::PER_ADDRESS_A_DAY + 2, 'stranger@example.com'), idleDays: 40);

        [, $warned] = Workspace::pruneAbandoned();

        $this->assertSame(MailBudget::PER_ADDRESS_A_DAY, $warned);
        Mail::assertSent(AbandonedBoardWarning::class, MailBudget::PER_ADDRESS_A_DAY);
        $this->assertSame($boards->count(), Workspace::whereIn('id', $boards->pluck('id'))->count());
    }
}
