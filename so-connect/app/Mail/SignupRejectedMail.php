<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent when an admin rejects a sign-up: carries the admin's reason, and either
 * how many attempts remain or the news that the account has been removed after
 * the final one (see RequestDecisionController::MAX_SIGNUP_REJECTIONS).
 */
class SignupRejectedMail extends Mailable
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $reason,
        public readonly int $attemptsLeft,
        public readonly bool $accountDeleted,
        public readonly string $retryUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            // After the final rejection there is nothing left to correct, so
            // the subject says what happened rather than asking for changes.
            subject: $this->accountDeleted
                ? 'Your StudentConnect sign-up was closed'
                : 'Your StudentConnect sign-up needs changes',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.signup-rejected',
        );
    }
}
