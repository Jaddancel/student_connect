<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent the moment a sign-up form is submitted: confirms the address and unlocks
 * the applicant's guest access while an admin reviews the request.
 */
class AccountRequestReceivedMail extends Mailable
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $confirmUrl,
        public readonly int $expiresInDays,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirm your email — StudentConnect sign-up received',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.account-request-received',
        );
    }
}
