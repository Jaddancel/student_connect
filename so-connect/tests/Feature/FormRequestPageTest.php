<?php

use App\Models\Approval;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\GeneratedDocument;
use App\Models\Request as ActionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function frpOfficer(int $orgId): User
{
    $user = recordsUser(3);
    DB::table('organization_officers')->insert([
        'role' => 'officer',
        'organization' => $orgId,
        'user' => (int) $user->getKey(),
        'yearterm' => null,
        'member_since' => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    return $user;
}

function frpForm(array $overrides = [], string $fieldKey = 'purpose'): Form
{
    $form = Form::create(array_merge([
        'name' => 'Facility Request',
        'route_name' => 'facility-request',
        'is_active' => true,
        'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => [
            'html' => '<p><span class="field-token" data-field="'.$fieldKey.'" contenteditable="false">Value</span></p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
        ],
    ], $overrides));

    FormDescription::create([
        'form_id' => $form->id, 'field_key' => $fieldKey, 'field_label' => ucfirst($fieldKey),
        'field_type' => 'text', 'is_required' => true, 'field_order' => 1,
    ]);

    return $form;
}

it('lists a form\'s pending and decided requests on its own page', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $org = recordsOrganization('Requests Org');
    $officer = frpOfficer((int) $org->getKey());
    $form = frpForm();

    // Two submissions → two pending requests on this form's queue.
    foreach (['Sports fest', 'Seminar'] as $purpose) {
        $this->actingAs($officer)
            ->post(route('forms.render.submit', $form->route_name), ['purpose' => $purpose])
            ->assertSessionHasNoErrors();
    }

    $admin = recordsUser(2);
    $requestIds = ActionRequest::query()->where('form_id', $form->id)->pluck('request_id');
    expect($requestIds)->toHaveCount(2);

    // Decide one so both tables have rows.
    app(\App\Services\RequestApprovalService::class)->reject(
        ActionRequest::query()->findOrFail($requestIds->first()),
        (int) $admin->getKey(),
        'Missing details',
    );

    $this->actingAs($admin)
        ->get(route('admin.form-requests.index', $form))
        ->assertOk()
        ->assertSee('Facility Request Requests')
        ->assertSee('Requests Org')
        ->assertSee('Missing details');

    // Type-3 users are kept out by the admin middleware.
    $this->actingAs($officer)->get(route('admin.form-requests.index', $form))->assertForbidden();
});

it('approves a plain-form request from its page and generates the document', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $org = recordsOrganization('Requests Org');
    $officer = frpOfficer((int) $org->getKey());
    $form = frpForm();

    $this->actingAs($officer)
        ->post(route('forms.render.submit', $form->route_name), ['purpose' => 'Sports fest'])
        ->assertSessionHasNoErrors();

    $actionRequest = ActionRequest::query()->where('form_id', $form->id)->firstOrFail();
    $admin = recordsUser(2);

    $this->actingAs($admin)
        ->post(route('admin.form-requests.decide', [$form, $actionRequest->request_id]), ['decision' => 'approve'])
        ->assertRedirect(route('admin.form-requests.index', $form));

    $approval = Approval::query()->where('request', $actionRequest->request_id)->firstOrFail();
    expect($approval->is_rejected)->toBeFalse();

    $generated = GeneratedDocument::query()
        ->where('request_id', $actionRequest->request_id)
        ->firstOrFail();
    expect($generated->status)->toBe('generated')
        ->and((int) $generated->approval_id)->toBe((int) $approval->getKey());
    Storage::disk('public')->assertExists($generated->pdf_path);
});

it('rejects with a reason from the form request page', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $org = recordsOrganization('Requests Org');
    $officer = frpOfficer((int) $org->getKey());
    $form = frpForm();

    $this->actingAs($officer)
        ->post(route('forms.render.submit', $form->route_name), ['purpose' => 'Sports fest'])
        ->assertSessionHasNoErrors();

    $actionRequest = ActionRequest::query()->where('form_id', $form->id)->firstOrFail();

    $this->actingAs(recordsUser(2))
        ->post(route('admin.form-requests.decide', [$form, $actionRequest->request_id]), [
            'decision' => 'reject',
            'rejection_reason' => 'Wrong semester',
        ])
        ->assertRedirect(route('admin.form-requests.index', $form));

    $approval = Approval::query()->where('request', $actionRequest->request_id)->firstOrFail();
    expect($approval->is_rejected)->toBeTrue()
        ->and($approval->rejection_reason)->toBe('Wrong semester');
    expect(GeneratedDocument::query()->where('request_id', $actionRequest->request_id)->exists())->toBeFalse();
});

it('requires president approval before admin enrollment for a membership request', function () {
    $homeOrg = recordsOrganization('Home Org');
    $targetOrg = recordsOrganization('Target Org');
    $applicant = frpOfficer((int) $homeOrg->getKey());

    $president = recordsUser(3);
    DB::table('organization_officers')->insert([
        'role' => 'president',
        'organization' => $targetOrg->getKey(),
        'user' => (int) $president->getKey(),
        'yearterm' => null,
        'member_since' => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    $form = frpForm([
        'name' => 'Org Membership',
        'route_name' => 'org-membership',
        'system_function' => 'membership_registration',
    ], 'organization_id');

    FormDescription::create([
        'form_id' => $form->id, 'field_key' => 'position', 'field_label' => 'Position',
        'field_type' => 'position-select', 'is_required' => true, 'field_order' => 2,
    ]);

    $this->actingAs($applicant)
        ->post(route('forms.render.submit', $form->route_name), [
            'organization_id' => (string) $targetOrg->getKey(),
            'position' => 'Secretary',
        ])
        ->assertSessionHasNoErrors();

    $actionRequest = ActionRequest::query()
        ->where('form_id', $form->id)->where('action_type', 1)->firstOrFail();

    $this->actingAs($president)
        ->get(route('membership-requests'))
        ->assertOk()
        ->assertSee('Membership Requests')
        ->assertSee('Membership Registration');

    $admin = recordsUser(2);
    expect(fn () => app(\App\Services\RequestApprovalService::class)->approve(
        $actionRequest,
        (int) $admin->getKey(),
        'admin',
    ))->toThrow(\LogicException::class);

    $this->actingAs($president)
        ->postJson('/api/requests/'.$actionRequest->getKey().'/decision', ['decision' => 'approve'])
        ->assertOk();

    $memberRow = DB::table('organization_officers')
        ->where('user', $applicant->getKey())
        ->where('organization', $targetOrg->getKey())
        ->first();
    expect($memberRow)->toBeNull();

    app(\App\Services\RequestApprovalService::class)->approve(
        $actionRequest,
        (int) $admin->getKey(),
        'admin',
    );

    $memberRow = DB::table('organization_officers')
        ->where('user', $applicant->getKey())
        ->where('organization', $targetOrg->getKey())
        ->first();
    expect($memberRow)->not->toBeNull()
        ->and($memberRow->role)->toBe('member')
        ->and($memberRow->position)->toBe('Secretary');
});

it('has no request page for sign-up, new-event and new-workplan bindings', function () {
    $form = frpForm(['system_function' => 'new_event', 'route_name' => 'event-bound']);

    $this->actingAs(recordsUser(2))
        ->get(route('admin.form-requests.index', $form))
        ->assertNotFound();
});

it('displays UTC request timestamps in the configured local timezone', function () {
    config(['app.display_timezone' => 'Asia/Manila']);

    $form = frpForm();
    $actionRequest = ActionRequest::query()->create([
        'action' => 'document_generation',
        'requested_at' => '2026-09-29 00:17:46',
        'user' => recordsUser(3)->getKey(),
        'action_type' => \App\Helpers\FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION,
        'form_id' => $form->getKey(),
        'payload' => [],
    ]);

    $this->actingAs(recordsUser(2))
        ->get(route('admin.form-requests.show', [$form, $actionRequest->getKey()]))
        ->assertOk()
        ->assertSee('Sep 29, 2026 08:17 AM');
});

it('never lists another form\'s requests', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $org = recordsOrganization('Requests Org');
    $officer = frpOfficer((int) $org->getKey());
    $formA = frpForm();
    $formB = frpForm(['name' => 'Equipment Request', 'route_name' => 'equipment-request'], 'items');

    $this->actingAs($officer)
        ->post(route('forms.render.submit', $formA->route_name), ['purpose' => 'Sports fest'])
        ->assertSessionHasNoErrors();

    $actionRequest = ActionRequest::query()->where('form_id', $formA->id)->firstOrFail();

    // The request belongs to form A — form B's page must not expose it.
    $this->actingAs(recordsUser(2))
        ->get(route('admin.form-requests.show', [$formB, $actionRequest->request_id]))
        ->assertNotFound();
});
