<?php

namespace Tests\Feature;

use App\Mail\OwnerLinkRecovery;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\InteractsWithWorkspace;
use Tests\TestCase;

/**
 * Owner-link recovery: opt-in, encrypted email; recovery rotates the secret
 * and mails the NEW link to the STORED address only; no enumeration oracle;
 * rate-limited; workspace-isolated.
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
        RateLimiter::clear('recovery:'.$this->workspace->id);
    }

    // --- Owner sets the recovery email (owner-gated, opt-in) ---

    public function test_owner_can_set_a_recovery_email(): void
    {
        $this->unlockOwnerSession();

        $this->post(route('courses.recovery-email', $this->wsParams()), [
            'recoveryEmail' => 'me@example.com',
        ])->assertRedirect();

        $this->assertTrue(
            $this->workspace->fresh()->recoveryEmailMatches('me@example.com')
        );
    }

    public function test_non_owner_cannot_set_a_recovery_email(): void
    {
        // Not owner
        $this->post(route('courses.recovery-email', $this->wsParams()), [
            'recoveryEmail' => 'attacker@example.com',
        ])->assertForbidden();

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

    public function test_owner_panel_shows_needs_recovery_email_prop_when_none_set(): void
    {
        $this->unlockOwnerSession();

        $this->get(route('courses.index', $this->wsParams()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('isOwner', true)
                ->where('needsRecoveryEmail', true)
            );
    }

    public function test_owner_panel_exposes_the_current_recovery_email_when_set(): void
    {
        $this->workspace->setRecoveryEmail('owner@example.com');
        $this->unlockOwnerSession();

        $this->get(route('courses.index', $this->wsParams()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('isOwner', true)
                ->where('needsRecoveryEmail', false)
                ->where('currentRecoveryEmail', 'owner@example.com')
            );
    }

    public function test_recovery_panel_is_hidden_when_mail_is_not_deliverable(): void
    {
        config(['mail.default' => 'log']);

        $this->unlockOwnerSession();

        $this->get(route('courses.index', $this->wsParams()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('recoveryAvailable', false)
            );
    }

    // --- Recovery request flow ---

    public function test_correct_email_mails_a_restore_link_without_touching_the_current_owner_link(): void
    {
        $this->workspace->setRecoveryEmail('owner@example.com');
        $oldSecret = $this->ownerSecret;

        $this->post(route('workspace.recover.store', $this->wsParams()), [
            'email' => 'owner@example.com',
        ])->assertRedirect();

        // Asking changes nothing: anyone who knows the recovery address could
        // ask, and that must not revoke the owner's saved link.
        $this->assertTrue($this->workspace->fresh()->verifyOwner($oldSecret));

        // Mailed to the STORED address.
        Mail::assertSent(OwnerLinkRecovery::class, function ($mail) {
            return $mail->hasTo('owner@example.com');
        });
    }

    private function requestRestoreLink(string $email = 'owner@example.com'): string
    {
        $this->workspace->setRecoveryEmail($email);
        $this->post(route('workspace.recover.store', $this->wsParams()), ['email' => $email]);

        $url = null;
        Mail::assertSent(OwnerLinkRecovery::class, function ($mail) use (&$url) {
            $url = $mail->restoreUrl;

            return true;
        });

        return $url;
    }

    public function test_redeeming_the_mailed_link_rotates_the_secret_and_unlocks_this_browser(): void
    {
        $oldSecret = $this->ownerSecret;
        $url = $this->requestRestoreLink();

        $this->get($url)->assertRedirect(route('start'));

        $fresh = $this->workspace->fresh();
        $this->assertFalse($fresh->verifyOwner($oldSecret), 'old link retired on redeem');
        $this->assertSame(true, session($this->workspace->ownerSessionKey()));

        // The new owner link is shown once, on the same screen a new board gets.
        parse_str((string) parse_url(session('ownerUrl'), PHP_URL_QUERY), $q);
        $this->assertTrue($fresh->verifyOwner($q['owner']));
        $this->assertTrue(session('recovered'));
    }

    public function test_the_restore_link_works_exactly_once(): void
    {
        $url = $this->requestRestoreLink();

        $this->get($url)->assertRedirect(route('start'));
        $this->get($url)
            ->assertRedirect(route('workspace.recover', $this->wsParams()))
            ->assertSessionHasErrors('email');
    }

    public function test_a_tampered_restore_link_is_rejected(): void
    {
        $url = $this->requestRestoreLink();

        $this->get(str_replace('signature=', 'signature=0', $url))->assertForbidden();
    }

    public function test_a_restore_link_for_one_board_cannot_redeem_another(): void
    {
        $url = $this->requestRestoreLink();
        [$beta] = Workspace::provision('Beta Board');

        // Same nonce, different board in the path: the signature no longer matches.
        $this->get(str_replace('/'.$this->workspace->slug.'/', '/'.$beta->slug.'/', $url))->assertForbidden();
        $this->assertTrue($this->workspace->fresh()->verifyOwner($this->ownerSecret));
    }

    public function test_wrong_or_absent_email_sends_nothing_and_gives_identical_response(): void
    {
        $this->workspace->setRecoveryEmail('owner@example.com');

        $this->post(route('workspace.recover.store', $this->wsParams()), [
            'email' => 'not-the-owner@example.com',
        ])->assertSessionHas('done', true);

        Mail::assertNothingSent();

        // No-recovery-email workspace: identical outcome.
        [$other] = Workspace::provision('No Email Board');
        $this->actingInWorkspace($other);
        $this->post(route('workspace.recover.store', ['workspace' => $other->slug]), [
            'email' => 'anyone@example.com',
        ])->assertSessionHas('done', true);

        Mail::assertNothingSent();
    }

    public function test_recovery_only_ever_mails_the_stored_address_not_the_typed_one(): void
    {
        $this->workspace->setRecoveryEmail('real-owner@example.com');

        $this->post(route('workspace.recover.store', $this->wsParams()), [
            'email' => 'real-owner@example.com',
        ]);

        Mail::assertSent(OwnerLinkRecovery::class, function ($mail) {
            return $mail->hasTo('real-owner@example.com')
                && ! $mail->hasTo('attacker@evil.com');
        });
    }

    public function test_recovery_is_rate_limited(): void
    {
        $this->workspace->setRecoveryEmail('owner@example.com');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('workspace.recover.store', $this->wsParams()), [
                'email' => 'wrong@example.com',
            ]);
        }

        // 6th request should be throttled — done=true but no mail
        $this->post(route('workspace.recover.store', $this->wsParams()), [
            'email' => 'owner@example.com',
        ])->assertSessionHas('done', true);

        Mail::assertNothingSent();
    }

    public function test_recovery_is_isolated_between_workspaces(): void
    {
        $this->workspace->setRecoveryEmail('alpha-owner@example.com');
        [$beta] = Workspace::provision('Beta Board');
        $beta->setRecoveryEmail('beta-owner@example.com');

        // Requesting recovery under Beta with Alpha's email must do nothing.
        $this->actingInWorkspace($beta);
        $this->post(route('workspace.recover.store', ['workspace' => $beta->slug]), [
            'email' => 'alpha-owner@example.com',
        ]);

        Mail::assertNothingSent();
    }

    public function test_mail_failure_does_not_retire_the_existing_owner_link(): void
    {
        $this->workspace->setRecoveryEmail('owner@example.com');
        $oldSecret = $this->ownerSecret;

        Mail::shouldReceive('to->send')
            ->once()
            ->andThrow(new RuntimeException('smtp failed'));

        $this->post(route('workspace.recover.store', $this->wsParams()), [
            'email' => 'owner@example.com',
        ])->assertSessionHas('done', true);

        $this->assertTrue($this->workspace->fresh()->verifyOwner($oldSecret));
    }
}
