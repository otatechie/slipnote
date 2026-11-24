<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** A school asking about SlipNote, copied to the support inbox so it's seen the same day. */
class SchoolEnquiry extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array  $enquiry  Suggestion::schoolEnquiry(): school, from, classes, ref, message
     * @param  string  $contact  the phone number or email they left
     */
    public function __construct(
        public array $enquiry,
        public string $contact,
        public string $dashboardUrl,
    ) {}

    public function build(): self
    {
        $mail = $this
            // One line, whatever was typed into the form.
            ->subject('School enquiry: '.trim(preg_replace('/\s+/u', ' ', $this->enquiry['school'])))
            ->view('emails.school-enquiry')
            ->text('emails.school-enquiry-text');

        // Reply answers the school straight away when they left an email.
        if (filter_var($this->contact, FILTER_VALIDATE_EMAIL)) {
            $mail->replyTo($this->contact);
        }

        return $mail;
    }
}
