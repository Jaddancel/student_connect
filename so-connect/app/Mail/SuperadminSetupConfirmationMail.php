<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent during first-run system setup. The superadmin account is created
 * unverified and cannot sign in until this link is followed, so whoever runs
 * the installer has to prove they control the address they typed.
 */
class SuperadminSetupConfirmationMail extends Mailable
{
    public function __construct(
        public readonly string $recipientEmail,
        public readonly string $confirmationUrl,
        public readonly int $expiresInHours,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirm your StudentConnect superadmin account',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.superadmin-setup-confirmation',
        );
    }
}
