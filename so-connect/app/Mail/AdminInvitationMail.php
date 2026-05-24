<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AdminInvitationMail extends Mailable
{
    public function __construct(
        public readonly string $recipientEmail,
        public readonly string $recipientName,
        public readonly string $activationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Admin Account Has Been Created — StudentConnect',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.admin-invitation',
        );
    }
}
