<?php

namespace App\Observers;

use App\Mail\NewCalendarEventMail;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class EventCreatedObserver
{
    public function created(Event $event): void
    {
        if (! $event->organization || ! $event->detailOfEvent) {
            return;
        }

        $event->loadMissing(['detailOfEvent', 'organizationOfEvent.detail']);

        $recipients = DB::table('organization_officers as oo')
            ->join('users as u', 'u.user_id', '=', 'oo.user')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->where('oo.organization', (int) $event->organization)
            ->where('u.user_type', User::TYPE_OFFICER)
            ->whereNotNull('u.user_email')
            ->where('u.user_email', '<>', '')
            ->select([
                'u.user_email',
                'p.first_name',
                'p.last_name',
            ])
            ->distinct()
            ->get();

        $detail = $event->detailOfEvent;
        $organizationName = (string) ($event->organizationOfEvent?->detail?->name ?? 'Organization');

        foreach ($recipients as $recipient) {
            Mail::to((string) $recipient->user_email)->queue(
                (new NewCalendarEventMail(
                    recipientName: trim(implode(' ', array_filter([
                        $recipient->first_name,
                        $recipient->last_name,
                    ]))) ?: 'there',
                    eventName: (string) $detail->name,
                    description: (string) ($detail->desc_text ?? ''),
                    location: (string) ($detail->location ?? ''),
                    startTime: $detail->start_time->format('M j, Y g:i A T'),
                    endTime: $detail->end_time->format('M j, Y g:i A T'),
                    calendarUrl: NewCalendarEventMail::googleCalendarUrl(
                        eventName: (string) $detail->name,
                        description: (string) ($detail->desc_text ?? ''),
                        location: (string) ($detail->location ?? ''),
                        startTime: $detail->start_time,
                        endTime: $detail->end_time,
                        organizationName: $organizationName,
                    ),
                    organizationName: $organizationName,
                ))->afterCommit()
            );
        }
    }
}
