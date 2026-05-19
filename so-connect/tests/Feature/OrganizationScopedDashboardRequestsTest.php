<?php

use App\Models\Approval;
use App\Models\Event;
use App\Models\Event\EventDetail;
use App\Models\Organization;
use App\Models\Profile;
use App\Models\Request as ActionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function assignOfficerRole(User $user, int $organizationId, string $role = 'officer'): void
{
    DB::table('organization_officers')->insert([
        'role'          => $role,
        'organization'  => $organizationId,
        'user'          => (int) $user->getKey(),
        'yearterm'      => null,
        'member_since'  => now(),
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

    $this->assertDatabaseHas('organization_officers', [
        'user' => (int) $requester->getKey(),
        'organization' => (int) $receivingOrganization->getKey(),
        'role' => 'member',
    ]);

    $this->assertDatabaseHas('organization_officers', [
        'user' => (int) $requester->getKey(),
        'organization' => (int) $otherOrganization->getKey(),
    ]);
});

it('allows policy and security requests only for presidents and scopes by president organizations', function () {
    $president = createUserWithProfile('policy-president@example.test');
    $officerOnly = createUserWithProfile('policy-officer@example.test');
    $requester = createUserWithProfile('policy-requester@example.test');

    $orgA = Organization::query()->create([
        'organization_type' => 1,
        'detail' => null,
    ]);

    $orgB = Organization::query()->create([
        'organization_type' => 1,
        'detail' => null,
    ]);

    assignOfficerRole($president, (int) $orgA->getKey(), 'president');
    assignOfficerRole($officerOnly, (int) $orgA->getKey(), 'officer');

    ActionRequest::query()->create([
        'action' => $requester->getKey().'|'.$orgA->getKey().'|member',
        'requested_at' => now(),
        'user' => $requester->getKey(),
        'action_type' => 7,
    ]);

    ActionRequest::query()->create([
        'action' => $requester->getKey().'|'.$orgB->getKey().'|member',
        'requested_at' => now()->subMinute(),
        'user' => $requester->getKey(),
        'action_type' => 7,
    ]);

    $this->actingAs($president)
        ->getJson('/api/policy-security/requests')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.organization_id', (int) $orgA->getKey());

    $this->actingAs($officerOnly)
        ->getJson('/api/policy-security/requests')
        ->assertForbidden();
});

it('approves type 7 member to officer role change', function () {
    $president = createUserWithProfile('role-change-president@example.test');
    $requester = createUserWithProfile('role-change-requester@example.test');

    $organization = Organization::query()->create([
        'organization_type' => 1,
        'detail' => null,
    ]);

    assignOfficerRole($president, (int) $organization->getKey(), 'president');

    DB::table('organization_officers')->insert([
        'role'          => 'member',
        'organization'  => (int) $organization->getKey(),
        'user'          => (int) $requester->getKey(),
        'yearterm'      => null,
        'member_since'  => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    $roleChangeRequest = ActionRequest::query()->create([
        'action' => $requester->getKey().'|'.$organization->getKey().'|member',
        'requested_at' => now(),
        'user' => $requester->getKey(),
        'action_type' => 7,
    ]);

    $this->actingAs($president)
        ->postJson('/api/requests/'.$roleChangeRequest->getKey().'/decision', [
            'decision' => 'approve',
        ])
        ->assertOk();

    $this->assertDatabaseHas('organization_officers', [
        'user' => (int) $requester->getKey(),
        'organization' => (int) $organization->getKey(),
        'role' => 'officer',
    ]);
});

it('approves type 7 officer to president role change', function () {
    $president = createUserWithProfile('role-change-president-2@example.test');
    $requester = createUserWithProfile('role-change-requester-2@example.test');

    $organization = Organization::query()->create([
        'organization_type' => 1,
        'detail' => null,
    ]);

    assignOfficerRole($president, (int) $organization->getKey(), 'president');

    DB::table('organization_officers')->insert([
        'role'          => 'officer',
        'organization'  => (int) $organization->getKey(),
        'user'          => (int) $requester->getKey(),
        'yearterm'      => null,
        'member_since'  => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    $roleChangeRequest = ActionRequest::query()->create([
        'action' => $requester->getKey().'|'.$organization->getKey().'|officer',
        'requested_at' => now(),
        'user' => $requester->getKey(),
        'action_type' => 7,
    ]);

    $this->actingAs($president)
        ->postJson('/api/requests/'.$roleChangeRequest->getKey().'/decision', [
            'decision' => 'approve',
        ])
        ->assertOk();

    $this->assertDatabaseHas('organization_officers', [
        'user' => (int) $requester->getKey(),
        'organization' => (int) $organization->getKey(),
        'role' => 'president',
    ]);
});

it('auto-approves requests when a president creates them', function () {
    $president = createUserWithProfile('auto-approve-president@example.test');
    $organization = Organization::query()->create([
        'organization_type' => 1,
        'detail' => null,
    ]);

    assignOfficerRole($president, (int) $organization->getKey(), 'president');

    // When president creates an event request
    $this->actingAs($president)
        ->postJson('/api/events/requests', [
            'organization_id' => (int) $organization->getKey(),
            'name' => 'Tech Meetup',
            'location' => 'Virtual',
            'desc_text' => 'A tech meetup',
            'start_time' => now()->addDay()->toDateTimeString(),
            'end_time' => now()->addDay()->addHours(2)->toDateTimeString(),
        ])
        ->assertCreated();

    // The request should be automatically approved
    $lastRequest = ActionRequest::query()->latest()->first();
    expect($lastRequest)->not()->toBeNull();
    expect($lastRequest->action_type)->toBe(2); // Event request type

    $approval = DB::table('approvals')
        ->where('request', (int) $lastRequest->getKey())
        ->first();

    expect($approval)->not()->toBeNull();
    expect($approval->approved_at)->not()->toBeNull();
    expect((bool) $approval->is_rejected)->toBeFalse();

    // The event should have been created automatically
    $event = Event::query()
        ->where('organization', (int) $organization->getKey())
        ->where('creator', (int) $president->getKey())
        ->first();

    expect($event)->not()->toBeNull();
});
