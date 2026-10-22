<?php

use App\Models\Profile;
use App\Models\Request as ActionRequest;
use App\Models\Semester;
use App\Models\User;
use App\Services\SystemDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function systemDashboardSuperadmin(): User
{
    $profile = Profile::query()->create([
        'first_name' => 'Root', 'middle_name' => '', 'last_name' => 'Admin', 'occupation' => 'SuperAdmin',
    ]);

    $user = User::query()->create([
        'user_email' => 'system-dashboard-root@example.test',
        'user_password' => 'password12345',
        'user_type' => User::TYPE_SUPERADMIN,
        'profile' => $profile->getKey(),
        'profile_pending' => false,
    ]);
    $user->forceFill(['email_verified_at' => now()])->save();

    return $user;
}

afterEach(fn () => Carbon::setTestNow());

it('renders the system dashboard widgets and drops the profile requests button', function () {
    $this->actingAs(systemDashboardSuperadmin())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('System Dashboard')
        ->assertSee('Uptime')
        ->assertSee('Registered Accounts')
        ->assertSee('Active Organizations')
        ->assertSee('Documents Generated')
        ->assertSee('Storage Used')
        ->assertSee('Data View')
        ->assertSee('class="flex-1 min-w-0 transition-all duration-300 ease-in-out xl:ml-[290px]"', false)
        ->assertDontSee('Profile Requests')
        ->assertDontSee('/superadmin/profile-requests');
});

it('serves dashboard metrics for every period and rejects unknown ones', function () {
    $superadmin = systemDashboardSuperadmin();

    foreach (array_keys(SystemDashboardService::PERIOD_LABELS) as $period) {
        $this->actingAs($superadmin)
            ->getJson(route('superadmin.dashboard.metrics', ['period' => $period]))
            ->assertOk()
            ->assertJsonPath('period', $period)
            ->assertJsonStructure([
                'activity' => ['points', 'totals' => ['requests', 'generations', 'downloads', 'all'], 'semesters'],
                'registrations' => ['labels', 'counts'],
                'error_rate' => ['current_label', 'previous_label', 'rate', 'requests', 'errors', 'comparison'],
            ]);
    }

    $this->actingAs($superadmin)
        ->getJson(route('superadmin.dashboard.metrics', ['period' => 'decade']))
        ->assertUnprocessable();
});

it('keeps dashboard metrics superadmin-only', function () {
    systemDashboardSuperadmin();
    $admin = User::query()->create([
        'user_email' => 'system-dashboard-admin@example.test',
        'user_password' => 'password12345',
        'user_type' => User::TYPE_ADMIN,
        'profile_pending' => false,
    ]);

    $this->actingAs($admin)
        ->getJson(route('superadmin.dashboard.metrics'))
        ->assertForbidden();
});

it('no longer exposes the profile match or officer account request workflows', function () {
    $superadmin = systemDashboardSuperadmin();

    expect(Route::has('superadmin.profile-requests'))->toBeFalse()
        ->and(Route::has('superadmin.profile-requests.decision'))->toBeFalse()
        ->and(Route::has('superadmin.profile-requests.auto-accept-suggested'))->toBeFalse()
        ->and(Route::has('superadmin.officer-account-requests.decision'))->toBeFalse();

    $this->actingAs($superadmin)->get('/superadmin/profile-requests')->assertNotFound();
});

it('lets a non-admin create their profile directly without a match request', function () {
    systemDashboardSuperadmin();
    $officer = User::query()->create([
        'user_email' => 'system-dashboard-officer@example.test',
        'user_password' => 'password12345',
        'user_type' => User::TYPE_OFFICER,
        'profile' => null,
        'profile_pending' => false,
    ]);

    $this->actingAs($officer)
        ->post(route('profile.store'), ['fname' => 'Ana', 'lname' => 'Reyes', 'mname' => 'S'])
        ->assertRedirect(route('dashboard'));

    $officer->refresh();
    $profile = Profile::query()->find($officer->profile);

    expect($profile)->not->toBeNull()
        ->and($profile->first_name)->toBe('Ana')
        ->and($profile->occupation)->toBe('Student')
        ->and($officer->profile_pending)->toBeFalse()
        ->and(ActionRequest::query()->where('action_type', 9)->exists())->toBeFalse();
});

it('counts requests and server errors into hourly metrics', function () {
    systemDashboardSuperadmin();
    Route::get('/__dashboard-test/ok', fn () => 'ok');
    Route::get('/__dashboard-test/boom', fn () => abort(500));

    $bucket = fn () => DB::table('system_request_metrics')->where('bucket_at', now()->startOfHour())->first();
    $before = $bucket();

    $this->get('/__dashboard-test/ok')->assertOk();
    $this->get('/__dashboard-test/boom')->assertStatus(500);

    $after = $bucket();

    expect((int) $after->requests - (int) ($before->requests ?? 0))->toBe(2)
        ->and((int) $after->server_errors - (int) ($before->server_errors ?? 0))->toBe(1);
});

it('logs only successful tracked downloads', function () {
    $superadmin = systemDashboardSuperadmin();
    Route::middleware(['web', 'track.download:test'])->group(function () {
        Route::get('/__dashboard-test/download', fn () => response('file'));
        Route::get('/__dashboard-test/missing', fn () => redirect('/'));
    });

    $this->actingAs($superadmin)->get('/__dashboard-test/download')->assertOk();
    $this->actingAs($superadmin)->get('/__dashboard-test/missing')->assertRedirect();

    expect(DB::table('document_downloads')->count())->toBe(1);
    $this->assertDatabaseHas('document_downloads', ['kind' => 'test', 'user_id' => $superadmin->getKey()]);
    $this->assertDatabaseHas('action_logs', [
        'user_id' => $superadmin->getKey(),
        'category' => \App\Services\ActionLogger::CATEGORY_DOCUMENT_GENERATION,
        'action' => 'document_download',
    ]);
    expect(DB::table('action_logs')->where('action', 'document_download')->count())->toBe(1);
});

it('compares the current error rate against the previous period', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', 'UTC'));
    DB::table('system_request_metrics')->insert([
        ['bucket_at' => '2026-08-10 03:00:00', 'requests' => 100, 'server_errors' => 4],
        ['bucket_at' => '2026-11-02 05:00:00', 'requests' => 200, 'server_errors' => 2],
    ]);

    $errorRate = app(SystemDashboardService::class)->metrics('quarterly')['error_rate'];

    expect($errorRate['current_label'])->toBe('Q4 2026')
        ->and($errorRate['previous_label'])->toBe('Q3 2026')
        ->and($errorRate['rate'])->toBe(1.0)
        ->and($errorRate['previous_rate'])->toBe(4.0)
        ->and($errorRate['comparison'])->toBe('lower');
});

it('places semester start and preparation markers on the activity chart', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', 'UTC'));
    Semester::query()->create([
        'name' => 'Second Semester 2026-2027', 'semester_number' => 2,
        'starts_at' => '2026-11-09', 'ends_at' => '2027-03-29', 'vacation_days' => 40,
    ]);

    $activity = app(SystemDashboardService::class)->metrics('quarterly')['activity'];
    $marker = collect($activity['semesters'])->firstWhere('starts_on', 'Nov 9, 2026');
    $majors = array_values(array_filter(array_column($activity['points'], 'major')));

    expect($majors)->toBe(['Q1 2026', 'Q2 2026', 'Q3 2026', 'Q4 2026'])
        ->and($marker)->not->toBeNull()
        ->and($marker['start_x'])->toBeFloat()
        ->and($marker['prep_from_x'])->toBeLessThan($marker['prep_to_x'])
        ->and($marker['prep_to_x'])->toEqual($marker['start_x']);
});
