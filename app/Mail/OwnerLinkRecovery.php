<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent ONLY to a workspace's stored recovery email, after a recovery
 * request. Carries a signed, single-use, one-hour link; visiting it is what
 * rotates the owner secret, so until then the existing owner link still
 * works and an unrequested email changes nothing. Simple HTML with a
 * clickable button, plus a plain-text fallback — minimal, image-free, clear
 * sender, since university inboxes filter aggressively.
 */
class OwnerLinkRecovery extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $workspaceName,
        public string $restoreUrl,
    ) {}

    public function build(): self
    {
        return $this
            ->subject("Restore owner access to {$this->workspaceName} on SlipNote")
            ->view('emails.owner-link-recovery')          // HTML (clickable button)
            ->text('emails.owner-link-recovery-text');     // plain-text fallback
    }
}
