<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
            ->assertSee('MATH 251')   // board context for the reported file
            ->assertSee('spam');      // the report reason
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

    public function test_remove_is_blocked_without_an_operator_session(): void
    {
        $this->post(route('operator.remove', $this->material->id))->assertForbidden();
        $this->assertSame(1, Material::count());
    }
}
