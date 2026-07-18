<?php

use App\Models\AppSetting;
use App\Models\Form;
use App\Models\Semester;
use App\Services\AccreditationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// AppSetting caches rememberForever; the array cache survives between tests even
// though RefreshDatabase wipes the rows, so start each test from a clean cache.
beforeEach(fn () => Cache::flush());

function accreditationForm(string $route = 'organization-accreditation'): Form
{
    return Form::create([
        'name' => 'Organization Accreditation',
        'route_name' => $route,
        'is_active' => true,
        'is_published' => true,
    ]);
}

/** Insert a doc-generation request for an org+form, optionally decided. */
function orgRequest(int $orgId, int $formId, Carbon $requestedAt, ?bool $rejected = null): void
{
    $requestId = DB::table('requests')->insertGetId([
        'action' => (string) $orgId,
        'action_type' => 3,
        'form_id' => $formId,
        'organization_id' => $orgId,
        'requested_at' => $requestedAt,
        'payload' => json_encode(['form_id' => $formId]),
    ]);

    if ($rejected !== null) {
        DB::table('approvals')->insert([
            'request' => $requestId,
            'is_rejected' => $rejected,
            'approved_at' => now(),
        ]);
    }
}

it('takes the deadline from the next upcoming semester', function () {
    Semester::create(['name' => 'Past', 'semester_number' => 1, 'starts_at' => Carbon::today()->subDays(10), 'vacation_days' => 0]);
    Semester::create(['name' => 'Next', 'semester_number' => 2, 'starts_at' => Carbon::today()->addDays(30), 'vacation_days' => 0]);

    $service = app(AccreditationService::class);

    expect($service->deadline()->toDateString())->toBe(Carbon::today()->addDays(30)->toDateString());
    expect($service->daysUntilDeadline())->toBe(30);
});

it('opens the warning window only inside notify_days of the deadline', function () {
    Semester::create(['name' => 'Next', 'semester_number' => 1, 'starts_at' => Carbon::today()->addDays(5), 'vacation_days' => 0]);

    // Default notify window is 7 days, so 5 days out is inside it.
    expect(app(AccreditationService::class)->isWithinWarningWindow())->toBeTrue();

    AppSetting::put('accreditation.notify_days', 3);
    // Now the window is only 3 days, so 5 days out is outside it.
    expect(app(AccreditationService::class)->isWithinWarningWindow())->toBeFalse();
});

it('marks an org compliant when a required form is approved before the deadline', function () {
    $org = recordsOrganization('Compliant Org', 'CO');
    $form = accreditationForm();
    AppSetting::put('accreditation.conditions', ['required_forms' => [$form->getKey()]]);

    orgRequest((int) $org->getKey(), (int) $form->getKey(), Carbon::today()->subDay(), rejected: false);

    $service = app(AccreditationService::class);
    expect($service->isCompliant($org, Carbon::today()))->toBeTrue();
    expect($service->missingFormIds($org, Carbon::today()))->toBe([]);
});

it('does not count a pending or rejected submission', function () {
    $org = recordsOrganization('Pending Org', 'PO');
    $form = accreditationForm();
    AppSetting::put('accreditation.conditions', ['required_forms' => [$form->getKey()]]);

    // Pending (no approval row) + rejected (is_rejected = true) — neither counts.
    orgRequest((int) $org->getKey(), (int) $form->getKey(), Carbon::today()->subDay(), rejected: null);
    orgRequest((int) $org->getKey(), (int) $form->getKey(), Carbon::today()->subDay(), rejected: true);

    expect(app(AccreditationService::class)->isCompliant($org, Carbon::today()))->toBeFalse();
});

it('does not count an approved submission made after the deadline', function () {
    $org = recordsOrganization('Late Org', 'LO');
    $form = accreditationForm();
    AppSetting::put('accreditation.conditions', ['required_forms' => [$form->getKey()]]);

    $deadline = Carbon::today()->subDay();
    // Approved, but submitted today — after yesterday's deadline.
    orgRequest((int) $org->getKey(), (int) $form->getKey(), Carbon::today(), rejected: false);

    expect(app(AccreditationService::class)->isCompliant($org, $deadline))->toBeFalse();
});

it('falls back to the seeded accreditation form when no conditions are configured', function () {
    $form = accreditationForm();

    expect(app(AccreditationService::class)->requiredFormIds())->toBe([(int) $form->getKey()]);
});
