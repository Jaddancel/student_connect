<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AfterEventReportDueMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $eventName,
        public readonly string $organizationName,
        public readonly string $finishedAt,
        public readonly ?string $deadline,
        public readonly string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'After-event report due: '.$this->eventName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.after-event-report-due',
        );
    }
}
