<?php

use App\Forms\SystemFunction;
use App\Models\AppSetting;
use App\Models\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// AppSetting caches rememberForever; start each test from a clean cache.
beforeEach(fn () => Cache::flush());

function dashboardRequest(array $attributes, ?bool $rejected = null, ?string $stage = null, ?int $admin = null): int
{
    $requestId = DB::table('requests')->insertGetId(array_merge([
        'action' => '0',
        'requested_at' => now(),
    ], $attributes, [
        'payload' => json_encode($attributes['payload'] ?? []),
    ]));

    if ($rejected !== null) {
        DB::table('approvals')->insert([
            'request' => $requestId,
            'admin' => $admin,
            'stage' => $stage,
            'is_rejected' => $rejected,
            'approved_at' => now(),
        ]);
    }

    return $requestId;
}

it('tracks new user and organization membership requests in the membership section', function () {
    $admin = recordsUser(2);
    $org = recordsOrganization('Chess Club');
    $member = recordsUser(3, ['first_name' => 'Mia', 'last_name' => 'Member']);

    $signupId = dashboardRequest([
        'action' => '0|'.$org->getKey().'|new_officer',
        'action_type' => 11,
        'requested_at' => now()->subMinutes(5),
        'payload' => ['first_name' => 'Nina', 'last_name' => 'Newbie', 'organization_id' => $org->getKey()],
    ], rejected: false);

    $membershipId = dashboardRequest([
        'action' => $org->getKey().'|'.$member->getKey(),
        'action_type' => 1,
        'organization_id' => $org->getKey(),
        'user' => $member->getKey(),
    ]);
    // President approval alone is not final for memberships.
    DB::table('approvals')->insert(['request' => $membershipId, 'stage' => 'president', 'is_rejected' => false, 'approved_at' => now()]);

    $roleChangeId = dashboardRequest(['action' => '1|'.$org->getKey().'|member', 'action_type' => 7]);

    $response = $this->actingAs($admin)->getJson('/api/dashboard/requests/membership')->assertOk();

    $rows = collect($response->json('data'))->keyBy('request_id');

    expect($rows->keys()->all())->toBe([$membershipId, $signupId])
        ->and($rows->has($roleChangeId))->toBeFalse()
        ->and($rows[$signupId]['kind'])->toBe('New User')
        ->and($rows[$signupId]['name_or_title'])->toBe('Nina Newbie (New User)')
        ->and($rows[$signupId]['request_organization'])->toBe('Chess Club')
        ->and($rows[$signupId]['approval_status'])->toBe('approved')
        ->and($rows[$membershipId]['kind'])->toBe('Organization Membership')
        ->and($rows[$membershipId]['name_or_title'])->toBe('Mia Member (Organization Membership)')
        ->and($rows[$membershipId]['approval_status'])->toBe('pending');

    DB::table('approvals')->insert(['request' => $membershipId, 'stage' => 'admin', 'is_rejected' => true, 'approved_at' => now()]);

    $this->actingAs($admin)->getJson('/api/dashboard/requests/membership')
        ->assertJsonPath('data.0.approval_status', 'rejected');
});

it('tracks only activity requests in the events section', function () {
    $admin = recordsUser(2);
    $org = recordsOrganization('Drama Guild');
    $activityForm = Form::query()->create([
        'name' => 'New Event', 'route_name' => 'dash-new-event', 'system_function' => SystemFunction::NEW_EVENT,
    ]);
    $otherForm = Form::query()->create(['name' => 'Other Form', 'route_name' => 'dash-other']);

    $submissionId = DB::table('form_submissions')->insertGetId([
        'form_id' => $activityForm->getKey(),
        'payload' => json_encode(['projectActivity' => 'Spring Play']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $activityId = dashboardRequest([
        'action_type' => 3,
        'form_id' => $activityForm->getKey(),
        'organization_id' => $org->getKey(),
        'payload' => ['form_id' => $activityForm->getKey(), 'submission_id' => $submissionId],
    ], rejected: true);
    dashboardRequest(['action_type' => 3, 'form_id' => $otherForm->getKey(), 'payload' => ['form_id' => $otherForm->getKey()]]);
    dashboardRequest(['action_type' => 2, 'action' => $org->getKey().'|legacy']);

    $this->actingAs($admin)->getJson('/api/dashboard/requests/events')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.request_id', $activityId)
        ->assertJsonPath('data.0.kind', 'Activity')
        ->assertJsonPath('data.0.name_or_title', 'Spring Play')
        ->assertJsonPath('data.0.request_organization', 'Drama Guild')
        ->assertJsonPath('data.0.approval_status', 'rejected');
});

it('tracks requests from accreditation-required forms in the policy section', function () {
    $admin = recordsUser(2);
    $requester = recordsUser(3, ['first_name' => 'Ana', 'last_name' => 'Officer']);
    $org = recordsOrganization('Robotics Society');
    $required = Form::query()->create([
        'name' => 'Constitution and By-Laws',
        'route_name' => 'dash-cbl',
        'auto_approve_submissions' => true,
    ]);
    $notRequired = Form::query()->create(['name' => 'Feedback', 'route_name' => 'dash-feedback']);
    AppSetting::put('accreditation.conditions', ['required_forms' => [$required->getKey()]]);

    $requiredId = dashboardRequest([
        'action_type' => 3,
        'organization_id' => $org->getKey(),
        'user' => $requester->getKey(),
        // Matched through the payload when the form_id column is empty.
        'payload' => ['form_id' => $required->getKey()],
    ]);
    $systemAcceptedId = dashboardRequest([
        'action_type' => 3,
        'form_id' => $required->getKey(),
        'organization_id' => $org->getKey(),
        'user' => $requester->getKey(),
        'payload' => ['form_id' => $required->getKey()],
    ], rejected: false, admin: 0);
    dashboardRequest(['action_type' => 3, 'form_id' => $notRequired->getKey(), 'payload' => ['form_id' => $notRequired->getKey()]]);
    $oldId = dashboardRequest([
        'action_type' => 3,
        'form_id' => $required->getKey(),
        'requested_at' => now()->subDays(3),
    ], rejected: false);
    // Future-dated rows are never "in the last 24 hours".
    dashboardRequest(['action_type' => 3, 'form_id' => $required->getKey(), 'requested_at' => now()->addDays(2)]);

    $this->actingAs($admin)->getJson('/api/dashboard/requests/policy?hours=24')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.request_id', $requiredId)
        ->assertJsonPath('data.0.name_or_title', 'Constitution and By-Laws — Ana Officer')
        ->assertJsonPath('data.0.request_organization', 'Robotics Society')
        ->assertJsonPath('data.0.approval_status', 'pending');

    $response = $this->actingAs($admin)->getJson('/api/dashboard/requests/policy')
        ->assertJsonCount(4, 'data')
        ->assertOk();

    $rows = collect($response->json('data'))->keyBy('request_id');
    expect($rows[$oldId]['approval_status'])->toBe('approved')
        ->and($rows[$oldId]['accepted_by_system'])->toBeFalse()
        ->and($rows[$systemAcceptedId]['accepted_by_system'])->toBeTrue();
});

it('restricts dashboard requests to admins', function () {
    $this->actingAs(recordsUser(3))->getJson('/api/dashboard/requests/membership')->assertForbidden();
    $this->actingAs(recordsUser(2))->getJson('/api/dashboard/requests/unknown')->assertNotFound();
});

it('requires authentication for dashboard requests', function () {
    $this->getJson('/api/dashboard/requests/membership')->assertUnauthorized();
});

it('offers last quarter and last year filters on the admin dashboard', function () {
    $this->actingAs(recordsUser(2))->get('/dashboard')->assertOk()
        ->assertSee('<option value="quarter">Last quarter</option>', false)
        ->assertSee('<option value="year">Last year</option>', false)
        ->assertDontSee('<option value="semester">', false);
});

it('filters every dashboard section to the rolling quarter or year', function (string $window, string $date, string $start, string $section) {
    $this->travelTo(\Illuminate\Support\Carbon::parse($date));
    $form = Form::query()->create([
        'name' => 'Quarter Test', 'route_name' => 'quarter-test',
        'system_function' => SystemFunction::NEW_EVENT,
    ]);
    AppSetting::put('accreditation.conditions', ['required_forms' => [$form->getKey()]]);
    $attributes = [
        'action_type' => $section === 'membership' ? 11 : 3,
        'form_id' => $form->getKey(),
    ];
    $first = dashboardRequest($attributes + ['requested_at' => $start]);
    $latest = dashboardRequest($attributes + ['requested_at' => now()]);
    dashboardRequest($attributes + ['requested_at' => \Illuminate\Support\Carbon::parse($start)->subSecond()]);
    dashboardRequest($attributes + ['requested_at' => now()->addSecond()]);

    $response = $this->actingAs(recordsUser(2))
        ->getJson('/api/dashboard/requests/'.$section.'?window='.$window.'&hours=1')
        ->assertOk()->assertJsonCount(2, 'data');

    expect(array_column($response->json('data'), 'request_id'))->toBe([$latest, $first])
        ->and($response->json('range.starts_at'))->toBe(\Illuminate\Support\Carbon::parse($start)->toIso8601String())
        ->and($response->json('range.ends_at'))->toBe(now()->toIso8601String());
})->with([
    ['quarter', '2026-10-12 12:00:00', '2026-07-12 12:00:00'],
    ['quarter', '2026-01-15 12:00:00', '2025-10-15 12:00:00'],
    ['quarter', '2026-05-31 12:00:00', '2026-02-28 12:00:00'],
    ['quarter', '2024-05-31 12:00:00', '2024-02-29 12:00:00'],
    ['year', '2026-10-12 12:00:00', '2025-10-12 12:00:00'],
    ['year', '2024-02-29 12:00:00', '2023-02-28 12:00:00'],
])->with(['membership', 'events', 'policy']);

it('validates unsupported dashboard windows', function (string $window) {
    $this->actingAs(recordsUser(2))
        ->getJson('/api/dashboard/requests/membership?window='.$window)
        ->assertUnprocessable()->assertJsonValidationErrors('window');
})->with(['invalid', 'semester']);
