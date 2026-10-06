<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OfficerActivationMail extends Mailable
{
    public function __construct(
        public readonly string $recipientEmail,
        public readonly string $organizationName,
        public readonly string $position,
        public readonly string $activationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your StudentConnect Application Was Approved',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.officer-activation',
        );
    }
}
