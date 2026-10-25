<?php

use App\Helpers\MenuHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function addOrganizationMember($organization, $user, string $role, ?string $position = null): void
{
    DB::table('organization_officers')->insert([
        'organization' => $organization->getKey(),
        'user' => $user->getKey(),
        'role' => $role,
        'position' => $position,
        'member_since' => now()->subYear(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);
}

it('shows every member in the officers organization and adds the sidebar link', function () {
    $organization = recordsOrganization('Campus Leaders Guild');
    $officer = recordsUser(3, ['first_name' => 'Olivia', 'last_name' => 'Officer']);
    $president = recordsUser(3, ['first_name' => 'Paolo', 'last_name' => 'President', 'student_id' => 'TAU-1001', 'course_year' => 'BSIT 4']);
    $member = recordsUser(3, ['first_name' => 'Mika', 'last_name' => 'Member', 'student_id' => 'TAU-1002', 'course_year' => 'BSIT 2']);
    addOrganizationMember($organization, $officer, 'officer', 'Secretary');
    addOrganizationMember($organization, $president, 'president', 'President');
    addOrganizationMember($organization, $member, 'member');

    $response = $this->actingAs($officer)->get(route('manage-organization'));

    $response->assertOk()
        ->assertSee('Everyone in your organization')
        ->assertSee('Campus Leaders Guild')
        ->assertSee('Paolo T President')
        ->assertSee('Mika T Member')
        ->assertSee('TAU-1001')
        ->assertSee('3')
        ->assertSee('Total members');

    $records = collect(MenuHelper::getMenuGroups())->firstWhere('title', 'Reports & Records');
    expect(collect($records['items'])->firstWhere('path', '/manage-organization')['name'])
        ->toBe('Organization Members');
});

it('filters the directory and never exposes another organization roster', function () {
    $organization = recordsOrganization('Visible Organization');
    $otherOrganization = recordsOrganization('Private Organization');
    $officer = recordsUser(3, ['first_name' => 'Directory', 'last_name' => 'Officer']);
    $visibleMember = recordsUser(3, ['first_name' => 'Visible', 'last_name' => 'Member', 'course_year' => 'BSA 2']);
    $hiddenMember = recordsUser(3, ['first_name' => 'Hidden', 'last_name' => 'Member']);
    addOrganizationMember($organization, $officer, 'officer');
    addOrganizationMember($organization, $visibleMember, 'member');
    addOrganizationMember($otherOrganization, $hiddenMember, 'member');

    $this->actingAs($officer)
        ->get(route('manage-organization', [
            'organization_id' => $otherOrganization->getKey(),
            'q' => 'Visible',
            'role' => 'member',
        ]))
        ->assertOk()
        ->assertSee('Visible T Member')
        ->assertDontSee('Directory T Officer')
        ->assertDontSee('Hidden T Member')
        ->assertDontSee('Private Organization');
});
