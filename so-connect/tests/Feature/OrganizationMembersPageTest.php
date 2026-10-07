<?php

use App\Models\Officer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function rosterEntry(int $organizationId, string $role, ?string $position, array $profile): \App\Models\User
{
    $user = recordsUser(3, $profile);

    Officer::query()->create([
        'organization' => $organizationId,
        'user' => $user->getKey(),
        'role' => $role,
        'position' => $position,
    ]);

    return $user;
}

it('lists organization officers as cards and everyone else as members', function () {
    $org = recordsOrganization('Roster Society');
    $id = $org->getKey();

    rosterEntry($id, 'officer', 'Treasurer', ['first_name' => 'Tina', 'last_name' => 'Treas']);
    rosterEntry($id, 'officer', 'Others', ['first_name' => 'Oscar', 'last_name' => 'Other']);
    rosterEntry($id, 'president', 'President', ['first_name' => 'Paula', 'last_name' => 'Pres']);
    rosterEntry($id, 'officer', 'Secretary', ['first_name' => 'Sam', 'last_name' => 'Sec', 'course_year' => 'BSCS - 2nd Year']);
    rosterEntry($id, 'officer', 'Auditor', ['first_name' => 'Aldo', 'last_name' => 'Aud']);
    rosterEntry($id, 'member', null, ['first_name' => 'Mia', 'last_name' => 'Member']);

    // An officer who also has a member row is only listed once, as an officer.
    $dual = rosterEntry($id, 'officer', 'Others', ['first_name' => 'Dana', 'last_name' => 'Dual']);
    Officer::query()->create(['organization' => $id, 'user' => $dual->getKey(), 'role' => 'member']);

    $slug = 'roster-society-'.$id;

    $response = $this->get(route('organization-members', ['organizationId' => $id, 'slug' => $slug]))
        ->assertOk()
        ->assertSee('7 members')
        ->assertSee('aria-current="page"', false)
        ->assertSeeInOrder(['Officers', 'Paula Pres', 'President', 'Sam Sec', 'Secretary', 'BSCS - 2nd Year',
            'Aldo Aud', 'Auditor', 'Tina Treas', 'Treasurer', 'Dana Dual', 'Officer', 'Oscar Other', 'Officer',
            'Members', 'Mia Member']);

    expect(substr_count($response->getContent(), 'data-officer'))->toBe(6)
        ->and(substr_count($response->getContent(), 'data-member'))->toBe(1);
});

it('shows empty states and links the members tab from the feed', function () {
    $org = recordsOrganization('Empty Club');
    $params = ['organizationId' => $org->getKey(), 'slug' => 'empty-club-'.$org->getKey()];

    $this->get(route('organization-feed', $params))
        ->assertOk()
        ->assertSee(route('organization-members', $params), false)
        ->assertSee('0 members');

    $this->get(route('organization-members', $params))
        ->assertOk()
        ->assertSee('No officers listed yet.')
        ->assertSee('No members listed yet.');
});

it('redirects a stale slug to the canonical members url', function () {
    $org = recordsOrganization('Slug Guild');

    $this->get(route('organization-members', ['organizationId' => $org->getKey(), 'slug' => 'old-name']))
        ->assertRedirect(route('organization-members', [
            'organizationId' => $org->getKey(),
            'slug' => 'slug-guild-'.$org->getKey(),
        ]));
});
