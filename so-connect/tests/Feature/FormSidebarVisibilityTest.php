<?php

use App\Helpers\MenuHelper;
use App\Models\Form;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function sidebarTestUser(int $type, string $tag): User
{
    $addressId = DB::table('profile_addresses')->insertGetId([
        'country' => 'Philippines', 'province' => 'Tarlac', 'town' => 'Camiling', 'barangay' => 'Poblacion',
    ]);
    $profile = Profile::query()->create([
        'first_name' => 'Sidebar', 'last_name' => 'Tester', 'middle_name' => 'T', 'occupation' => 'Staff', 'address' => $addressId,
    ]);

    return User::query()->create([
        'user_email' => 'sidebar-'.$tag.'@example.com',
        'user_password' => 'password',
        'user_type' => $type,
        'profile' => $profile->getKey(),
    ]);
}

function sidebarTestOfficer(): User
{
    $user = sidebarTestUser(3, 'officer');
    $detailId = DB::table('organization_details')->insertGetId([
        'name' => 'Sidebar Org', 'detail_text' => 'Test organization', 'initials' => 'SO',
    ]);
    $orgId = DB::table('organizations')->insertGetId(['organization_type' => 1, 'detail' => $detailId]);
    DB::table('organization_officers')->insert([
        'role' => 'officer', 'organization' => $orgId, 'user' => (int) $user->getKey(), 'yearterm' => null,
        'member_since' => now(), 'registered_at' => now(), 'reassigned_at' => now(),
    ]);

    return $user;
}

/** @return list<string> */
function organizationFormPaths(): array
{
    $group = collect(MenuHelper::getMenuGroups())->firstWhere('title', 'Organization Forms');

    return collect($group['items'] ?? [])->pluck('path')->all();
}

it('saves the show-on-sidebar option from the builder, defaulting to shown', function () {
    $payload = [
        'name' => 'Sidebar Form', 'route_name' => 'sidebar-form',
        'fields' => [['field_key' => 'a', 'field_label' => 'A', 'field_type' => 'text', 'is_required' => false]],
        'rows' => [],
    ];

    $this->actingAs(sidebarTestUser(2, 'admin'))
        ->postJson(route('admin.form-builder.store'), $payload)
        ->assertOk();
    $form = Form::where('route_name', 'sidebar-form')->firstOrFail();
    expect($form->show_in_sidebar)->toBeTrue();

    $this->putJson(route('admin.form-builder.update', $form), array_merge($payload, ['show_in_sidebar' => false]))
        ->assertOk();
    expect($form->fresh()->show_in_sidebar)->toBeFalse();

    $this->get(route('admin.form-builder.edit', $form))
        ->assertOk()
        ->assertSee('Show on sidebar');

    $this->putJson(route('admin.form-builder.update', $form), array_merge($payload, ['show_in_sidebar' => 'nope']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('show_in_sidebar');
});

it('hides forms with show-on-sidebar off from the officer sidebar only', function () {
    $shown = Form::create(['name' => 'Shown Form', 'route_name' => 'shown-form', 'is_active' => true, 'is_published' => true]);
    $hidden = Form::create(['name' => 'Hidden Form', 'route_name' => 'hidden-form', 'is_active' => true, 'is_published' => true, 'show_in_sidebar' => false]);

    $this->actingAs(sidebarTestOfficer());
    expect(organizationFormPaths())
        ->toContain('/forms/shown-form')
        ->not->toContain('/forms/hidden-form');

    // Admins still get a request queue for the hidden form.
    $this->actingAs(sidebarTestUser(2, 'admin2'));
    $requestPaths = collect(collect(MenuHelper::getMenuGroups())->firstWhere('title', 'Requests')['items'])->pluck('path');
    expect($requestPaths)->toContain('/admin/form-requests/'.$hidden->id)
        ->toContain('/admin/form-requests/'.$shown->id);
});
