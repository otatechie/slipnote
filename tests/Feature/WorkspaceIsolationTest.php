<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Workspace;
use App\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The security crux of multi-tenancy: workspace A must NEVER read, reach,
 * or mutate workspace B's data. Written before the feature is wired up so
 * these start red and prove the isolation, not just assert it after.
 */
class WorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $a;

    private Workspace $b;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->a] = Workspace::provision('Alpha Program');
        [$this->b] = Workspace::provision('Beta Program');
    }

    /** Run a closure with a given workspace as the resolved tenant. */
    private function asWorkspace(Workspace $w, \Closure $fn)
    {
        app(Tenancy::class)->set($w);

        return $fn();
    }

    public function test_provision_returns_a_one_time_plaintext_secret_and_stores_only_a_hash(): void
    {
        [$ws, $secret] = Workspace::provision('Gamma');

        $this->assertNotEmpty($secret);
        $this->assertNotSame($secret, $ws->owner_secret_hash);
        $this->assertTrue($ws->verifyOwner($secret));
        $this->assertFalse($ws->verifyOwner('wrong'));
        // The plaintext is never persisted.
        $this->assertNull(Workspace::find($ws->id)->getAttribute('owner_secret'));
    }

    public function test_a_query_only_returns_the_current_workspaces_courses(): void
    {
        $this->asWorkspace($this->a, fn () => Course::create([
            'code' => 'AAA 100', 'title' => 'Alpha course', 'slug' => 'aaa-100',
        ]));
        $this->asWorkspace($this->b, fn () => Course::create([
            'code' => 'BBB 200', 'title' => 'Beta course', 'slug' => 'bbb-200',
        ]));

        $aCourses = $this->asWorkspace($this->a, fn () => Course::pluck('code')->all());
        $bCourses = $this->asWorkspace($this->b, fn () => Course::pluck('code')->all());

        $this->assertSame(['AAA 100'], $aCourses);
        $this->assertSame(['BBB 200'], $bCourses);
    }

    public function test_create_auto_assigns_the_current_workspace_and_ignores_injected_id(): void
    {
        $course = $this->asWorkspace($this->a, fn () => Course::create([
            'code' => 'AAA 100', 'title' => 'x', 'slug' => 'aaa-100',
            // Attempt to plant it in B via mass assignment:
            'workspace_id' => $this->b->id,
        ]));

        // $fillable excludes workspace_id, so the trait wins: it belongs to A.
        $this->assertSame($this->a->id, $course->fresh()->workspace_id);
    }

    public function test_two_workspaces_may_each_have_the_same_course_slug(): void
    {
        $this->asWorkspace($this->a, fn () => Course::create([
            'code' => 'PHYS 201', 'title' => 'a', 'slug' => 'phys-201',
        ]));

        // Same slug in B must be allowed (composite unique, not global).
        $courseB = $this->asWorkspace($this->b, fn () => Course::create([
            'code' => 'PHYS 201', 'title' => 'b', 'slug' => 'phys-201',
        ]));

        $this->assertSame('phys-201', $courseB->slug);
        $this->assertSame($this->b->id, $courseB->workspace_id);
    }

    public function test_the_course_list_page_only_shows_the_url_workspaces_courses(): void
    {
        $this->asWorkspace($this->a, fn () => Course::create([
            'code' => 'ALPHA 1', 'title' => 'Alpha only', 'slug' => 'alpha-1',
        ]));
        $this->asWorkspace($this->b, fn () => Course::create([
            'code' => 'BETA 1', 'title' => 'Beta only', 'slug' => 'beta-1',
        ]));

        $this->get("/{$this->a->slug}")
            ->assertOk()
            ->assertSee('ALPHA 1')
            ->assertDontSee('BETA 1');
    }

    public function test_a_course_page_404s_for_a_slug_that_lives_in_another_workspace(): void
    {
        $this->asWorkspace($this->b, fn () => Course::create([
            'code' => 'BETA 1', 'title' => 'Beta only', 'slug' => 'beta-1',
        ]));

        // Slug exists, but only in B — must not be reachable under A.
        $this->get("/{$this->a->slug}/c/beta-1")->assertNotFound();
    }

    public function test_an_unknown_workspace_slug_404s(): void
    {
        $this->get('/no-such-workspace')->assertNotFound();
    }

    public function test_owner_secret_for_one_workspace_does_not_unlock_another(): void
    {
        [$wsA, $secretA] = Workspace::provision('Owner A');
        [$wsB] = Workspace::provision('Owner B');

        $this->assertTrue($wsA->verifyOwner($secretA));
        $this->assertFalse($wsB->verifyOwner($secretA));
        $this->assertNotSame($wsA->ownerSessionKey(), $wsB->ownerSessionKey());
        $this->assertNotSame($wsA->uploadUnlockKey(), $wsB->uploadUnlockKey());
    }
}
