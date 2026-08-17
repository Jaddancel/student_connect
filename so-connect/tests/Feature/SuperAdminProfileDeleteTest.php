<?php

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows a superadmin to delete a profile', function () {
    $superAdmin = User::query()->create([
        'user_email' => 'superadmin-delete@example.test',
        'user_password' => 'password12345',
        'user_type' => 1,
        'profile' => null,
        'profile_pending' => false,
    ]);

    $profile = Profile::query()->create([
        'first_name' => 'John',
        'middle_name' => 'Q',
        'last_name' => 'Public',
        'occupation' => 'Engineer',
        'address' => null,
    ]);

    $this->actingAs($superAdmin)
        ->deleteJson('/superadmin/profiles/'.$profile->getKey())
        ->assertOk()
        ->assertJsonPath('message', 'Profile deleted successfully.');

    $this->assertDatabaseMissing('profiles', [
        'profile_id' => $profile->getKey(),
    ]);
});

it('unlinks the owning user account instead of blocking deletion', function () {
    $superAdmin = User::query()->create([
        'user_email' => 'superadmin-delete-linked@example.test',
        'user_password' => 'password12345',
        'user_type' => 1,
        'profile' => null,
        'profile_pending' => false,
    ]);

    $profile = Profile::query()->create([
        'first_name' => 'Jane',
        'middle_name' => 'M',
        'last_name' => 'Doe',
        'occupation' => 'Student',
        'address' => null,
    ]);

    $member = User::query()->create([
        'user_email' => 'member-delete@example.test',
        'user_password' => 'password12345',
        'user_type' => 3,
        'profile' => (int) $profile->getKey(),
        'profile_pending' => false,
    ]);

    $this->actingAs($superAdmin)
        ->deleteJson('/superadmin/profiles/'.$profile->getKey())
        ->assertOk();

    $member->refresh();

    expect($member->profile)->toBeNull();
});

it('refuses to delete a superadmin profile', function () {
    $superAdmin = User::query()->create([
        'user_email' => 'superadmin-delete-self@example.test',
        'user_password' => 'password12345',
        'user_type' => 1,
        'profile' => null,
        'profile_pending' => false,
    ]);

    $otherSuperAdminProfile = Profile::query()->create([
        'first_name' => 'Root',
        'middle_name' => '',
        'last_name' => 'Admin',
        'occupation' => 'SuperAdmin',
        'address' => null,
    ]);

    $otherSuperAdmin = User::query()->create([
        'user_email' => 'superadmin-target@example.test',
        'user_password' => 'password12345',
        'user_type' => 1,
        'profile' => (int) $otherSuperAdminProfile->getKey(),
        'profile_pending' => false,
    ]);

    $this->actingAs($superAdmin)
        ->deleteJson('/superadmin/profiles/'.$otherSuperAdminProfile->getKey())
        ->assertForbidden();

    $this->assertDatabaseHas('profiles', [
        'profile_id' => $otherSuperAdminProfile->getKey(),
    ]);
});

it('blocks non-superadmins from deleting a profile', function () {
    $officer = User::query()->create([
        'user_email' => 'officer-delete@example.test',
        'user_password' => 'password12345',
        'user_type' => 3,
        'profile' => null,
        'profile_pending' => false,
    ]);

    $profile = Profile::query()->create([
        'first_name' => 'Someone',
        'middle_name' => '',
        'last_name' => 'Else',
        'occupation' => 'Student',
        'address' => null,
    ]);

    $this->actingAs($officer)
        ->deleteJson('/superadmin/profiles/'.$profile->getKey())
        ->assertForbidden();

    $this->assertDatabaseHas('profiles', [
        'profile_id' => $profile->getKey(),
    ]);
});
