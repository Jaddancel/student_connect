<?php

use App\Models\AppSetting;
use App\Models\Form;
use App\Models\Organization;
use App\Models\Semester;
use App\Models\User;
use App\Services\AccreditationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// AppSetting caches rememberForever; the array cache outlives RefreshDatabase.
beforeEach(fn () => Cache::flush());

function psStatsUser(string $email, int $type): User
{
    $user = User::query()->create([
        'user_email' => $email,
        'user_password' => 'password12345',
        'user_type' => $type,
        'profile_pending' => false,
    ]);
    $user->forceFill(['email_verified_at' => now()])->save();

    return $user;
}

function psStatsOrg(string $status = AccreditationService::STATUS_ACTIVE): Organization
{
    $org = Organization::query()->create(['organization_type' => 1, 'detail' => null]);
    $org->forceFill(['accreditation_status' => $status])->save();

    return $org;
}

function psStatsSubmission(Organization $org, Form $form, Carbon $requestedAt, ?bool $rejected): void
{
    $requestId = DB::table('requests')->insertGetId([
        'action' => (string) $org->getKey(),
        'action_type' => 3,
        'form_id' => $form->getKey(),
        'organization_id' => $org->getKey(),
        'requested_at' => $requestedAt,
        'payload' => json_encode(['form_id' => $form->getKey()]),
    ]);

    if ($rejected !== null) {
        DB::table('approvals')->insert(['request' => $requestId, 'is_rejected' => $rejected, 'approved_at' => now()]);
    }
}

function psStatsSetup(): array
{
    psStatsUser('ps-stats-root@example.test', User::TYPE_SUPERADMIN);
    $form = Form::create([
        'name' => 'Organization Accreditation', 'route_name' => 'organization-accreditation',
        'is_active' => true, 'is_published' => true,
    ]);
    AppSetting::put('accreditation.conditions', ['required_forms' => [$form->getKey()]]);

    // Current semester: prep opened 10 days ago, starts in 20 days.
    Semester::create([
        'name' => 'Current', 'semester_number' => 1,
        'starts_at' => Carbon::today()->addDays(20), 'ends_at' => Carbon::today()->addDays(140),
        'vacation_days' => 30,
    ]);

    return [$form];
}

it('counts active organizations without an approved accreditation submission this semester', function () {
    [$form] = psStatsSetup();
    $admin = psStatsUser('ps-stats-admin@example.test', User::TYPE_ADMIN);

    $compliant = psStatsOrg();
    psStatsSubmission($compliant, $form, Carbon::today()->subDays(2), rejected: false);

    $oldSubmission = psStatsOrg();      // approved, but before this semester's prep window
    psStatsSubmission($oldSubmission, $form, Carbon::today()->subDays(60), rejected: false);

    $rejected = psStatsOrg();
    psStatsSubmission($rejected, $form, Carbon::today()->subDay(), rejected: true);

    $pending = psStatsOrg();
    psStatsSubmission($pending, $form, Carbon::today()->subDay(), rejected: null);

    psStatsOrg();                       // never submitted

    $disabled = psStatsOrg(AccreditationService::STATUS_DISABLED);
    psStatsSubmission($disabled, $form, Carbon::today()->subDays(60), rejected: false);

    $this->actingAs($admin)
        ->getJson('/api/policy-security/stats')
        ->assertOk()
        ->assertJsonPath('data.show_accreditation', true)
        ->assertJsonPath('data.non_compliant_organizations_count', 4)
        ->assertJsonPath('data.accreditation_semester_label', '1st Sem '.Semester::first()->schoolYear())
        ->assertJsonMissingPath('data.pending_role_change_requests_count');

    // Enforcement keeps its existing (unbounded) rule.
    expect(app(AccreditationService::class)->isCompliant($oldSubmission))->toBeTrue();
});

it('returns a null count when there is no current semester', function () {
    psStatsUser('ps-stats-root@example.test', User::TYPE_SUPERADMIN);
    $admin = psStatsUser('ps-stats-admin@example.test', User::TYPE_ADMIN);
    psStatsOrg();

    $this->actingAs($admin)
        ->getJson('/api/policy-security/stats')
        ->assertOk()
        ->assertJsonPath('data.show_accreditation', true)
        ->assertJsonPath('data.non_compliant_organizations_count', null)
        ->assertJsonPath('data.accreditation_semester_label', null);
});

it('hides the accreditation count from presidents', function () {
    psStatsSetup();
    $president = psStatsUser('ps-stats-president@example.test', User::TYPE_OFFICER);
    $org = psStatsOrg();
    DB::table('organization_officers')->insert([
        'role' => 'president', 'organization' => $org->getKey(), 'user' => $president->getKey(),
        'yearterm' => null, 'member_since' => now(), 'registered_at' => now(), 'reassigned_at' => now(),
    ]);

    $this->actingAs($president)
        ->getJson('/api/policy-security/stats')
        ->assertOk()
        ->assertJsonPath('data.show_accreditation', false)
        ->assertJsonPath('data.non_compliant_organizations_count', null);
});
