<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewCalendarEventMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $eventName,
        public readonly string $description,
        public readonly string $location,
        public readonly string $startTime,
        public readonly string $endTime,
        public readonly string $calendarUrl,
        public readonly string $organizationName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New calendar event: '.$this->eventName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.new-calendar-event',
        );
    }

    public static function googleCalendarUrl(
        string $eventName,
        string $description,
        string $location,
        CarbonInterface $startTime,
        CarbonInterface $endTime,
        string $organizationName,
    ): string {
        $details = trim($description);
        if ($organizationName !== '') {
            $details = trim($details."\nOrganization: ".$organizationName);
        }

        $query = http_build_query([
            'action' => 'TEMPLATE',
            'text' => $eventName,
            'dates' => $startTime->copy()->utc()->format('Ymd\THis\Z')
                .'/'.$endTime->copy()->utc()->format('Ymd\THis\Z'),
            'details' => $details,
            'location' => $location,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'https://calendar.google.com/calendar/render?'.$query;
    }
}
