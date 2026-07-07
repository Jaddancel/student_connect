<?php

use App\Models\Officer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists organizations with an anchored officers section', function () {
    $admin = recordsUser(2);
    $org = recordsOrganization('Test Org', 'TO');

    $president = recordsUser(3);
    Officer::create([
        'role' => 'president',
        'position' => 'President',
        'organization' => $org->organization_id,
        'user' => $president->getKey(),
        'member_since' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.database-view.index'))
        ->assertOk()
        ->assertSee('Test Org')
        ->assertSee('id="org-'.$org->organization_id.'"', false)
        ->assertSee($president->user_email);
});

it('excludes plain members from the officer roster', function () {
    $admin = recordsUser(2);
    $org = recordsOrganization('Test Org', 'TO');

    $officer = recordsUser(3);
    Officer::create(['role' => 'officer', 'organization' => $org->organization_id, 'user' => $officer->getKey(), 'member_since' => now()]);

    $member = recordsUser(3);
    Officer::create(['role' => 'member', 'organization' => $org->organization_id, 'user' => $member->getKey(), 'member_since' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.database-view.officers.export.json'))
        ->assertOk()
        ->assertJsonCount(1, 'officers')
        ->assertJsonFragment(['email' => $officer->user_email])
        ->assertJsonMissing(['email' => $member->user_email]);
});

it('exposes organization and officer exports', function () {
    $admin = recordsUser(2);
    recordsOrganization('Test Org', 'TO');

    $this->actingAs($admin)->get(route('admin.database-view.orgs.export.json'))->assertOk();
    $this->actingAs($admin)->get(route('admin.database-view.orgs.export.print'))->assertOk();
    $this->actingAs($admin)->get(route('admin.database-view.orgs.export.xlsx'))->assertOk();
    $this->actingAs($admin)->get(route('admin.database-view.officers.export.print'))->assertOk();
    $this->actingAs($admin)->get(route('admin.database-view.officers.export.xlsx'))->assertOk();
});

it('blocks non-admins from the database view', function () {
    $member = recordsUser(3);

    $this->actingAs($member)->get(route('admin.database-view.index'))->assertForbidden();
});
