<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent when an admin approves a sign-up whose account already exists (the
 * pending guest account created at sign-up). Unlike {@see OfficerActivationMail}
 * there is nothing to activate — the account is simply promoted, so this points
 * at the dashboard instead of an activation link.
 */
class SignupApprovedMail extends Mailable
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $organizationName,
        public readonly string $position,
        public readonly string $dashboardUrl,
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
            view: 'mail.signup-approved',
        );
    }
}
