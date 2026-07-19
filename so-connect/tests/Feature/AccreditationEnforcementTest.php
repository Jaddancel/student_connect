<?php

use App\Models\Form;
use App\Services\AccreditationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
