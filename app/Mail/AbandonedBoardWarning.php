<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** To an empty board's recovery email, a week before the board is removed. */
class AbandonedBoardWarning extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $workspaceName,
        public string $boardUrl,
        public int $days,
    ) {}

    public function build(): self
    {
        return $this
            ->subject("Your SlipNote board “{$this->workspaceName}” will be removed in {$this->days} days")
            ->view('emails.abandoned-board-warning')
            ->text('emails.abandoned-board-warning-text');
    }
}
