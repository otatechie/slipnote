<?php

namespace Tests\Feature;

use App\Models\Ambassador;
use App\Models\Course;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ?ref= attribution and the operator's ambassadors tab. No accounts anywhere:
 * a ref is a cookie until a board is created, then a column on that board.
 */
class AmbassadorProgramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['noteshare.operator_secret' => 'op-secret']);
    }

    private function asOperator(): static
    {
        $this->post(route('operator.login'), ['secret' => 'op-secret']);

        return $this;
    }

    // --- The cookie ---

    public function test_a_valid_ref_on_any_page_is_kept_in_a_cookie(): void
    {
        $this->get('/?ref=Kwame')->assertOk()->assertCookie('slipnote_ref', 'kwame');
        $this->get('/start?ref=knust-ama')->assertOk()->assertCookie('slipnote_ref', 'knust-ama');
    }

    public function test_a_malformed_ref_is_ignored(): void
    {
        $this->get('/?ref=<script>')->assertOk()->assertCookieMissing('slipnote_ref');
        $this->get('/?ref='.str_repeat('a', 41))->assertOk()->assertCookieMissing('slipnote_ref');
        $this->get('/?ref[]=x')->assertOk()->assertCookieMissing('slipnote_ref');
    }

    public function test_first_touch_wins_so_ambassadors_cannot_poach_each_other(): void
    {
        $this->withCookie('slipnote_ref', 'kwame')
            ->get('/?ref=yaw')
            ->assertOk()
            ->assertCookieMissing('slipnote_ref'); // no new cookie set; the old one stands
    }

    // --- Attribution at creation ---

    public function test_creating_a_board_with_a_ref_cookie_records_the_referrer_and_clears_the_cookie(): void
    {
        $this->withCookie('slipnote_ref', 'kwame')
            ->post(route('workspaces.store'), ['name' => 'Physics Level 200'])
            ->assertRedirect()
            ->assertCookieExpired('slipnote_ref');

        $this->assertSame('kwame', Workspace::firstOrFail()->referrer);
    }

    public function test_a_board_created_without_a_ref_has_no_referrer(): void
    {
        $this->post(route('workspaces.store'), ['name' => 'Physics Level 200']);

        $this->assertNull(Workspace::firstOrFail()->referrer);
    }

    public function test_referrer_cannot_be_set_through_the_form(): void
    {
        $this->post(route('workspaces.store'), ['name' => 'Physics', 'referrer' => 'kwame']);

        $this->assertNull(Workspace::firstOrFail()->referrer);
    }

    // --- The operator tab ---

    public function test_operator_can_add_an_ambassador_and_the_slug_is_normalised(): void
    {
        $this->asOperator()
            ->post(route('operator.ambassadors.store'), [
                'name' => 'Kwame Mensah', 'slug' => '  KNUST-Kwame ', 'campus' => 'KNUST',
                'phone' => '0244000000', 'network' => 'mtn',
            ])
            ->assertRedirect(route('operator.dashboard', ['tab' => 'ambassadors']));

        $amb = Ambassador::firstOrFail();
        $this->assertSame('knust-kwame', $amb->slug);
        $this->assertSame('0244000000', $amb->phone);
        $this->assertStringContainsString('?ref=knust-kwame', $amb->link());
        $this->assertStringContainsString($amb->link(), $amb->inviteMessage());
    }

    public function test_phone_is_encrypted_at_rest(): void
    {
        $this->asOperator()->post(route('operator.ambassadors.store'), [
            'name' => 'Kwame', 'slug' => 'kwame', 'phone' => '0244000000',
        ]);

        $raw = \DB::table('ambassadors')->value('phone');
        $this->assertNotSame('0244000000', $raw);
    }

    public function test_slugs_are_unique_and_a_bad_slug_is_rejected(): void
    {
        Ambassador::create(['name' => 'Kwame', 'slug' => 'kwame']);

        $this->asOperator()->post(route('operator.ambassadors.store'), ['name' => 'Other', 'slug' => 'kwame'])
            ->assertSessionHasErrors('slug');
        $this->asOperator()->post(route('operator.ambassadors.store'), ['name' => 'Other', 'slug' => 'not ok!'])
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Ambassador::count());
    }

    public function test_only_the_operator_can_add_or_retire(): void
    {
        $amb = Ambassador::create(['name' => 'Kwame', 'slug' => 'kwame']);

        $this->post(route('operator.ambassadors.store'), ['name' => 'X', 'slug' => 'x'])->assertForbidden();
        $this->post(route('operator.ambassadors.retire', $amb))->assertForbidden();

        $this->assertSame(1, Ambassador::count());
        $this->assertNull($amb->fresh()->retired_at);
    }

    public function test_retiring_keeps_the_history_and_the_slug(): void
    {
        $amb = Ambassador::create(['name' => 'Kwame', 'slug' => 'kwame']);
        [$ws] = Workspace::provision('Old Board');
        $ws->forceFill(['referrer' => 'kwame'])->save();

        $this->asOperator()->post(route('operator.ambassadors.retire', $amb))->assertRedirect();

        $this->assertNotNull($amb->fresh()->retired_at);

        // Still listed, faded, with their numbers -- under Retired.
        $html = $this->asOperator()->get(route('operator.dashboard', ['tab' => 'ambassadors']))->getContent();
        $this->assertStringContainsString('data-ref="kwame" data-boards="1"', $html);
        $this->assertStringContainsString('Retired', $html);
        $this->asOperator()->post(route('operator.ambassadors.store'), ['name' => 'New', 'slug' => 'kwame'])
            ->assertSessionHasErrors('slug');
    }

    private function seedBoard(Workspace $ws, string $code): void
    {
        $course = $ws->courses()->create(['code' => $code, 'title' => 'T', 'slug' => Str::slug($code), 'workspace_id' => $ws->id]);
        $course->materials()->create(['section' => 'notes', 'original_filename' => 'a.pdf',
            'stored_path' => 'x/'.Str::slug($code).'.pdf', 'download_token' => 'dl-'.Str::slug($code), 'file_size' => 1]);
    }

    public function test_used_this_month_is_a_thirty_day_window(): void
    {
        Ambassador::create(['name' => 'Kwame', 'slug' => 'kwame']);
        [$recent] = Workspace::provision('Recent');
        [$stale] = Workspace::provision('Stale');
        $recent->forceFill(['referrer' => 'kwame'])->save();
        $stale->forceFill(['referrer' => 'kwame'])->save();
        $this->seedBoard($recent, 'AAA 101');
        $this->seedBoard($stale, 'BBB 101');
        $this->visitedOn($recent, 20, 21, 22);
        $this->visitedOn($stale, 40, 41, 42);

        $html = $this->asOperator()->get(route('operator.dashboard', ['tab' => 'ambassadors']))->getContent();

        // Visited 20 days ago counts for this month's payout; 40 days ago does not.
        $this->assertStringContainsString('data-ref="kwame" data-boards="2" data-seeded="2" data-active="1"', $html);
    }

    private function visitedOn(Workspace $ws, int ...$daysAgo): void
    {
        foreach ($daysAgo as $ago) {
            \DB::table('board_visit_days')->insert(['workspace_id' => $ws->id, 'day' => now()->subDays($ago)->toDateString()]);
        }
    }

    private function ambassadorsTab(): string
    {
        return $this->asOperator()->get(route('operator.dashboard', ['tab' => 'ambassadors']))->getContent();
    }

    public function test_a_board_visited_on_too_few_days_is_not_used(): void
    {
        Ambassador::create(['name' => 'Kwame', 'slug' => 'kwame']);
        [$ws] = Workspace::provision('Twice');
        $ws->forceFill(['referrer' => 'kwame'])->save();
        $this->seedBoard($ws, 'PHY 101');
        $this->visitedOn($ws, 0, 1);

        $this->assertStringContainsString('data-ref="kwame" data-boards="1" data-seeded="1" data-active="0"', $this->ambassadorsTab());

        $this->visitedOn($ws, 2);

        $this->assertStringContainsString('data-ref="kwame" data-boards="1" data-seeded="1" data-active="1"', $this->ambassadorsTab());
    }

    public function test_the_owners_own_visits_never_make_a_board_used(): void
    {
        Ambassador::create(['name' => 'Kwame', 'slug' => 'kwame']);
        [$ws] = Workspace::provision('Opened By Its Owner');
        $ws->forceFill(['referrer' => 'kwame'])->save();
        $this->seedBoard($ws, 'PHY 101');

        // The ambassador made the board, so holds the owner link, and opens it daily.
        session([$ws->ownerSessionKey() => $ws->ownerSessionValue()]);
        foreach (range(1, 3) as $_) {
            $this->get('/'.$ws->slug)->assertOk();
            $this->travel(1)->days();
        }

        $this->assertNotNull($ws->fresh()->last_accessed_at); // still kept from removal
        $this->assertStringContainsString('data-ref="kwame" data-boards="1" data-seeded="1" data-active="0"', $this->ambassadorsTab());

        // Classmates, without the owner link.
        $this->flushSession();
        foreach (range(1, 3) as $_) {
            $this->get('/'.$ws->slug)->assertOk();
            $this->travel(1)->days();
        }

        $this->assertStringContainsString('data-ref="kwame" data-boards="1" data-seeded="1" data-active="1"', $this->ambassadorsTab());
    }

    public function test_the_reward_currency_is_configurable(): void
    {
        config(['noteshare.ambassador_reward' => 500, 'noteshare.ambassador_currency' => 'NGN']);
        Ambassador::create(['name' => 'Chidi', 'slug' => 'unilag-chidi']);
        [$ws] = Workspace::provision('Live Board');
        $ws->forceFill(['referrer' => 'unilag-chidi'])->save();
        $this->seedBoard($ws, 'CSC 101');
        $this->visitedOn($ws, 0, 1, 2);

        $html = $this->asOperator()->get(route('operator.dashboard', ['tab' => 'ambassadors']))->getContent();

        $this->assertStringContainsString('NGN 500 due', $html);
        $this->assertStringContainsString('Due this month: NGN 500.', $html);
        $this->assertStringNotContainsString('GHS', $html);
    }

    public function test_an_empty_board_is_never_counted_as_used_however_often_it_is_opened(): void
    {
        Ambassador::create(['name' => 'Kwame', 'slug' => 'kwame']);
        [$empty] = Workspace::provision('Opened But Empty');
        $empty->forceFill(['referrer' => 'kwame'])->save();
        $this->visitedOn($empty, 0, 1, 2);

        $html = $this->asOperator()->get(route('operator.dashboard', ['tab' => 'ambassadors']))->getContent();

        // Otherwise an ambassador could make boards and open them for the reward.
        $this->assertStringContainsString('data-ref="kwame" data-boards="1" data-seeded="0" data-active="0" data-due="0"', $html);
        // No amount shown either -- data-due="0" is the only "due" on the row.
        $this->assertStringNotContainsString('GHS', substr($html, strpos($html, 'data-ref="kwame"'), 800));
    }

    public function test_the_referrals_table_counts_boards_seeded_and_active_and_flags_unknown_refs(): void
    {
        Ambassador::create(['name' => 'Kwame Mensah', 'slug' => 'kwame']);

        [$live] = Workspace::provision('Live Board');
        [$emptyBoard] = Workspace::provision('Empty Board');
        [$stranger] = Workspace::provision('Guessed');
        $live->forceFill(['referrer' => 'kwame'])->save();
        $this->visitedOn($live, 0, 1, 2);
        $emptyBoard->forceFill(['referrer' => 'kwame'])->save();
        $stranger->forceFill(['referrer' => 'nobody'])->save();

        $course = $live->courses()->create(['code' => 'PHY 101', 'title' => 'Physics', 'slug' => 'phy-101', 'workspace_id' => $live->id]);
        $course->materials()->create(['section' => 'notes', 'original_filename' => 'a.pdf', 'stored_path' => 'x/a.pdf', 'download_token' => 'dl-a', 'file_size' => 1]);

        $html = $this->asOperator()->get(route('operator.dashboard', ['tab' => 'ambassadors']))->assertOk()->getContent();

        config(['noteshare.ambassador_reward' => 12, 'noteshare.ambassador_currency' => 'GHS']);
        $html = $this->asOperator()->get(route('operator.dashboard', ['tab' => 'ambassadors']))->assertOk()->getContent();

        $this->assertStringContainsString('data-ref="kwame" data-boards="2" data-seeded="1" data-active="1" data-due="12"', $html);
        $this->assertStringContainsString('data-ref="nobody" data-boards="1" data-seeded="0" data-active="0" data-due="0"', $html);
        $this->assertStringContainsString('GHS 12 due', $html);
        $this->assertStringContainsString('Due this month: GHS 12.', $html);
        $this->assertStringContainsString('unknown ref', $html);
        $this->assertStringContainsString('?ref=kwame', $html);
        // Live ambassadors first, unknown refs after them, each under its own heading.
        $this->assertLessThan(strpos($html, 'data-ref="nobody"'), strpos($html, 'data-ref="kwame"'));
        $this->assertStringContainsString('Refs nobody owns', $html);
    }

    // --- The footer link ---

    public function test_footer_links_to_the_application_form_only_when_configured(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Become a campus ambassador');

        config(['noteshare.ambassador_form_url' => 'https://forms.example/apply']);

        $this->get('/')->assertOk()
            ->assertSee('Become a campus ambassador')
            ->assertSee('https://forms.example/apply');
    }
}
