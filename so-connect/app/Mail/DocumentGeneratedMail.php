<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;

class DocumentGeneratedMail extends Mailable
{
    public function __construct(public readonly string $formName, public readonly string $orgName)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Document Ready: ' . $this->formName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.document-generated',
        );
    }
}
