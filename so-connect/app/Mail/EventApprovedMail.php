<?php

namespace App\Mail;

use App\Models\EventPlan;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;

class EventApprovedMail extends Mailable
{
    public function __construct(public readonly EventPlan $plan, public readonly string $recipientName)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Event Approved: ' . $this->plan->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.event-approved',
        );
    }
}
