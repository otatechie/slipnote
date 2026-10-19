<?php

namespace Tests\Feature;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\DB;
use Tests\InteractsWithWorkspace;
use Tests\TestCase;

/**
 * Arrival counts and the feedback prompt: the two ways the operator learns
 * who uses SlipNote and why, without accounts and without tracking anyone.
 */
class VisitTallyTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/128 Mobile Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkspace();
        Course::create(['code' => 'CS 101', 'title' => 'Intro', 'slug' => 'cs-101']);
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        parent::tearDown();
    }

    private function visit(string $url, array $headers = [], string $ip = '127.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders(['User-Agent' => self::BROWSER] + $headers)
            ->get($url);
    }

    private function tallies()
    {
        return DB::table('visit_tallies')->get();
    }

    // --- Counting ---

    public function test_an_arrival_on_a_board_is_counted_against_that_board_and_source(): void
    {
        $this->visit(route('courses.index', $this->wsParams()), ['Referer' => 'https://www.google.com.sa/'])->assertOk();

        $row = $this->tallies()->sole();
        $this->assertSame($this->workspace->id, (int) $row->workspace_id);
        $this->assertSame('google', $row->source);
        $this->assertSame(1, (int) $row->arrivals);
        $this->assertSame(now()->toDateString(), $row->day);
    }

    public function test_repeat_arrivals_add_to_one_row_not_many(): void
    {
        foreach (range(1, 3) as $_) {
            $this->visit(route('courses.index', $this->wsParams()));
        }

        $row = $this->tallies()->sole();
        $this->assertSame('direct', $row->source);
        $this->assertSame(3, (int) $row->arrivals);
    }

    public function test_moving_around_inside_the_site_is_not_an_arrival(): void
    {
        $this->visit(route('course.show', $this->wsParams(['slug' => 'cs-101'])), [
            'Referer' => route('courses.index', $this->wsParams()),
        ])->assertOk();

        $this->assertCount(0, $this->tallies());
    }

    public function test_link_preview_fetchers_and_crawlers_are_not_counted(): void
    {
        foreach (['WhatsApp/2.23.20.0 A', 'Googlebot/2.1', 'facebookexternalhit/1.1', ''] as $agent) {
            $this->withHeaders(['User-Agent' => $agent])->get(route('courses.index', $this->wsParams()));
        }

        $this->assertCount(0, $this->tallies());
    }

    public function test_the_landing_page_counts_as_not_on_a_board(): void
    {
        $this->visit(route('welcome'), ['Referer' => 'https://news.ycombinator.com/item?id=1'])->assertOk();

        $row = $this->tallies()->sole();
        $this->assertSame(0, (int) $row->workspace_id);
        $this->assertSame('news.ycombinator.com', $row->source);
    }

    public function test_an_ambassador_link_is_its_own_source(): void
    {
        $this->visit(route('welcome', ['ref' => 'knust-kwame']));

        $this->assertSame('ambassador', $this->tallies()->sole()->source);
    }

    public function test_nothing_personal_is_stored(): void
    {
        $this->visit(route('courses.index', $this->wsParams()), [], '203.0.113.9');

        $this->assertSame(
            ['id', 'day', 'workspace_id', 'country', 'source', 'arrivals'],
            array_keys((array) $this->tallies()->sole()),
        );
    }

    // --- Country ---

    public function test_country_is_read_from_cloudflare_only_through_a_trusted_proxy(): void
    {
        TrustProxies::at(['10.0.0.1']);

        $this->visit(route('courses.index', $this->wsParams()), ['CF-IPCountry' => 'SA'], '10.0.0.1');

        $this->assertSame('SA', $this->tallies()->sole()->country);
    }

    public function test_a_country_header_from_an_untrusted_client_is_ignored(): void
    {
        $this->visit(route('courses.index', $this->wsParams()), ['CF-IPCountry' => 'SA'], '198.51.100.7');

        $this->assertSame('', $this->tallies()->sole()->country);
    }

    public function test_unknown_and_tor_countries_are_stored_as_unknown(): void
    {
        TrustProxies::at(['10.0.0.1']);

        $this->visit(route('courses.index', $this->wsParams()), ['CF-IPCountry' => 'T1'], '10.0.0.1');

        $this->assertSame('', $this->tallies()->sole()->country);
    }

    // --- Operator view ---

    public function test_the_visitors_tab_shows_countries_sources_and_boards(): void
    {
        config(['noteshare.operator_secret' => 'op-secret']);
        DB::table('visit_tallies')->insert([
            ['day' => now()->toDateString(), 'workspace_id' => $this->workspace->id, 'country' => 'SA', 'source' => 'direct', 'arrivals' => 7],
            ['day' => now()->toDateString(), 'workspace_id' => 0, 'country' => 'GH', 'source' => 'google', 'arrivals' => 2],
            // Outside the window: never shown.
            ['day' => now()->subDays(40)->toDateString(), 'workspace_id' => 0, 'country' => 'NG', 'source' => 'old-site.example', 'arrivals' => 99],
        ]);

        $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->get(route('operator.dashboard', ['tab' => 'visitors']))
            ->assertOk()
            ->assertSee('9 arrivals')
            ->assertSee('Saudi Arabia')
            ->assertSee('Ghana')
            ->assertSee('google')
            ->assertSee('Test Workspace')
            ->assertDontSee('old-site.example');
    }

    public function test_the_visitors_tab_explains_an_empty_count(): void
    {
        config(['noteshare.operator_secret' => 'op-secret']);

        $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->get(route('operator.dashboard', ['tab' => 'visitors']))
            ->assertOk()
            ->assertSee('No arrivals counted yet');
    }
}
