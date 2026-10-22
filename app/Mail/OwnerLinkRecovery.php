<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

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
