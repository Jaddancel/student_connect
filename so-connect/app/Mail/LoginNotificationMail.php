<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "New sign-in" security notice, sent to users who opted in via
 * Settings (users.notify_on_login). Queued so login stays fast; the sign-in
 * metadata is captured at dispatch time by SendLoginNotification and passed as
 * scalars (the request is gone by the time the queue worker runs).
 */
class LoginNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientEmail,
        public readonly string $ipAddress,
        public readonly string $userAgent,
        public readonly string $loggedInAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New sign-in to your StudentConnect account',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.login-notification',
        );
    }
}
