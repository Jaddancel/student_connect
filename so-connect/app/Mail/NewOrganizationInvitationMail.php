<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewOrganizationInvitationMail extends Mailable
{
    public function __construct(
        public readonly string $recipientEmail,
        public readonly string $organizationName,
        public readonly string $role,
        public readonly string $activationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're Invited to Join {$this->organizationName} — StudentConnect",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.new-organization-invitation',
        );
    }
}
