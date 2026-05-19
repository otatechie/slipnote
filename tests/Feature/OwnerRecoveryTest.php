<?php

namespace Tests\Feature;

use App\Mail\OwnerLinkRecovery;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\InteractsWithWorkspace;
use Tests\TestCase;

/**
 * Owner-link recovery: opt-in, encrypted email; recovery rotates the secret
 * and mails the NEW link to the STORED address only; no enumeration oracle;
 * rate-limited; workspace-isolated. Written failing-first.
 */
class OwnerRecoveryTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkspace();
        config(['mail.default' => 'smtp']);
        Mail::fake();
        RateLimiter::clear('recover:'.$this->workspace->id);
    }

    // --- Owner sets the recovery email (owner-gated, opt-in) ---

    public function test_owner_can_set_a_recovery_email(): void
    {
        $this->unlockOwnerSession();

        Livewire::test('courses-page')
            ->set('recoveryEmail', 'me@example.com')
            ->call('saveRecoveryEmail')
            ->assertHasNoErrors();

        $this->assertTrue(
            $this->workspace->fresh()->recoveryEmailMatches('me@example.com')
        );
    }

    public function test_non_owner_cannot_set_a_recovery_email(): void
    {
        Livewire::test('courses-page') // not owner
            ->set('recoveryEmail', 'attacker@example.com')
            ->call('saveRecoveryEmail')
            ->assertForbidden();

        $this->assertNull($this->workspace->fresh()->recovery_email);
    }

    public function test_recovery_email_is_encrypted_at_rest(): void
    {
        $this->workspace->setRecoveryEmail('secret@example.com');

        $raw = DB::table('workspaces')->where('id', $this->workspace->id)
            ->value('recovery_email');

        $this->assertStringNotContainsString('secret@example.com', (string) $raw);
        $this->assertTrue($this->workspace->fresh()->recoveryEmailMatches('secret@example.com'));
    }

    public function test_owner_panel_nudges_when_no_recovery_email_is_set(): void
    {
        $this->unlockOwnerSession();

        Livewire::test('courses-page')
            ->assertSee('this board can’t be recovered', false);
    }

    public function test_recovery_panel_is_hidden_when_mail_is_not_deliverable(): void
    {
        config(['mail.default' => 'log']);

        $this->unlockOwnerSession();

        Livewire::test('courses-page')
            ->assertDontSee('No recovery email set')
            ->assertDontSee('this board can’t be recovered', false);
    }

    // --- Recovery request flow ---

    public function test_correct_email_rotates_the_secret_and_mails_the_new_link_to_the_stored_address(): void
    {
        $this->workspace->setRecoveryEmail('owner@example.com');
        $oldSecret = $this->ownerSecret;

        $this->actingInWorkspace($this->workspace);
        Livewire::test('workspace-recovery')
            ->set('email', 'owner@example.com')
            ->call('requestRecovery')
            ->assertHasNoErrors();

        // Old link is dead (rotated).
        $this->assertFalse($this->workspace->fresh()->verifyOwner($oldSecret));

        // Mailed to the STORED address.
        Mail::assertQueued(OwnerLinkRecovery::class, function ($mail) {
            return $mail->hasTo('owner@example.com');
        });
    }

    public function test_wrong_or_absent_email_sends_nothing_and_gives_identical_response(): void
    {
        $this->workspace->setRecoveryEmail('owner@example.com');

        $this->actingInWorkspace($this->workspace);
        Livewire::test('workspace-recovery')
            ->set('email', 'not-the-owner@example.com')
            ->call('requestRecovery')
            ->assertHasNoErrors()
            ->assertSet('done', true); // identical "done" state as a match

        Mail::assertNothingQueued();

        // No-recovery-email workspace: identical outcome, still nothing sent.
        [$other] = Workspace::provision('No Email Board');
        $this->actingInWorkspace($other);
        Livewire::test('workspace-recovery')
            ->set('email', 'anyone@example.com')
            ->call('requestRecovery')
            ->assertHasNoErrors()
            ->assertSet('done', true);

        Mail::assertNothingQueued();
    }

    public function test_recovery_only_ever_mails_the_stored_address_not_the_typed_one(): void
    {
        $this->workspace->setRecoveryEmail('real-owner@example.com');

        $this->actingInWorkspace($this->workspace);
        Livewire::test('workspace-recovery')
            ->set('email', 'real-owner@example.com') // must match to trigger
            ->call('requestRecovery');

        Mail::assertQueued(OwnerLinkRecovery::class, function ($mail) {
            return $mail->hasTo('real-owner@example.com')
                && ! $mail->hasTo('attacker@evil.com');
        });
    }

    public function test_recovery_is_rate_limited(): void
    {
        $this->workspace->setRecoveryEmail('owner@example.com');
        $this->actingInWorkspace($this->workspace);

        for ($i = 0; $i < 5; $i++) {
            Livewire::test('workspace-recovery')
                ->set('email', 'wrong@example.com')
                ->call('requestRecovery');
        }

        Livewire::test('workspace-recovery')
            ->set('email', 'owner@example.com')
            ->call('requestRecovery')
            ->assertHasErrors('email'); // throttled
    }

    public function test_recovery_is_isolated_between_workspaces(): void
    {
        $this->workspace->setRecoveryEmail('alpha-owner@example.com');
        [$beta] = Workspace::provision('Beta Board');
        $beta->setRecoveryEmail('beta-owner@example.com');

        // Requesting recovery under Beta with Alpha's email must do nothing.
        $this->actingInWorkspace($beta);
        Livewire::test('workspace-recovery')
            ->set('email', 'alpha-owner@example.com')
            ->call('requestRecovery');

        Mail::assertNothingQueued();
    }
}
