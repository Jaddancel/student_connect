<?php

use App\Models\Approval;
use App\Models\Request as ActionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeActionRequest(int $userId): ActionRequest
{
    return ActionRequest::create([
        'action' => 'test',
        'requested_at' => now(),
        'user' => $userId,
        'action_type' => 3,
    ]);
}

it('groups member requests by status and excludes admin-user requests', function () {
    $admin = recordsUser(2);
    $member = recordsUser(3);

    $accepted = makeActionRequest($member->getKey());
    Approval::create(['request' => $accepted->getKey(), 'is_rejected' => false, 'approved_at' => now()]);

    $systemAccepted = makeActionRequest($member->getKey());
    Approval::create([
        'request' => $systemAccepted->getKey(),
        'admin' => 0,
        'is_rejected' => false,
        'approved_at' => now(),
    ]);

    $rejected = makeActionRequest($member->getKey());
    Approval::create(['request' => $rejected->getKey(), 'is_rejected' => true, 'approved_at' => now()]);

    makeActionRequest($member->getKey()); // pending (no approval)

    $adminRequest = makeActionRequest($admin->getKey()); // must be excluded
    Approval::create(['request' => $adminRequest->getKey(), 'is_rejected' => false, 'approved_at' => now()]);

    $this->actingAs($admin)->get(route('admin.request-records.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.request-records.index'))
        ->assertOk()
        ->assertSee('Accepted by the system');

    $jsonResponse = $this->actingAs($admin)->get(route('admin.request-records.export.json'))
        ->assertOk()
        ->assertJsonCount(2, 'accepted')
        ->assertJsonCount(1, 'pending')
        ->assertJsonCount(1, 'rejected');
    expect(collect($jsonResponse->json('accepted'))->pluck('accepted_by')->filter()->all())
        ->toBe(['Accepted by the system']);

    $this->actingAs($admin)->get(route('admin.request-records.export.print'))
        ->assertOk()
        ->assertSee('Accepted by the system');
});

it('exposes request record exports', function () {
    $admin = recordsUser(2);

    $this->actingAs($admin)->get(route('admin.request-records.export.json'))->assertOk();
    $this->actingAs($admin)->get(route('admin.request-records.export.print'))->assertOk();
    $this->actingAs($admin)->get(route('admin.request-records.export.xlsx'))->assertOk();
});

it('blocks non-admins from request records', function () {
    $member = recordsUser(3);

    $this->actingAs($member)->get(route('admin.request-records.index'))->assertForbidden();
});
