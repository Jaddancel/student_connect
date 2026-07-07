<?php

use App\Models\LoginLog;
use App\Models\Officer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets an admin view the audit logs page', function () {
    $admin = recordsUser(2);
    $member = recordsUser(3, ['first_name' => 'Alice', 'last_name' => 'Anderson']);

    LoginLog::create([
        'user_id' => $member->getKey(),
        'interaction' => 'LOGIN',
        'logged_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.audit-logs.index'))
        ->assertOk()
        ->assertSee($member->user_email);
});

it('filters audit logs by name', function () {
    $admin = recordsUser(2);
    $alice = recordsUser(3, ['first_name' => 'Alice', 'last_name' => 'Anderson']);
    $bob = recordsUser(3, ['first_name' => 'Bob', 'last_name' => 'Baker']);

    LoginLog::create(['user_id' => $alice->getKey(), 'interaction' => 'LOGIN', 'logged_at' => now()]);
    LoginLog::create(['user_id' => $bob->getKey(), 'interaction' => 'LOGIN', 'logged_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.audit-logs.index', ['name' => 'Alice']))
        ->assertOk()
        ->assertSee('Alice')
        ->assertDontSee($bob->user_email);
});

it('filters audit logs by organization', function () {
    $admin = recordsUser(2);
    $inOrg = recordsUser(3);
    $outOrg = recordsUser(3);
    $org = recordsOrganization('Alpha Org', 'AO');

    Officer::create([
        'role' => 'officer',
        'organization' => $org->organization_id,
        'user' => $inOrg->getKey(),
        'member_since' => now(),
    ]);

    LoginLog::create(['user_id' => $inOrg->getKey(), 'interaction' => 'LOGIN', 'logged_at' => now()]);
    LoginLog::create(['user_id' => $outOrg->getKey(), 'interaction' => 'LOGIN', 'logged_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.audit-logs.index', ['org_id' => $org->organization_id]))
        ->assertOk()
        ->assertSee($inOrg->user_email)
        ->assertDontSee($outOrg->user_email);
});

it('exposes audit log exports that respect filters', function () {
    $admin = recordsUser(2);
    $alice = recordsUser(3, ['first_name' => 'Alice', 'last_name' => 'Anderson']);
    LoginLog::create(['user_id' => $alice->getKey(), 'interaction' => 'LOGIN', 'logged_at' => now()]);

    $this->actingAs($admin)->get(route('admin.audit-logs.export.json', ['name' => 'Alice']))
        ->assertOk()
        ->assertJsonFragment(['user_email' => $alice->user_email]);
    $this->actingAs($admin)->get(route('admin.audit-logs.export.print'))->assertOk();
    $this->actingAs($admin)->get(route('admin.audit-logs.export.xlsx'))->assertOk();
});

it('blocks non-admins from the audit logs', function () {
    $member = recordsUser(3);

    $this->actingAs($member)->get(route('admin.audit-logs.index'))->assertForbidden();
});
