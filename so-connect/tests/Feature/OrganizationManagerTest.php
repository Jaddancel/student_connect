<?php

use App\Helpers\MenuHelper;
use App\Models\ActionLog;
use App\Models\Officer;
use App\Models\Organization;
use App\Models\User;
use App\Services\AccreditationService;
use App\Services\ActionLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function managerSeat(Organization $org, string $role, ?string $position, array $profile = [], int $type = 3): User
{
    $user = recordsUser($type, $profile);

    Officer::query()->create([
        'organization' => $org->getKey(),
        'user' => $user->getKey(),
        'role' => $role,
        'position' => $position,
    ]);

    return $user;
}

function seatOf(Organization $org, User $user): Officer
{
    return Officer::query()
        ->where('organization', $org->getKey())
        ->where('user', $user->getKey())
        ->firstOrFail();
}

function changeOfficer(Organization $org, string $position, User $user)
{
    return test()->post(route('admin.organization-manager.update', ['organization' => $org->getKey()]), [
        'position' => $position,
        'user_id' => $user->getKey(),
    ]);
}

beforeEach(function () {
    $this->admin = recordsUser(2);
    $this->org = recordsOrganization('Manager Guild');
});

it('restricts the page to admins', function () {
    $this->get(route('admin.organization-manager.index'))->assertRedirect(route('home'));

    foreach ([1, 3, 4] as $type) {
        $this->actingAs(recordsUser($type))
            ->get(route('admin.organization-manager.index'))
            ->assertForbidden();
    }

    $this->actingAs($this->admin)
        ->get(route('admin.organization-manager.index'))
        ->assertOk()
        ->assertSee('Organization Manager');
});

it('shows the link in the admin sidebar group', function () {
    $this->actingAs($this->admin);

    $admin = collect(MenuHelper::getMenuGroups())->firstWhere('title', 'Admin');

    expect(collect($admin['items'])->pluck('path'))->toContain('/admin/organization-manager');
});

it('lists only active organizations and shows the officer board', function () {
    $disabled = recordsOrganization('Dormant Club');
    $disabled->update(['accreditation_status' => AccreditationService::STATUS_DISABLED]);

    managerSeat($this->org, 'president', 'President', ['first_name' => 'Paula', 'last_name' => 'Pres']);
    managerSeat($this->org, 'officer', 'Treasurer', ['first_name' => 'Tina', 'last_name' => 'Treas']);
    managerSeat($this->org, 'officer', 'Others', ['first_name' => 'Oscar', 'last_name' => 'Other']);
    managerSeat($this->org, 'member', 'Secretary', ['first_name' => 'Mia', 'last_name' => 'Member']);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.organization-manager.index', ['organization_id' => $this->org->getKey()]))
        ->assertOk()
        ->assertSee('Manager Guild')
        ->assertDontSee('Dormant Club')
        ->assertSeeInOrder(['President', 'Paula Pres', 'Secretary', 'Vacant', 'Auditor', 'Vacant', 'Treasurer', 'Tina Treas', 'Other', 'Oscar Other'])
        ->assertDontSee('Mia Member');

    expect(substr_count($response->getContent(), 'data-other-officer'))->toBe(1);
});

it('replaces a position holder, keeping them as an Others officer', function () {
    $old = managerSeat($this->org, 'officer', 'Secretary');
    $new = managerSeat($this->org, 'member', null, ['first_name' => 'Nina', 'last_name' => 'New']);

    $this->actingAs($this->admin);
    changeOfficer($this->org, 'Secretary', $new)
        ->assertRedirect(route('admin.organization-manager.index', ['organization_id' => $this->org->getKey()]))
        ->assertSessionHas('success');

    expect(seatOf($this->org, $new)->only(['role', 'position']))->toEqual(['role' => 'officer', 'position' => 'Secretary'])
        ->and(seatOf($this->org, $old)->only(['role', 'position']))->toEqual(['role' => 'officer', 'position' => 'Others']);

    $log = ActionLog::query()->where('category', ActionLogger::CATEGORY_ORGANIZATION_OFFICERS)->first();
    expect($log)->not->toBeNull()
        ->and($log->meta['position'])->toBe('Secretary')
        ->and($log->meta['replaced_user_ids'])->toEqual([(int) $old->getKey()]);
});

it('moves a named officer to President, vacating their old position', function () {
    $president = managerSeat($this->org, 'president', 'President');
    $treasurer = managerSeat($this->org, 'officer', 'Treasurer');
    DB::table('presidents')->insert(['officer' => seatOf($this->org, $president)->getKey()]);

    $this->actingAs($this->admin);
    changeOfficer($this->org, 'President', $treasurer)->assertSessionHasNoErrors();

    expect(seatOf($this->org, $treasurer)->only(['role', 'position']))->toEqual(['role' => 'president', 'position' => 'President'])
        ->and(seatOf($this->org, $president)->only(['role', 'position']))->toEqual(['role' => 'officer', 'position' => 'Others'])
        ->and(DB::table('presidents')->count())->toBe(0);

    $this->get(route('admin.organization-manager.index', ['organization_id' => $this->org->getKey()]))
        ->assertSeeInOrder(['Treasurer', 'Vacant']);
});

it('promotes the officer row of a user who also has a member row', function () {
    $dual = managerSeat($this->org, 'officer', 'Others');
    Officer::query()->create(['organization' => $this->org->getKey(), 'user' => $dual->getKey(), 'role' => 'member']);

    $this->actingAs($this->admin);
    changeOfficer($this->org, 'Auditor', $dual)->assertSessionHasNoErrors();

    $roles = Officer::query()->where('user', $dual->getKey())->orderBy('org_officer_id')->get(['role', 'position'])->toArray();
    expect($roles)->toEqual([
        ['role' => 'officer', 'position' => 'Auditor'],
        ['role' => 'member', 'position' => null],
    ]);
});

it('rejects invalid assignments', function () {
    $other = recordsOrganization('Elsewhere');
    $outsider = managerSeat($other, 'member', null);
    $guest = managerSeat($this->org, 'member', null, [], 4);
    $secretary = managerSeat($this->org, 'officer', 'Secretary');

    $this->actingAs($this->admin);

    changeOfficer($this->org, 'Secretary', $outsider)->assertSessionHasErrors('user_id');
    changeOfficer($this->org, 'Secretary', $guest)->assertSessionHasErrors('user_id');
    changeOfficer($this->org, 'Secretary', $secretary)->assertSessionHasErrors('user_id');
    changeOfficer($this->org, 'Vice President', $secretary)->assertSessionHasErrors('position');

    $this->org->update(['accreditation_status' => AccreditationService::STATUS_DISABLED]);
    changeOfficer($this->org, 'Auditor', $secretary)->assertNotFound();

    expect(seatOf($this->org, $secretary)->position)->toBe('Secretary')
        ->and(seatOf($this->org, $guest)->role)->toBe('member');
});

it('searches officer-account members of the organization only', function () {
    managerSeat($this->org, 'member', null, ['first_name' => 'Alice', 'last_name' => 'Alpha']);
    managerSeat($this->org, 'officer', 'Auditor', ['first_name' => 'Alan', 'last_name' => 'Audit']);
    managerSeat($this->org, 'member', null, ['first_name' => 'Alfred', 'last_name' => 'Guest'], 4);
    managerSeat(recordsOrganization('Other Org'), 'member', null, ['first_name' => 'Albert', 'last_name' => 'Away']);
    managerSeat($this->org, 'member', null, ['first_name' => 'Bob', 'last_name' => 'Beta']);

    $url = route('admin.organization-manager.candidates', ['organization' => $this->org->getKey()]);

    $this->actingAs($this->admin)
        ->getJson($url.'?q=Al')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['name' => 'Alice Alpha', 'current' => 'Member'])
        ->assertJsonFragment(['name' => 'Alan Audit', 'current' => 'Auditor'])
        ->assertJsonMissing(['name' => 'Alfred Guest'])
        ->assertJsonMissing(['name' => 'Albert Away']);

    $this->getJson($url.'?q=alice alpha')->assertJsonCount(1, 'data');
    $this->getJson($url)->assertJsonCount(3, 'data');

    $this->actingAs(recordsUser(3))->getJson($url)->assertForbidden();
});
