<?php

use App\Models\Event;
use App\Models\Event\EventDetail;
use App\Models\Form;
use App\Models\ManualFormSession;
use App\Models\Request as ActionRequest;
use App\Models\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('shows the selected organization banner, officer count and scoped events', function () {
    $this->travelTo(now()->setDate(2026, 10, 12)->startOfDay());
    $user = recordsUser(3);
    $otherUser = recordsUser(3);
    $member = recordsUser(3);
    $organization = recordsOrganization('Ecological and Solid Waste Management Society', 'ESWM');
    $otherOrganization = recordsOrganization('Another organization');
    $organization->forceFill(['created_at' => '2023-06-01 00:00:00'])->save();

    foreach ([
        [$user, $organization, 'president'],
        [$otherUser, $organization, 'officer'],
        [$member, $organization, 'member'],
        [$user, $otherOrganization, 'officer'],
    ] as [$person, $org, $role]) {
        DB::table('organization_officers')->insert([
            'user' => $person->getKey(),
            'organization' => $org->getKey(),
            'role' => $role,
        ]);
    }

    $events = [];
    foreach ([$organization, $otherOrganization] as $org) {
        $detail = EventDetail::query()->create([
            'name' => 'Event for '.$org->getKey(),
            'desc_text' => 'Organization event',
            'location' => 'Campus',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
        ]);
        $events[$org->getKey()] = Event::query()->create([
            'organization' => $org->getKey(),
            'event_detail' => $detail->getKey(),
        ]);
    }
    Semester::query()->create([
        'name' => 'First',
        'starts_at' => '2026-08-01',
        'ends_at' => '2026-10-18',
        'vacation_days' => 0,
    ]);

    $this->actingAs($user)
        ->withSession(['active_organization_id' => $organization->getKey()])
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Ecological and Solid Waste Management Society')
        ->assertDontSee('Ecological and Solid Waste Management Society Dashboard')
        ->assertSee('Founded on June 1, 2023.')
        ->assertSee('2 officers.')
        ->assertSee('Time until semester end:')
        ->assertSee('ESWM%20TAU.JPG')
        ->assertSee('Event for '.$organization->getKey())
        ->assertSee(route('calendar', ['event' => $events[$organization->getKey()]->getKey()]))
        ->assertDontSee('Event for '.$otherOrganization->getKey())
        ->assertViewHas('semesterDaysRemaining', 7);

    $this->actingAs($user)
        ->withSession(['active_organization_id' => $otherOrganization->getKey()])
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Another organization')
        ->assertDontSee('Another organization Dashboard')
        ->assertSee('1 officer.')
        ->assertSee('Event for '.$otherOrganization->getKey())
        ->assertSee(route('calendar', ['event' => $events[$otherOrganization->getKey()]->getKey()]))
        ->assertDontSee('Event for '.$organization->getKey());
});

it('renders explicit deadline states without a logo', function (?string $end, string $message) {
    $this->travelTo(now()->setDate(2026, 10, 12)->startOfDay());
    $user = recordsUser(3);
    $organization = recordsOrganization('No logo organization', 'NLO');
    DB::table('organization_officers')->insert([
        'user' => $user->getKey(),
        'organization' => $organization->getKey(),
        'role' => 'officer',
    ]);
    if ($end !== null) {
        Semester::query()->create([
            'name' => 'First', 'starts_at' => '2026-08-01',
            'ends_at' => $end, 'vacation_days' => 0,
        ]);
    }

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertSee('No logo organization')
        ->assertDontSee('No logo organization Dashboard')
        ->assertSee('NLO')
        ->assertSee($message)
        ->assertSee('No upcoming events');
})->with([
    'no semester' => [null, 'Semester end date not configured.'],
    'below seven days' => ['2026-10-17', 'Time until semester end:'],
    'above seven days' => ['2026-10-19', 'Days until semester end: 8'],
    'ended semester' => ['2026-10-11', 'Semester has ended.'],
]);

it('keeps the existing event empty state for users without an organization', function () {
    $this->actingAs(recordsUser(3))->get('/dashboard')
        ->assertOk()
        ->assertSee('No organization selected')
        ->assertSee('No upcoming events')
        ->assertDontSee('organization-dashboard-title');
});

it('surfaces a student\'s pending work and recent submission status', function () {
    $user = recordsUser(3, ['first_name' => 'Alex']);
    $organization = recordsOrganization('Student Leaders Association', 'SLA');
    DB::table('organization_officers')->insert([
        'user' => $user->getKey(),
        'organization' => $organization->getKey(),
        'role' => 'officer',
        'position' => 'Secretary',
    ]);

    $form = Form::factory()->create([
        'name' => 'Activity Proposal',
        'route_name' => 'activity-proposal-dashboard-test',
    ]);

    $pending = ActionRequest::factory()->create([
        'action' => 'pending',
        'action_type' => 3,
        'form_id' => $form->getKey(),
        'organization_id' => $organization->getKey(),
        'requested_by' => $user->getKey(),
        'requested_at' => now(),
    ]);
    $approved = ActionRequest::factory()->create([
        'action' => 'approved',
        'action_type' => 3,
        'form_id' => $form->getKey(),
        'organization_id' => $organization->getKey(),
        'requested_by' => $user->getKey(),
        'requested_at' => now()->subMinute(),
    ]);
    $rejected = ActionRequest::factory()->create([
        'action' => 'rejected',
        'action_type' => 3,
        'form_id' => $form->getKey(),
        'organization_id' => $organization->getKey(),
        'requested_by' => $user->getKey(),
        'requested_at' => now()->subMinutes(2),
    ]);

    DB::table('approvals')->insert([
        [
            'approved_at' => now(),
            'request' => $approved->getKey(),
            'is_rejected' => false,
        ],
        [
            'approved_at' => now(),
            'request' => $rejected->getKey(),
            'is_rejected' => true,
            'rejection_reason' => 'Please attach the revised budget.',
        ],
    ]);

    ManualFormSession::query()->create([
        'form_id' => $form->getKey(),
        'user_id' => $user->getKey(),
        'status' => ManualFormSession::STATUS_REVIEW,
    ]);

    $this->actingAs($user)
        ->withSession(['active_organization_id' => $organization->getKey()])
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Welcome back, Alex')
        ->assertSee('Secretary')
        ->assertSee('Needs your attention')
        ->assertSee('Continue open drafts')
        ->assertSee('Submissions awaiting review')
        ->assertSee('Activity Proposal')
        ->assertSee('Awaiting review')
        ->assertSee('Approved')
        ->assertSee('Needs revision')
        ->assertSee('Please attach the revised budget.')
        ->assertViewHas('pendingRequestCount', 1)
        ->assertViewHas('openDraftCount', 1);

    expect($pending->exists)->toBeTrue();
});
