<?php

use App\Models\Profile;
use App\Models\Request as ActionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a pending type 8 profile request from profile setup', function () {
    $closestProfile = Profile::query()->create([
        'first_name' => 'Jane',
        'middle_name' => 'M',
        'last_name' => 'Doe',
        'occupation' => 'Student',
        'address' => null,
    ]);

    $member = User::query()->create([
        'user_email' => 'member-profile-request@example.test',
        'user_password' => 'password12345',
        'user_type' => 3,
        'profile' => null,
        'profile_pending' => false,
    ]);

    $this->actingAs($member)
        ->post('/profile/create', [
            'fname' => 'Jane',
            'lname' => 'Doe',
            'mname' => 'Marie',
        ])
        ->assertRedirect(route('dashboard'));

    $createdRequest = ActionRequest::query()
        ->where('user', (int) $member->getKey())
        ->where('action_type', 9)
        ->latest('request_id')
        ->first();

    expect($createdRequest)->not()->toBeNull();

    $parts = explode('|', (string) $createdRequest->action);

    expect($parts[0] ?? null)->toBe((string) $member->getKey())
        ->and($parts[1] ?? null)->toBe('Jane')
        ->and($parts[2] ?? null)->toBe('Doe')
        ->and($parts[4] ?? null)->toBe((string) $closestProfile->getKey());

    $member->refresh();

    expect($member->profile)->toBeNull()
        ->and($member->profile_pending)->toBeTrue();
});

it('allows superadmin to approve a profile request and attach a profile', function () {
    $superAdmin = User::query()->create([
        'user_email' => 'superadmin-approve@example.test',
        'user_password' => 'password12345',
        'user_type' => 1,
        'profile' => null,
        'profile_pending' => false,
    ]);

    $targetUser = User::query()->create([
        'user_email' => 'pending-user@example.test',
        'user_password' => 'password12345',
        'user_type' => 3,
        'profile' => null,
        'profile_pending' => true,
    ]);

    $profileToAttach = Profile::query()->create([
        'first_name' => 'John',
        'middle_name' => 'Q',
        'last_name' => 'Public',
        'occupation' => 'Engineer',
        'address' => null,
    ]);

    $profileRequest = ActionRequest::query()->create([
        'action' => implode('|', [
            (int) $targetUser->getKey(),
            'John',
            'Public',
            'Q',
            (int) $profileToAttach->getKey(),
        ]),
        'action_type' => 9,
        'user' => (int) $targetUser->getKey(),
    ]);

    $this->actingAs($superAdmin)
        ->postJson('/superadmin/profile-requests/'.$profileRequest->getKey().'/decision', [
            'decision' => 'approve',
            'profile_id' => (int) $profileToAttach->getKey(),
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Profile request approved and linked successfully.');

    $targetUser->refresh();

    expect((int) $targetUser->profile)->toBe((int) $profileToAttach->getKey())
        ->and($targetUser->profile_pending)->toBeFalse();

    $this->assertDatabaseHas('approvals', [
        'request' => (int) $profileRequest->getKey(),
        'admin' => (int) $superAdmin->getKey(),
        'is_rejected' => 0,
    ]);
});
