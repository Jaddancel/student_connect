<?php

use App\Models\Approval;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Organization;
use App\Models\Profile;
use App\Models\Profile\profileAddress;
use App\Models\Request as ActionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(DatabaseTransactions::class);

// ── Helpers ──────────────────────────────────────────────────────────────────

function makeOfficerWithOrg(string $email, string $role = 'officer'): array
{
    $addressId = DB::table('profile_addresses')->insertGetId([
        'country' => 'Philippines', 'province' => 'Cebu', 'town' => 'Cebu City', 'barangay' => 'Lahug',
    ]);

    $profile = Profile::query()->create([
        'first_name' => 'Test', 'last_name' => 'Officer', 'middle_name' => 'T',
        'occupation' => 'Student', 'address' => $addressId, 'contact_number' => '9171234567',
    ]);

    $user = User::query()->create([
        'user_email' => $email, 'user_password' => 'password', 'user_type' => 3,
        'profile' => $profile->getKey(), 'profile_pending' => false,
    ]);

    $org = Organization::query()->create(['organization_type' => 1, 'detail' => null]);

    DB::table('organization_officers')->insert([
        'role'          => $role,
        'organization'  => (int) $org->getKey(),
        'user'          => (int) $user->getKey(),
        'yearterm'      => null,
        'member_since'  => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    return [$user, $org];
}

function makeAdminUser(string $email): User
{
    return User::query()->create([
        'user_email' => $email, 'user_password' => 'password', 'user_type' => 2,
        'profile' => null, 'profile_pending' => false,
    ]);
}

function makeSuperadminUser(string $email): User
{
    return User::query()->create([
        'user_email' => $email, 'user_password' => 'password', 'user_type' => 1,
        'profile' => null, 'profile_pending' => false,
    ]);
}

function makeDirectoryPayload(int $organizationId): array
{
    return [
        'first_name'    => 'New', 'middle_name' => 'M', 'last_name' => 'Officer',
        'email'         => 'newofficer@example.test',
        'organization_id' => $organizationId,
        'semester'      => '1st', 'season' => 'fall', 'school_year' => '2024-2025',
        'position'      => 'Secretary', 'contact_number' => '9171234567',
        'age'           => 20, 'sex' => 'Female',
        'nationality'   => 'Filipino', 'birthday' => '2004-01-01',
        'present_address' => 'Barangay Lahug, Cebu City',
        'parents_guardian' => 'Guardian Name',
        'course'        => 'BS Computer Science', 'year_level' => '2nd Year',
        'date_filed'    => '2024-08-01',
    ];
}

// ── Tests ─────────────────────────────────────────────────────────────────────

it('user type 3 submitting directory form creates action_type=11 request and a form submission', function () {
    Storage::fake('public');

    [$officer, $org] = makeOfficerWithOrg('officer-directory@example.test');

    Form::firstOrCreate(
        ['route_name' => 'student-leader-directory'],
        ['name' => 'Directory of Student Leader', 'is_active' => true, 'is_published' => true, 'created_by' => null]
    );

    $photo     = UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg');
    $signature = UploadedFile::fake()->create('sig.jpg', 50, 'image/jpeg');

    $payload = array_merge(makeDirectoryPayload((int) $org->getKey()), [
        'photo'     => $photo,
        'signature' => $signature,
    ]);

    $this->actingAs($officer)
        ->post(route('student-leader-directory.store'), $payload)
        ->assertRedirect(route('student-leader-directory'));

    $actionRequest = ActionRequest::query()
        ->where('action_type', 11)
        ->where('user', (int) $officer->getKey())
        ->first();

    expect($actionRequest)->not->toBeNull();
    expect($actionRequest->action)->toContain((string) $org->getKey());

    $reqPayload = (array) $actionRequest->payload;
    expect($reqPayload['email'])->toBe('newofficer@example.test')
        ->and($reqPayload['first_name'])->toBe('New')
        ->and($reqPayload['last_name'])->toBe('Officer');

    expect(FormSubmission::query()->where('organization_id', (int) $org->getKey())->exists())->toBeTrue();
});

it('admin can see action_type=11 rows on promotion requests page but president cannot', function () {
    [$president, $org] = makeOfficerWithOrg('president-view@example.test', 'president');
    $admin = makeAdminUser('admin-view@example.test');

    ActionRequest::query()->create([
        'action' => '0|'.$org->getKey().'|new_officer', 'action_type' => 11,
        'user' => (int) $president->getKey(), 'requested_at' => now(),
        'payload' => ['first_name' => 'New', 'last_name' => 'Officer', 'organization_id' => $org->getKey()],
    ]);

    $this->actingAs($admin)
        ->get(route('promotion-requests'))
        ->assertOk()
        ->assertSee('New Officer');

    $this->actingAs($president)
        ->get(route('promotion-requests'))
        ->assertForbidden();
});

it('admin approving action_type=11 creates action_type=12 request for superadmin', function () {
    [$officer, $org] = makeOfficerWithOrg('officer-approve@example.test');
    $admin = makeAdminUser('admin-approving@example.test');

    $payload = makeDirectoryPayload((int) $org->getKey());
    $payload['email'] = 'approve-target@example.test';

    $actionRequest = ActionRequest::query()->create([
        'action' => '0|'.$org->getKey().'|new_officer', 'action_type' => 11,
        'user' => (int) $officer->getKey(), 'requested_at' => now(),
        'payload' => $payload,
    ]);

    $this->actingAs($admin)
        ->postJson('/api/requests/'.$actionRequest->getKey().'/decision', ['decision' => 'approve'])
        ->assertOk();

    $approval = Approval::query()->where('request', (int) $actionRequest->getKey())->first();
    expect($approval)->not->toBeNull()
        ->and($approval->is_rejected)->toBeFalse();

    $createdType12 = ActionRequest::query()->where('action_type', 12)->first();
    expect($createdType12)->not->toBeNull();

    $type12Payload = (array) $createdType12->payload;
    expect($type12Payload['email'])->toBe('approve-target@example.test')
        ->and($type12Payload['original_request_id'])->toBe((int) $actionRequest->getKey());
});

it('admin rejecting action_type=11 creates rejection approval and no action_type=12', function () {
    [$officer, $org] = makeOfficerWithOrg('officer-reject@example.test');
    $admin = makeAdminUser('admin-rejecting@example.test');

    $actionRequest = ActionRequest::query()->create([
        'action' => '0|'.$org->getKey().'|new_officer', 'action_type' => 11,
        'user' => (int) $officer->getKey(), 'requested_at' => now(),
        'payload' => makeDirectoryPayload((int) $org->getKey()),
    ]);

    $this->actingAs($admin)
        ->postJson('/api/requests/'.$actionRequest->getKey().'/decision', ['decision' => 'reject'])
        ->assertOk();

    $approval = Approval::query()->where('request', (int) $actionRequest->getKey())->first();
    expect($approval)->not->toBeNull()
        ->and($approval->is_rejected)->toBeTrue();

    expect(ActionRequest::query()->where('action_type', 12)->exists())->toBeFalse();
});

it('superadmin approving action_type=12 creates profile/user/member/officer records', function () {
    [, $org] = makeOfficerWithOrg('officer-sa@example.test');
    $superadmin = makeSuperadminUser('superadmin-decide@example.test');

    $payload = array_merge(makeDirectoryPayload((int) $org->getKey()), [
        'email' => 'brand-new-officer@example.test',
        'original_request_id' => 99,
    ]);

    $type12 = ActionRequest::query()->create([
        'action' => '0|New|Officer|M|0', 'action_type' => 12,
        'user' => null, 'requested_at' => now(),
        'payload' => $payload,
    ]);

    $this->actingAs($superadmin)
        ->postJson(route('superadmin.officer-account-requests.decision', $type12->getKey()), [
            'decision' => 'approve',
            'country' => 'Philippines', 'province' => 'Cebu',
            'town' => 'Cebu City', 'barangay' => 'Lahug',
        ])
        ->assertOk();

    $newUser = User::query()->where('user_email', 'brand-new-officer@example.test')->first();
    expect($newUser)->not->toBeNull()
        ->and((int) $newUser->user_type)->toBe(3);

    expect(Hash::check('tAU100!!', $newUser->user_password))->toBeTrue();

    $profile = Profile::query()->where('profile_id', $newUser->profile)->first();
    expect($profile)->not->toBeNull()
        ->and($profile->occupation)->toBe('Student')
        ->and($profile->first_name)->toBe('New');

    $addrId = (int) $profile->address;
    $addr = DB::table('profile_addresses')->where('profile_address_id', $addrId)->first();
    expect($addr)->not->toBeNull()
        ->and($addr->country)->toBe('Philippines');

    $officerRow = DB::table('organization_officers')
        ->where('user', (int) $newUser->getKey())
        ->where('organization', (int) $org->getKey())
        ->where('role', 'officer')
        ->first();
    expect($officerRow)->not->toBeNull();

    $approval = Approval::query()->where('request', (int) $type12->getKey())->first();
    expect($approval)->not->toBeNull()
        ->and($approval->is_rejected)->toBeFalse()
        ->and((int) $officerRow->approval)->toBe((int) $approval->approval_id);
});

it('admin direct creation creates all records immediately without a request queue', function () {
    $admin = makeAdminUser('admin-direct@example.test');
    $org   = Organization::query()->create(['organization_type' => 1, 'detail' => null]);

    $this->actingAs($admin)
        ->post(route('admin.officers.store'), [
            'organization_id'       => (int) $org->getKey(),
            'email'                 => 'direct-officer@example.test',
            'first_name'            => 'Direct', 'middle_name' => 'D', 'last_name' => 'Officer',
            'position'              => 'Treasurer', 'contact_number' => '9171234567',
            'age'                   => 21, 'sex' => 'Male',
            'nationality'           => 'Filipino', 'birthday' => '2003-05-15',
            'course'                => 'BS IT', 'year_level' => '3rd Year',
            'country'               => 'Philippines', 'province' => 'Cebu',
            'town'                  => 'Cebu City', 'barangay' => 'Labangon',
        ])
        ->assertRedirect(route('admin.officers.create'));

    $newUser = User::query()->where('user_email', 'direct-officer@example.test')->first();
    expect($newUser)->not->toBeNull()
        ->and((int) $newUser->user_type)->toBe(3);

    expect(Hash::check('tAU100!!', $newUser->user_password))->toBeTrue();

    $officerRow = DB::table('organization_officers')
        ->where('user', (int) $newUser->getKey())
        ->where('organization', (int) $org->getKey())
        ->where('role', 'officer')
        ->first();
    expect($officerRow)->not->toBeNull();

    expect(ActionRequest::query()->where('user', (int) $newUser->getKey())->exists())->toBeFalse();
});

it('edit profile link is absent from navbar for user_type 2 and 3 but present for superadmin', function () {
    $addressId = DB::table('profile_addresses')->insertGetId([
        'country' => 'Philippines', 'province' => 'Cebu', 'town' => 'Cebu City', 'barangay' => 'Lahug',
    ]);
    $profile = Profile::query()->create([
        'first_name' => 'Test', 'last_name' => 'User', 'middle_name' => '', 'occupation' => 'Student', 'address' => $addressId,
    ]);

    $superadmin = User::query()->create([
        'user_email' => 'superadmin-nav@example.test', 'user_password' => 'password',
        'user_type' => 1, 'profile' => $profile->getKey(), 'profile_pending' => false,
    ]);
    $adminUser = User::query()->create([
        'user_email' => 'admin-nav@example.test', 'user_password' => 'password',
        'user_type' => 2, 'profile' => null, 'profile_pending' => false,
    ]);
    [$officer] = makeOfficerWithOrg('officer-nav@example.test');

    // Each user type lands on a different actual page; test that page's rendered dropdown
    $this->actingAs($superadmin)->get(route('superadmin.profile-requests'))->assertSee('Edit profile');
    $this->actingAs($adminUser)->get(route('admin-dashboard'))->assertDontSee('Edit profile');
    $this->actingAs($officer)->get(route('officer-dashboard'))->assertDontSee('Edit profile');
});

it('activity-request form prefills presidentContactNo from profile', function () {
    [$officer, ] = makeOfficerWithOrg('officer-contact@example.test');

    $this->actingAs($officer)
        ->get(route('activity-request'))
        ->assertOk()
        ->assertSee('9171234567');
});

it('joint-statement form prefills president_name and president_contact from profile', function () {
    [$officer, ] = makeOfficerWithOrg('officer-joint@example.test');

    $profile = Profile::query()->where('profile_id', $officer->profile)->first();

    $this->actingAs($officer)
        ->get(route('joint-statement'))
        ->assertOk()
        ->assertSee('Test T Officer')
        ->assertSee('9171234567');
});
