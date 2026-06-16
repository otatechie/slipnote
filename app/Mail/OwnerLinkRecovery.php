<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent ONLY to a workspace's stored recovery email, after a successful
 * recovery request. Carries the freshly-rotated owner link (the old one is
 * already dead). Simple HTML with a clickable button, plus a plain-text
 * fallback — minimal, image-free, clear sender, since university inboxes
 * filter aggressively and this must not look like spam.
 */
class OwnerLinkRecovery extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $workspaceName,
        public string $ownerUrl,
    ) {}

    public function build(): self
    {
        return $this
            ->subject("Your new SlipNote owner access for {$this->workspaceName}")
            ->view('emails.owner-link-recovery')          // HTML (clickable button)
            ->text('emails.owner-link-recovery-text');     // plain-text fallback
    }
}
