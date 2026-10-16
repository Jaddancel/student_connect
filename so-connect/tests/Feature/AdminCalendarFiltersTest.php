<?php

use App\Models\Event;
use App\Models\Event\EventDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows all system events to administrators and filters by selected organizations and types', function () {
    $admin = recordsUser(User::TYPE_ADMIN);
    $socioCivic = recordsOrganization('Robotics Society');
    $religious = recordsOrganization('Campus Fellowship');
    $studentGovernment = recordsOrganization('Student Council');

    $religious->update(['organization_type' => 2]);
    $studentGovernment->update(['organization_type' => 6]);

    $events = collect([
        [$socioCivic, 'Robotics Showcase'],
        [$religious, 'Campus Fellowship Gathering'],
        [$studentGovernment, 'Student Council Meeting'],
    ])->map(function (array $organizationAndName) {
        [$organization, $name] = $organizationAndName;
        $detail = EventDetail::query()->create([
            'name' => $name,
            'location' => 'Main Hall',
            'desc_text' => $name.' description',
            'start_time' => '2026-11-10 09:00:00',
            'end_time' => '2026-11-10 10:00:00',
        ]);

        return Event::query()->create([
            'organization' => $organization->getKey(),
            'event_detail' => $detail->getKey(),
        ]);
    });

    $this->actingAs($admin)
        ->getJson(route('api.events.calendar'))
        ->assertOk()
        ->assertJsonCount(3);

    $this->getJson(route('api.events.calendar', [
        'organizations' => [$socioCivic->getKey()],
        'types' => [2],
    ]))
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonFragment(['id' => (string) $events[0]->getKey()])
        ->assertJsonFragment(['id' => (string) $events[1]->getKey()])
        ->assertJsonMissing(['id' => (string) $events[2]->getKey()]);
});

it('keeps non-administrator calendars limited to organizations the user belongs to', function () {
    $member = recordsUser(User::TYPE_OFFICER);
    $memberOrganization = recordsOrganization('Member Organization');
    $otherOrganization = recordsOrganization('Other Organization');

    foreach ([$memberOrganization, $otherOrganization] as $index => $organization) {
        $detail = EventDetail::query()->create([
            'name' => 'Event '.$index,
            'location' => 'Main Hall',
            'desc_text' => 'Event description',
            'start_time' => '2026-11-10 09:00:00',
            'end_time' => '2026-11-10 10:00:00',
        ]);
        Event::query()->create([
            'organization' => $organization->getKey(),
            'event_detail' => $detail->getKey(),
        ]);
    }

    \Illuminate\Support\Facades\DB::table('organization_officers')->insert([
        'role' => 'member',
        'organization' => $memberOrganization->getKey(),
        'user' => $member->getKey(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    $this->actingAs($member)
        ->getJson(route('api.events.calendar'))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['title' => 'Event 0'])
        ->assertJsonMissing(['title' => 'Event 1']);
});
