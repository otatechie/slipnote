<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * A form that fails comes back to its page with a message, not Inertia's
 * raw error pop-up.
 */
class FormErrorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware('web')->post('/_test/expired', fn () => abort(419));
        Route::middleware('web')->post('/_test/broken', fn () => throw new RuntimeException('boom'));
        Route::middleware('web')->post('/_test/c/x/upload', fn () => abort(419));
        Route::middleware('web')->get('/_test/broken', fn () => throw new RuntimeException('boom'));
        config(['app.debug' => false]);
    }

    private function inertiaPost(string $uri)
    {
        return $this->withHeaders(['X-Inertia' => 'true', 'Referer' => url('/somewhere')])->post($uri);
    }

    public function test_an_expired_page_goes_back_with_a_notice(): void
    {
        $this->inertiaPost('/_test/expired')
            ->assertRedirect(url('/somewhere'))
            ->assertSessionHas('notice', 'This page was open a long time, so that didn’t go through. Please try again.');
    }

    public function test_an_upload_from_an_expired_page_shows_on_the_upload_form(): void
    {
        $this->inertiaPost('/_test/c/x/upload')
            ->assertRedirect(url('/somewhere'))
            ->assertSessionHasErrors('files');
    }

    public function test_a_server_error_goes_back_with_a_notice(): void
    {
        $this->inertiaPost('/_test/broken')
            ->assertRedirect(url('/somewhere'))
            ->assertSessionHas('notice', 'Something went wrong on our side. Please try again.');
    }

    public function test_with_debug_on_the_real_error_still_shows(): void
    {
        config(['app.debug' => true]);

        $this->inertiaPost('/_test/broken')->assertStatus(500);
    }

    public function test_pages_and_non_inertia_requests_are_left_alone(): void
    {
        $version = app(HandleInertiaRequests::class)->version(request());
        $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version])
            ->get('/_test/broken')->assertStatus(500);
        $this->flushHeaders()->post('/_test/expired')->assertStatus(419);
    }
}
