<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent ONLY to a workspace's stored recovery email, after a successful
 * recovery request. Carries the freshly-rotated owner link (the old one is
 * already dead). Plain text, minimal, clear sender — university inboxes
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
            ->subject("Your owner link for “{$this->workspaceName}” on SlipNote")
            ->text('emails.owner-link-recovery');
    }
}
