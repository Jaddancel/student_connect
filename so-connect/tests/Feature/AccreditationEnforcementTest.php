<?php

use App\Forms\Handlers\OrgAccreditationHandler;
use App\Models\AppSetting;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Semester;
use App\Services\AccreditationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function makeOfficerOf(int $userId, int $orgId): void
{
    DB::table('organization_officers')->insert([
        'role' => 'officer',
        'organization' => $orgId,
        'user' => $userId,
        'member_since' => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);
}

it('redirects a member of a fully-disabled org to the suspended notice', function () {
    $user = recordsUser(3);
    $org = recordsOrganization('Disabled Org', 'DO');
    app(AccreditationService::class)->disable($org);
    makeOfficerOf((int) $user->getKey(), (int) $org->getKey());

    $this->actingAs($user)->get('/settings')->assertRedirect(route('org-suspended'));
});

it('does not block a member who still belongs to an active org', function () {
    $user = recordsUser(3);
    $disabled = recordsOrganization('Disabled', 'D1');
    app(AccreditationService::class)->disable($disabled);
    $active = recordsOrganization('Active', 'A1');
    makeOfficerOf((int) $user->getKey(), (int) $disabled->getKey());
    makeOfficerOf((int) $user->getKey(), (int) $active->getKey());

    $this->actingAs($user)->get('/settings')->assertOk();
});

it('never blocks admins', function () {
    $this->actingAs(recordsUser(2))->get('/settings')->assertOk();
});

it('lets a super admin restore a disabled org', function () {
    $super = recordsUser(1);
    $org = recordsOrganization('Restore Me', 'RM');
    app(AccreditationService::class)->disable($org);

    $this->actingAs($super)
        ->post(route('superadmin.organizations.restore', $org->getKey()))
        ->assertRedirect();

    expect($org->fresh()->isAccreditationDisabled())->toBeFalse();
});

it('forbids non-super-admins from the restore UI', function () {
    $this->actingAs(recordsUser(2))
        ->get(route('superadmin.organizations.index'))
        ->assertForbidden();
});

it('lets an admin save accreditation conditions', function () {
    $admin = recordsUser(2);
    $form = Form::create(['name' => 'Accreditation', 'route_name' => 'accred-form', 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('settings.accreditation-conditions'), ['required_forms' => [$form->id]])
        ->assertRedirect();

    expect(app(AccreditationService::class)->requiredFormIds())->toBe([(int) $form->id]);
});

it('forbids non-admins from saving accreditation conditions', function () {
    $this->actingAs(recordsUser(3))
        ->post(route('settings.accreditation-conditions'), ['required_forms' => []])
        ->assertForbidden();
});

it('shows the danger card only for a non-compliant member inside the window', function () {
    Semester::create(['name' => 'Next', 'semester_number' => 1, 'starts_at' => Carbon::today()->addDays(3), 'vacation_days' => 0]);
    $form = Form::create(['name' => 'Accreditation', 'route_name' => 'accred-form', 'is_active' => true]);
    AppSetting::put('accreditation.conditions', ['required_forms' => [$form->id]]);

    $service = app(AccreditationService::class);

    $member = recordsUser(3);
    $org = recordsOrganization('Mine', 'MN');
    makeOfficerOf((int) $member->getKey(), (int) $org->getKey());

    // Non-compliant + inside the 3-day window ⇒ warns.
    expect($service->warningDaysLeftForUser((int) $member->getKey()))->toBe(3);

    // A user with no org membership never sees the card.
    expect($service->warningDaysLeftForUser((int) recordsUser(3)->getKey()))->toBeNull();
});

it('routes an accreditation submission into a reviewable doc-gen request', function () {
    $user = recordsUser(3);
    $org = recordsOrganization('Acc Org', 'AO');
    $form = Form::create([
        'name' => 'Organization Accreditation', 'route_name' => 'organization-recognition',
        'system_function' => 'org_accreditation', 'is_active' => true, 'is_published' => true,
    ]);
    $submission = FormSubmission::create([
        'form_id' => $form->id, 'organization_id' => null, 'submitted_by' => $user->getKey(),
        'payload' => ['organization_id' => $org->getKey()], 'submitted_at' => now(),
    ]);

    $request = Illuminate\Http\Request::create('/forms/organization-recognition', 'POST');
    $request->setUserResolver(fn () => $user);

    $handler = app(OrgAccreditationHandler::class);
    $payload = ['organization_id' => (int) $org->getKey()];
    $handler->validatePayload($form, $payload, $request);
    $handler->handle($form, $submission, $payload, $request);

    // The generic doc-generation request (action_type 3) the review page reads.
    expect(DB::table('requests')
        ->where('form_id', $form->id)
        ->where('organization_id', $org->getKey())
        ->where('action_type', 3)
        ->exists())->toBeTrue();
});

it('rejects an accreditation submission for a nonexistent org', function () {
    $form = Form::create([
        'name' => 'Organization Accreditation', 'route_name' => 'organization-recognition',
        'system_function' => 'org_accreditation', 'is_active' => true, 'is_published' => true,
    ]);
    $request = Illuminate\Http\Request::create('/', 'POST');

    expect(fn () => app(OrgAccreditationHandler::class)
        ->validatePayload($form, ['organization_id' => 999999], $request))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});
