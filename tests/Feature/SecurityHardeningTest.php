<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Material;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Tests\InteractsWithWorkspace;
use Tests\TestCase;

/**
 * Regressions for the audit findings. Each of these was a reproducible gap:
 * the test names say what a stranger could do before the fix.
 */
class SecurityHardeningTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkspace();
        Storage::fake('local');
        $this->course = Course::create(['code' => 'CS 101', 'title' => 'Intro', 'slug' => 'cs-101']);
    }

    private function makeMaterial(): Material
    {
        Storage::disk('local')->put('materials/x.pdf', '%PDF-1.4 test');

        return $this->course->materials()->create([
            'section' => 'notes',
            'original_filename' => 'notes.pdf',
            'stored_path' => 'materials/x.pdf',
            'manage_token' => 'manage-'.str_repeat('m', 33),
            'download_token' => 'download-'.str_repeat('d', 31),
            'file_size' => 13,
        ]);
    }

    // --- 1. A visitor could delete any file using the token the page gave them ---

    public function test_the_page_never_hands_a_visitor_the_delete_capability(): void
    {
        $material = $this->makeMaterial();
        $this->flushSession();

        $row = $this->get(route('course.show', $this->wsParams(['slug' => 'cs-101'])))
            ->assertOk()
            ->viewData('page')['props']['materials'][0];

        $this->assertArrayNotHasKey('manage_url', $row);
        $this->assertStringNotContainsString($material->manage_token, json_encode($row));
        $this->assertStringContainsString($material->download_token, $row['download_url']);
    }

    public function test_the_download_token_cannot_delete_the_file(): void
    {
        $material = $this->makeMaterial();
        $this->flushSession();

        $this->delete("/materials/{$material->id}/{$material->download_token}")->assertForbidden();

        $this->assertDatabaseHas('materials', ['id' => $material->id]);
        Storage::disk('local')->assertExists('materials/x.pdf');
    }

    public function test_the_manage_token_is_not_a_download_address(): void
    {
        $material = $this->makeMaterial();

        $this->get('/download/'.$material->manage_token)->assertNotFound();
        $this->get('/download/'.$material->download_token)->assertOk();
    }

    public function test_uploads_get_both_tokens_and_the_receipt_carries_only_the_manage_one(): void
    {
        $response = $this->post(route('course.upload', $this->wsParams(['slug' => 'cs-101'])), [
            'section' => 'notes',
            'files' => [\Illuminate\Http\UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
        ]);

        $material = Material::firstOrFail();
        $this->assertNotEmpty($material->manage_token);
        $this->assertNotEmpty($material->download_token);
        $this->assertNotSame($material->manage_token, $material->download_token);

        $receipt = $response->getSession()->get('manageUrl');
        $this->assertStringContainsString($material->manage_token, $receipt);
        $this->assertStringNotContainsString($material->download_token, $receipt);
    }

    // --- 2. X-Forwarded-For was believed from anyone ---

    public function test_a_client_cannot_choose_its_own_ip_via_x_forwarded_for(): void
    {
        $material = $this->makeMaterial();
        $url = route('material.report', $this->wsParams(['slug' => 'cs-101', 'material' => $material->id]));

        $this->withHeaders(['X-Forwarded-For' => '203.0.113.9'])->post($url, ['reason' => 'spam']);

        $this->assertNotSame('203.0.113.9', Report::first()->reporter_ip);
    }

    public function test_changing_x_forwarded_for_does_not_reset_an_ip_rate_limit(): void
    {
        $material = $this->makeMaterial();
        $url = route('material.report', $this->wsParams(['slug' => 'cs-101', 'material' => $material->id]));

        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders(['X-Forwarded-For' => '198.51.100.1'])->post($url, ['reason' => 'x']);
        }
        $count = Report::count();

        $this->withHeaders(['X-Forwarded-For' => '198.51.100.2'])->post($url, ['reason' => 'x']);

        $this->assertSame($count, Report::count(), 'a new header value must not buy a fresh quota');
    }

    // --- 3. ?owner= on a GET ran bcrypt with nothing counting failures ---

    public function test_owner_link_guesses_on_get_are_rate_limited_and_share_the_form_budget(): void
    {
        Hash::spy();
        $url = route('courses.index', $this->wsParams());

        for ($i = 0; $i < 8; $i++) {
            $this->get($url.'?owner=wrong-'.$i)->assertOk();
        }

        // Five checked, three refused before reaching bcrypt.
        Hash::shouldHaveReceived('check')->times(5);
        $this->assertSame(5, RateLimiter::attempts('unlock_owner:'.$this->workspace->id));

        // The real secret is refused through either door until the window clears.
        $this->get($url.'?owner='.$this->ownerSecret);
        $this->assertNotSame(true, session($this->workspace->ownerSessionKey()));
        $this->post(route('courses.unlock', $this->wsParams()), ['ownerInput' => $this->ownerSecret])
            ->assertSessionHasErrors('ownerInput');
    }

    public function test_a_correct_owner_link_still_unlocks_and_clears_the_counter(): void
    {
        $url = route('courses.index', $this->wsParams());
        $this->get($url.'?owner=wrong');

        $this->get($url.'?owner='.$this->ownerSecret)->assertRedirect($url);

        $this->assertSame(true, session($this->workspace->ownerSessionKey()));
        $this->assertSame(0, RateLimiter::attempts('unlock_owner:'.$this->workspace->id));
    }

    // --- 5. Section ZIPs were built in full on disk with no throttle ---

    public function test_section_zips_are_rate_limited_per_client_and_board(): void
    {
        $this->makeMaterial();
        $url = route('course.download-section', $this->wsParams(['slug' => 'cs-101', 'section' => 'notes']));

        for ($i = 0; $i < 10; $i++) {
            $this->get($url)->assertOk();
        }

        $this->get($url)->assertStatus(429);
    }

    // --- 6. The production CSP blocked the app's own inline theme script ---

    public function test_production_csp_carries_a_nonce_that_the_inline_theme_script_uses(): void
    {
        $this->app['env'] = 'production';

        $response = $this->get('/')->assertOk();
        $nonce = Vite::cspNonce();
        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertNotEmpty($nonce);
        $this->assertStringContainsString("script-src 'self' 'nonce-{$nonce}'", $csp);
        // Inline styles are allowed (style="" attributes); inline scripts are not.
        preg_match('/script-src ([^;]+)/', $csp, $scriptSrc);
        $this->assertStringNotContainsString('unsafe-inline', $scriptSrc[1]);
        $this->assertStringNotContainsString('bunny.net', $csp);
        $this->assertStringContainsString('<script nonce="'.$nonce.'">', $response->getContent());
    }

    // --- 8. Operator logout left the session id alive ---

    public function test_operator_logout_invalidates_the_session(): void
    {
        config(['noteshare.operator_secret' => 'op-secret']);

        $this->post(route('operator.login'), ['secret' => 'op-secret']);
        $before = session()->getId();
        $this->assertNotNull(session('operator_fp'));

        $this->post(route('operator.logout'))->assertRedirect(route('operator.dashboard'));

        $this->assertNull(session('operator_fp'));
        $this->assertNotSame($before, session()->getId());
    }
}
