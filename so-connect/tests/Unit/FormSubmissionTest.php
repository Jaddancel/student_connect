<?php

use App\Forms\Handlers\NewEventHandler;
use App\Forms\SystemFunction;
use App\Models\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function newEventValidationRequestFor($user): Request
{
    $request = Request::create('/forms/render/activity-request/submit', 'POST');
    $request->setUserResolver(fn () => $user);

    return $request;
}

it('accepts new event payload for an organization member with a future date', function () {
    $user = recordsUser(3);
    $organization = recordsOrganization('Form Submission Org', 'FSO');
    DB::table('organization_officers')->insert([
        'role' => 'officer',
        'organization' => $organization->organization_id,
        'user' => $user->getKey(),
        'member_since' => now(),
    ]);

    $form = new Form(['system_function' => SystemFunction::NEW_EVENT]);
    $payload = [
        'organization_id' => (int) $organization->organization_id,
        'title' => 'Campus Cleanup',
        'target_date' => now()->addDays(5)->toDateString(),
        'event_location' => 'Gym',
        'event_start_time' => '08:00',
        'event_end_time' => '10:00',
    ];

    (new NewEventHandler())->validatePayload($form, $payload, newEventValidationRequestFor($user));

    expect(true)->toBeTrue();
});

it('rejects new event payloads targeting a past date', function () {
    $user = recordsUser(3);
    $organization = recordsOrganization('Past Date Org', 'PDO');
    DB::table('organization_officers')->insert([
        'role' => 'officer',
        'organization' => $organization->organization_id,
        'user' => $user->getKey(),
        'member_since' => now(),
    ]);

    $form = new Form(['system_function' => SystemFunction::NEW_EVENT]);
    $payload = [
        'organization_id' => (int) $organization->organization_id,
        'title' => 'Old Event',
        'target_date' => now()->subDay()->toDateString(),
        'event_location' => 'Hall',
        'event_start_time' => '09:00',
        'event_end_time' => '11:00',
    ];

    expect(fn () => (new NewEventHandler())->validatePayload($form, $payload, newEventValidationRequestFor($user)))
        ->toThrow(ValidationException::class, 'Event requests cannot target a past date.');
});
