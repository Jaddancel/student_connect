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

it('disables and restores an org', function () {
    $org = recordsOrganization('Toggle Org', 'TO');
    $service = app(AccreditationService::class);

    $service->disable($org);
    $fresh = $org->fresh();
    expect($fresh->isAccreditationDisabled())->toBeTrue();
    expect($fresh->accreditation_disabled_at)->not->toBeNull();

    $service->restore($org->fresh());
    $restored = $org->fresh();
    expect($restored->isAccreditationDisabled())->toBeFalse();
    expect($restored->accreditation_disabled_at)->toBeNull();
});

it('selects non-compliant active orgs for disabling once a semester has started', function () {
    // A semester that has already started ⇒ enforcement deadline is yesterday.
    Semester::create(['name' => 'Started', 'semester_number' => 1, 'starts_at' => Carbon::today()->subDay(), 'vacation_days' => 0]);
    $form = accreditationForm();
    AppSetting::put('accreditation.conditions', ['required_forms' => [$form->getKey()]]);

    $compliant = recordsOrganization('Compliant', 'C1');
    orgRequest((int) $compliant->getKey(), (int) $form->getKey(), Carbon::today()->subDays(3), rejected: false);

    $nonCompliant = recordsOrganization('Non Compliant', 'N1');

    $alreadyDisabled = recordsOrganization('Disabled', 'D1');
    app(AccreditationService::class)->disable($alreadyDisabled);

    $ids = app(AccreditationService::class)->orgsToDisable()->pluck('organization_id')->all();

    expect($ids)->toContain((int) $nonCompliant->getKey());
    expect($ids)->not->toContain((int) $compliant->getKey());
    expect($ids)->not->toContain((int) $alreadyDisabled->getKey());
});

it('selects only past-grace disabled orgs for purge', function () {
    AppSetting::put('accreditation.purge_grace_days', 30);
    $service = app(AccreditationService::class);

    $expired = recordsOrganization('Expired', 'EX');
    $expired->forceFill(['accreditation_status' => 'disabled', 'accreditation_disabled_at' => Carbon::today()->subDays(31)])->save();

    $recent = recordsOrganization('Recent', 'RE');
    $recent->forceFill(['accreditation_status' => 'disabled', 'accreditation_disabled_at' => Carbon::today()->subDays(5)])->save();

    $ids = $service->orgsToPurge()->pluck('organization_id')->all();

    expect($ids)->toContain((int) $expired->getKey());
    expect($ids)->not->toContain((int) $recent->getKey());
});

it('hard-purges an org and its subtree without touching other orgs', function () {
    $org = recordsOrganization('Purge Me', 'PM');
    $detailId = $org->detail;
    orgRequest((int) $org->getKey(), 999, Carbon::today(), rejected: false);

    $survivor = recordsOrganization('Survivor', 'SV');
    orgRequest((int) $survivor->getKey(), 999, Carbon::today(), rejected: false);

    app(AccreditationService::class)->purge($org);

    expect(DB::table('organizations')->where('organization_id', $org->getKey())->exists())->toBeFalse();
    expect(DB::table('organization_details')->where('organization_detail_id', $detailId)->exists())->toBeFalse();
    expect(DB::table('requests')->where('organization_id', $org->getKey())->exists())->toBeFalse();

    // The other org and its data are untouched.
    expect(DB::table('organizations')->where('organization_id', $survivor->getKey())->exists())->toBeTrue();
    expect(DB::table('requests')->where('organization_id', $survivor->getKey())->exists())->toBeTrue();
});
