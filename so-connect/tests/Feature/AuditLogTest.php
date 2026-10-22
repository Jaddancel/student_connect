<?php

use App\Models\ActionLog;
use App\Models\Approval;
use App\Models\Officer;
use App\Models\Request as ActionRequest;
use App\Services\ActionLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function auditAction(
    \App\Models\User $user,
    string $category = ActionLogger::CATEGORY_REQUESTS,
    string $description = 'Sent a request',
    ?string $action = null,
): void {
    $action ??= [
        ActionLogger::CATEGORY_AUTH => 'login',
        ActionLogger::CATEGORY_REQUESTS => 'request_sent',
        ActionLogger::CATEGORY_DOCUMENT_GENERATION => 'document_download',
    ][$category] ?? 'did_something';

    ActionLogger::log($category, $action, $description, userId: $user->getKey());
}

function auditRequest(\App\Models\User $requester): ActionRequest
{
    return ActionRequest::query()->create([
        'action' => 'document_generation',
        'action_type' => \App\Helpers\FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION,
        'requested_at' => now(),
        'user' => $requester->getKey(),
    ]);
}

it('shows officer actions on the admin audit logs page', function () {
    $admin = recordsUser(2);
    $officer = recordsUser(3, ['first_name' => 'Alice', 'last_name' => 'Anderson']);

    auditAction($officer, description: 'Officer sent a request');

    $this->actingAs($admin)
        ->get(route('admin.audit-logs.index'))
        ->assertOk()
        ->assertSee('Officer Actions')
        ->assertSee($officer->user_email)
        ->assertSee('Officer sent a request');
});

it('limits the audit logs to log in/out, requests and document generation', function () {
    $admin = recordsUser(2);
    $officer = recordsUser(3);

    auditAction($officer, ActionLogger::CATEGORY_AUTH, 'Officer logged in');
    auditAction($officer, ActionLogger::CATEGORY_AUTH, 'Officer changed password', 'password_changed');
    auditAction($officer, ActionLogger::CATEGORY_REQUESTS, 'Officer request accepted', 'request_accepted');
    auditAction($officer, ActionLogger::CATEGORY_DOCUMENT_GENERATION, 'Officer partial form', 'partial_form_generated');
    auditAction($officer, ActionLogger::CATEGORY_SETTINGS, 'Officer changed a setting', 'settings_updated');
    auditAction($officer, ActionLogger::CATEGORY_SUPPORT, 'Officer opened a ticket', 'support_ticket_created');

    $response = $this->actingAs($admin)->get(route('admin.audit-logs.index'))->assertOk();
    $response->assertSee('Officer logged in')
        ->assertSee('Officer request accepted')
        ->assertSee('Officer partial form')
        ->assertDontSee('Officer changed password')
        ->assertDontSee('Officer changed a setting')
        ->assertDontSee('Officer opened a ticket');

    expect($response->viewData('categories'))->toBe([
        ActionLogger::CATEGORY_AUTH => 'Log In / Log Out',
        ActionLogger::CATEGORY_REQUESTS => 'Requests',
        ActionLogger::CATEGORY_DOCUMENT_GENERATION => 'Document Generation',
    ]);

    // An out-of-scope category in the URL can't widen the scope.
    $this->actingAs($admin)
        ->get(route('admin.audit-logs.index', ['category' => ActionLogger::CATEGORY_SETTINGS]))
        ->assertOk()
        ->assertDontSee('Officer changed a setting');

    $this->actingAs($admin)->get(route('admin.audit-logs.export.json'))
        ->assertOk()
        ->assertJsonCount(3);
});

it('logs request_sent for the signed-in sender and request_accepted for the approver', function () {
    $officer = recordsUser(3);
    $president = recordsUser(3);

    $this->actingAs($officer);
    $request = auditRequest($officer);

    $sent = ActionLog::query()->where('action', 'request_sent')->sole();
    expect($sent->category)->toBe(ActionLogger::CATEGORY_REQUESTS)
        ->and((int) $sent->user_id)->toBe((int) $officer->getKey())
        ->and($sent->meta['request_id'])->toBe((int) $request->getKey());

    $this->actingAs($president);
    Approval::query()->create([
        'request' => $request->getKey(),
        'admin' => $president->getKey(),
        'stage' => 'president',
        'approved_at' => now(),
        'is_rejected' => false,
    ]);

    $accepted = ActionLog::query()->where('action', 'request_accepted')->sole();
    expect((int) $accepted->user_id)->toBe((int) $president->getKey())
        ->and($accepted->meta)->toMatchArray(['request_id' => (int) $request->getKey(), 'stage' => 'president']);
});

it('does not log rejected approvals or requests created without a signed-in user', function () {
    $officer = recordsUser(3);

    $request = auditRequest($officer);

    $this->actingAs(recordsUser(2));
    Approval::query()->create([
        'request' => $request->getKey(),
        'approved_at' => now(),
        'is_rejected' => true,
    ]);

    expect(ActionLog::query()->whereIn('action', ['request_sent', 'request_accepted'])->count())->toBe(0);
});

it('excludes admin and superadmin actions from the audit logs', function () {
    $superadmin = recordsUser(1);
    $admin = recordsUser(2);
    $officer = recordsUser(3);

    auditAction($superadmin, description: 'Superadmin-only action');
    auditAction($admin, description: 'Admin-only action');
    auditAction($officer, description: 'Officer action');

    $this->actingAs($admin)
        ->get(route('admin.audit-logs.index'))
        ->assertOk()
        ->assertSee('Officer action')
        ->assertDontSee('Superadmin-only action')
        ->assertDontSee('Admin-only action');

    $this->actingAs($admin)->get(route('admin.audit-logs.export.json'))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['user_email' => $officer->user_email]);
});

it('filters audit logs by user and category', function () {
    $admin = recordsUser(2);
    $alice = recordsUser(3, ['first_name' => 'Alice', 'last_name' => 'Anderson']);
    $bob = recordsUser(3, ['first_name' => 'Bob', 'last_name' => 'Baker']);

    auditAction($alice, ActionLogger::CATEGORY_REQUESTS, 'Alice sent a request');
    auditAction($alice, ActionLogger::CATEGORY_DOCUMENT_GENERATION, 'Alice downloaded a document');
    auditAction($bob, ActionLogger::CATEGORY_REQUESTS, 'Bob sent a request');

    $this->actingAs($admin)
        ->get(route('admin.audit-logs.index', ['user' => 'Alice']))
        ->assertOk()
        ->assertSee('Alice sent a request')
        ->assertDontSee('Bob sent a request');

    $this->actingAs($admin)
        ->get(route('admin.audit-logs.index', ['category' => ActionLogger::CATEGORY_REQUESTS]))
        ->assertOk()
        ->assertSee('Alice sent a request')
        ->assertSee('Bob sent a request')
        ->assertDontSee('Alice downloaded a document');
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

    auditAction($inOrg, description: 'In-org action');
    auditAction($outOrg, description: 'Out-of-org action');

    $this->actingAs($admin)
        ->get(route('admin.audit-logs.index', ['org_id' => $org->organization_id]))
        ->assertOk()
        ->assertSee('In-org action')
        ->assertDontSee('Out-of-org action');
});

it('exposes audit log exports that respect filters', function () {
    $admin = recordsUser(2);
    $alice = recordsUser(3, ['first_name' => 'Alice', 'last_name' => 'Anderson']);
    $bob = recordsUser(3, ['first_name' => 'Bob', 'last_name' => 'Baker']);
    auditAction($alice);
    auditAction($bob);

    $this->actingAs($admin)->get(route('admin.audit-logs.export.json', ['user' => 'Alice']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['user_email' => $alice->user_email]);
    $this->actingAs($admin)->get(route('admin.audit-logs.export.pdf'))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
    $this->actingAs($admin)->get(route('admin.audit-logs.export.xlsx'))->assertOk();
});

it('searches only officer accounts for the user filter', function () {
    $admin = recordsUser(2, ['first_name' => 'Carol', 'last_name' => 'Admin']);
    $officer = recordsUser(3, ['first_name' => 'Carol', 'last_name' => 'Officer']);

    $this->actingAs($admin)
        ->getJson(route('admin.audit-logs.officers', ['q' => 'Carol']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['user_email' => $officer->user_email])
        ->assertJsonMissing(['user_email' => $admin->user_email]);
});

it('blocks non-admins from the audit logs', function () {
    $member = recordsUser(3);
    $superadmin = recordsUser(1);

    $this->actingAs($member)->get(route('admin.audit-logs.index'))->assertForbidden();
    $this->actingAs($member)->get(route('admin.audit-logs.officers', ['q' => 'ab']))->assertForbidden();
    $this->actingAs($superadmin)->get(route('admin.audit-logs.index'))->assertForbidden();
});

it('still renders the superadmin action logs page', function () {
    $superadmin = recordsUser(1);
    $admin = recordsUser(2);
    auditAction($admin, description: 'Admin-only action');

    $this->actingAs($superadmin)
        ->get(route('superadmin.action-logs.index'))
        ->assertOk()
        ->assertSee('Administrator Actions')
        ->assertSee('Admin-only action');
});
