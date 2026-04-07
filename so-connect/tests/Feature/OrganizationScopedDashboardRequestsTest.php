<?php

use App\Models\Member;
use App\Models\Organization;
use App\Models\Profile;
use App\Models\Request as ActionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function createUserWithProfile(string $email): User
{
    $addressId = DB::table('profile_addresses')->insertGetId([
        'country' => 'Philippines',
        'province' => 'Cebu',
        'town' => 'Cebu City',
        'barangay' => 'Lahug',
    ]);

    $profile = Profile::query()->create([
        'first_name' => 'Test',
        'last_name' => 'User',
        'middle_name' => 'T',
        'occupation' => 'Student',
        'address' => $addressId,
    ]);

    return User::query()->create([
        'user_email' => $email,
        'user_password' => 'password',
        'user_type' => 3,
        'profile' => $profile->getKey(),
    ]);
}

function assignOfficerRole(User $user, int $organizationId, string $role = 'officer'): void
{
    $member = Member::query()->create([
        'organization' => $organizationId,
        'approval' => null,
        'user' => (int) $user->getKey(),
        'member_since' => now(),
    ]);

    DB::table('organization_officers')->insert([
        'role' => $role,
        'organization' => $organizationId,
        'member' => $member->getKey(),
        'yearterm' => null,
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);
}

it('lists requests only for organizations where the user is an officer', function () {
    $officerUser = createUserWithProfile('officer@example.test');

    $organizationA = Organization::query()->create([
        'organization_type' => 1,
        'detail' => null,
    ]);

    $organizationB = Organization::query()->create([
        'organization_type' => 1,
        'detail' => null,
    ]);

    assignOfficerRole($officerUser, (int) $organizationA->getKey());

    $requesterA = createUserWithProfile('requester-a@example.test');
    $requesterB = createUserWithProfile('requester-b@example.test');

    $allowedRequest = ActionRequest::query()->create([
        'action' => $organizationA->getKey().'|'.$requesterA->getKey(),
        'requested_at' => now(),
        'user' => $requesterA->getKey(),
        'action_type' => 1,
    ]);

    $blockedRequest = ActionRequest::query()->create([
        'action' => $organizationB->getKey().'|'.$requesterB->getKey(),
        'requested_at' => now()->subMinute(),
        'user' => $requesterB->getKey(),
        'action_type' => 1,
    ]);

    $this->actingAs($officerUser)
        ->getJson('/api/requests/1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.request_id', $allowedRequest->getKey())
        ->assertJsonMissing(['request_id' => $blockedRequest->getKey()]);
});

it('denies decision for requests outside the officer organizations', function () {
    $officerUser = createUserWithProfile('officer-deny@example.test');

    $organizationA = Organization::query()->create([
        'organization_type' => 1,
        'detail' => null,
    ]);

    $organizationB = Organization::query()->create([
        'organization_type' => 1,
        'detail' => null,
    ]);

    assignOfficerRole($officerUser, (int) $organizationA->getKey());

    $requester = createUserWithProfile('requester-deny@example.test');

    $foreignRequest = ActionRequest::query()->create([
        'action' => $organizationB->getKey().'|'.$requester->getKey(),
        'requested_at' => now(),
        'user' => $requester->getKey(),
        'action_type' => 1,
    ]);

    $this->actingAs($officerUser)
        ->postJson('/api/requests/'.$foreignRequest->getKey().'/decision', [
            'decision' => 'approve',
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'You are not authorized to decide this request.');

    $this->assertDatabaseMissing('approvals', [
        'request' => $foreignRequest->getKey(),
    ]);
});

it('allows decision for requests in officer organizations', function () {
    $officerUser = createUserWithProfile('officer-approve@example.test');

    $organization = Organization::query()->create([
        'organization_type' => 1,
        'detail' => null,
    ]);

    assignOfficerRole($officerUser, (int) $organization->getKey());

    $requester = createUserWithProfile('requester-approve@example.test');

    $ownedRequest = ActionRequest::query()->create([
        'action' => $organization->getKey().'|'.$requester->getKey(),
        'requested_at' => now(),
        'user' => $requester->getKey(),
        'action_type' => 1,
    ]);

    $this->actingAs($officerUser)
        ->postJson('/api/requests/'.$ownedRequest->getKey().'/decision', [
            'decision' => 'approve',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Request approved successfully.');

    $this->assertDatabaseHas('approvals', [
        'request' => $ownedRequest->getKey(),
        'admin' => $officerUser->getKey(),
        'is_rejected' => 0,
    ]);
});

it('accepts membership as member only in the receiving organization', function () {
    $approver = createUserWithProfile('approver-member-only@example.test');
    $requester = createUserWithProfile('requester-member-only@example.test');

    $receivingOrganization = Organization::query()->create([
        'organization_type' => 1,
        'detail' => null,
    ]);

    $otherOrganization = Organization::query()->create([
        'organization_type' => 1,
        'detail' => null,
    ]);

    assignOfficerRole($approver, (int) $receivingOrganization->getKey());
    assignOfficerRole($requester, (int) $otherOrganization->getKey());

    $membershipRequest = ActionRequest::query()->create([
        'action' => $receivingOrganization->getKey().'|'.$requester->getKey(),
        'requested_at' => now(),
        'user' => $requester->getKey(),
        'action_type' => 1,
    ]);

    $this->actingAs($approver)
        ->postJson('/api/requests/'.$membershipRequest->getKey().'/decision', [
            'decision' => 'approve',
        ])
        ->assertOk();

    $newMembership = Member::query()
        ->where('organization', (int) $receivingOrganization->getKey())
        ->where('user', (int) $requester->getKey())
        ->first();

    expect($newMembership)->not()->toBeNull();

    $this->assertDatabaseMissing('organization_officers', [
        'member' => (int) $newMembership->getKey(),
        'organization' => (int) $receivingOrganization->getKey(),
    ]);

    $this->assertDatabaseHas('organization_officers', [
        'organization' => (int) $otherOrganization->getKey(),
    ]);
});
