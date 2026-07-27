<?php

use App\Helpers\DashboardSearchHelper;
use App\Helpers\MenuHelper;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Form authoring is Admin-only (user_type 2). These cover the entry points
 * that used to also expose it to super admins (user_type 1): the sidebar
 * menu and the global dashboard search.
 */
function makeMenuUser(int $type): User
{
    $addressId = DB::table('profile_addresses')->insertGetId([
        'country' => 'Philippines',
        'province' => 'Tarlac',
        'town' => 'Camiling',
        'barangay' => 'Poblacion',
    ]);

    $profile = Profile::query()->create([
        'first_name' => 'Menu',
        'last_name' => 'Access',
        'middle_name' => 'T',
        'occupation' => 'Staff',
        'address' => $addressId,
    ]);

    return User::query()->create([
        'user_email' => 'menuaccess'.$type.'@example.com',
        'user_password' => 'password',
        'user_type' => $type,
        'profile' => $profile->getKey(),
    ]);
}

it('does not show the form builder or template manager to super admins in the sidebar', function () {
    $superAdmin = makeMenuUser(1);
    $this->actingAs($superAdmin);

    $paths = collect(MenuHelper::getMenuGroups())
        ->flatMap(fn ($group) => $group['items'])
        ->pluck('path');

    expect($paths)->not->toContain('/admin/form-builder');
});

it('still shows the form builder to admins in the sidebar', function () {
    $admin = makeMenuUser(2);
    $this->actingAs($admin);

    $paths = collect(MenuHelper::getMenuGroups())
        ->flatMap(fn ($group) => $group['items'])
        ->pluck('path');

    expect($paths)->toContain('/admin/form-builder');
});

it('does not surface the template manager to super admins in dashboard search', function () {
    $superAdmin = makeMenuUser(1);

    $paths = collect(DashboardSearchHelper::getItemsForUser($superAdmin))->pluck('path');

    expect($paths)->not->toContain('/admin/templates');
});

it('still surfaces the template manager to admins in dashboard search', function () {
    $admin = makeMenuUser(2);

    $paths = collect(DashboardSearchHelper::getItemsForUser($admin))->pluck('path');

    expect($paths)->toContain('/admin/templates');
});
