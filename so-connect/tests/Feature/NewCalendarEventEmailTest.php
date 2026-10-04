<?php

use App\Mail\NewCalendarEventMail;
use App\Models\Event;
use App\Models\Event\EventDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('queues event details to each type-3 member of the event organization', function () {
    Mail::fake();

    $organization = recordsOrganization('Robotics Society');
    $member = recordsUser(3, ['first_name' => 'Alex', 'last_name' => 'Member']);
    $otherMember = recordsUser(3);
    $admin = recordsUser(2);
    $unrelatedMember = recordsUser(3);

    foreach ([$member, $otherMember, $admin] as $user) {
        DB::table('organization_officers')->insert([
            'role' => 'member',
            'organization' => $organization->getKey(),
            'user' => $user->getKey(),
            'registered_at' => now(),
            'reassigned_at' => now(),
        ]);
    }
    DB::table('organization_officers')->insert([
        'role' => 'member',
        'organization' => recordsOrganization('Debate Society')->getKey(),
        'user' => $unrelatedMember->getKey(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    $detail = EventDetail::query()->create([
        'name' => 'Robotics Showcase',
        'location' => 'Main Hall',
        'desc_text' => 'Student robotics demonstrations',
        'start_time' => '2026-11-10 09:00:00',
        'end_time' => '2026-11-10 12:00:00',
    ]);

    Event::query()->create([
        'organization' => $organization->getKey(),
        'event_detail' => $detail->getKey(),
    ]);

    Mail::assertQueuedCount(2);
    Mail::assertQueued(
        NewCalendarEventMail::class,
        fn (NewCalendarEventMail $mail) => $mail->hasTo($member->user_email)
            && $mail->recipientName === 'Alex Member'
            && $mail->eventName === 'Robotics Showcase'
            && $mail->description === 'Student robotics demonstrations'
            && $mail->location === 'Main Hall'
            && str_contains($mail->calendarUrl, 'calendar.google.com/calendar/render')
            && str_contains($mail->calendarUrl, 'Robotics%20Showcase')
    );
    Mail::assertNotQueued(
        NewCalendarEventMail::class,
        fn (NewCalendarEventMail $mail) => $mail->hasTo($admin->user_email)
            || $mail->hasTo($unrelatedMember->user_email)
    );
});
