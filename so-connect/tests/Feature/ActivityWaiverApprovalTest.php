<?php

use App\Forms\FieldType;
use App\Forms\SystemFunction;
use App\Helpers\FormTemplateHelper;
use App\Models\ActivityWaiverReview;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Models\Request as ActionRequest;
use App\Services\ActivityWaiverApprovalGate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array{0:ActionRequest,1:FormSubmission} */
function activityWaiverRequest(array $waivers, array $validations = []): array
{
    $officer = recordsUser(3);
    $form = Form::query()->firstOrCreate([
        'route_name' => 'activity-waiver-gate',
    ], [
        'name' => 'New Event',
        'system_function' => SystemFunction::NEW_EVENT,
        'is_active' => true,
        'is_published' => true,
    ]);
    FormDescription::query()->updateOrCreate([
        'form_id' => $form->getKey(),
        'field_key' => 'waiver',
    ], [
        'field_label' => 'Signed Waiver',
        'field_type' => FieldType::WAIVER_SCAN,
        'is_required' => true,
        'field_order' => 1,
    ]);

    $payload = ['waiver' => $waivers];
    if ($validations !== []) {
        $payload['_waiver_validation'] = ['waiver' => $validations];
    }

    $submission = FormSubmission::query()->create([
        'form_id' => $form->getKey(),
        'submitted_by' => $officer->getKey(),
        'payload' => $payload,
        'submitted_at' => now(),
    ]);
    $actionRequest = ActionRequest::query()->create([
        'action' => 'activity',
        'action_type' => FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION,
        'user' => $officer->getKey(),
        'requested_at' => now(),
        'form_id' => $form->getKey(),
        'payload' => [
            'form_id' => $form->getKey(),
            'submission_id' => $submission->getKey(),
        ],
    ]);

    return [$actionRequest, $submission];
}

it('classifies missing failed unvalidated and valid activity waivers', function () {
    $gate = app(ActivityWaiverApprovalGate::class);

    [$missing] = activityWaiverRequest([]);
    expect($gate->evaluate($missing))->toMatchArray([
        'status' => 'missing', 'can_approve' => false, 'can_review' => false,
    ]);

    [$failed] = activityWaiverRequest(['waivers/failed.png'], [['valid' => false]]);
    expect($gate->evaluate($failed))->toMatchArray([
        'status' => 'failed', 'can_approve' => false, 'can_review' => false,
        'failed_count' => 1,
    ]);

    [$unvalidated] = activityWaiverRequest(['waivers/unvalidated.png']);
    expect($gate->evaluate($unvalidated))->toMatchArray([
        'status' => 'unvalidated', 'can_approve' => false, 'can_review' => true,
        'unvalidated_count' => 1,
    ]);

    [$valid] = activityWaiverRequest(['waivers/valid.png'], [['valid' => true]]);
    expect($gate->evaluate($valid))->toMatchArray([
        'status' => 'valid', 'can_approve' => true, 'can_review' => false,
    ]);
});

it('blocks direct approval requests when the waiver gate has not passed', function (string $kind) {
    $admin = recordsUser(2);
    [$actionRequest] = match ($kind) {
        'missing' => activityWaiverRequest([]),
        'failed' => activityWaiverRequest(['waivers/failed.png'], [['valid' => false]]),
        default => activityWaiverRequest(['waivers/unvalidated.png']),
    };

    $this->actingAs($admin)
        ->from(route('admin.activity-requests.show', $actionRequest->getKey()))
        ->post(route('admin.activity-requests.decide', $actionRequest->getKey()), ['decision' => 'approve'])
        ->assertRedirect(route('admin.activity-requests.show', $actionRequest->getKey()))
        ->assertSessionHasErrors('waiver');

    $this->assertDatabaseMissing('approvals', ['request' => $actionRequest->getKey()]);
    $this->assertDatabaseHas('action_logs', [
        'category' => 'waiver_review',
        'action' => 'activity_approval_blocked',
        'subject_id' => $actionRequest->getKey(),
    ]);
})->with(['missing', 'failed', 'unvalidated']);

it('allows an admin to review an unvalidated waiver and records the audit trail', function () {
    $admin = recordsUser(2);
    [$actionRequest] = activityWaiverRequest(['waivers/unvalidated.png']);

    $this->actingAs($admin)
        ->post(route('admin.activity-requests.waiver-review', $actionRequest->getKey()), [
            'decision' => ActivityWaiverReview::DECISION_APPROVED,
            'remarks' => 'Signature and event details checked against the uploaded page.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('activity_waiver_reviews', [
        'request_id' => $actionRequest->getKey(),
        'reviewer_id' => $admin->getKey(),
        'decision' => ActivityWaiverReview::DECISION_APPROVED,
    ]);
    $this->assertDatabaseHas('action_logs', [
        'category' => 'waiver_review',
        'action' => 'waiver_manually_approved',
        'subject_id' => $actionRequest->getKey(),
    ]);

    expect(app(ActivityWaiverApprovalGate::class)->evaluate($actionRequest->fresh()))->toMatchArray([
        'status' => 'manually_approved', 'can_approve' => true, 'can_review' => true,
    ]);
});

it('never permits manual approval to override a failed automatic validation', function () {
    $admin = recordsUser(2);
    [$actionRequest] = activityWaiverRequest(['waivers/failed.png'], [['valid' => false]]);

    $this->actingAs($admin)
        ->post(route('admin.activity-requests.waiver-review', $actionRequest->getKey()), [
            'decision' => ActivityWaiverReview::DECISION_APPROVED,
        ])
        ->assertSessionHasErrors('waiver');

    $this->assertDatabaseMissing('activity_waiver_reviews', ['request_id' => $actionRequest->getKey()]);
});

it('renders waiver status and disables approval until review is complete', function () {
    $admin = recordsUser(2);
    [$actionRequest] = activityWaiverRequest(['waivers/unvalidated.png']);

    $this->actingAs($admin)
        ->get(route('admin.activity-requests.show', $actionRequest->getKey()))
        ->assertOk()
        ->assertSee('Waiver Approval Check')
        ->assertSee('Unvalidated')
        ->assertSee('aria-disabled="true"', false);

    ActivityWaiverReview::query()->create([
        'request_id' => $actionRequest->getKey(),
        'reviewer_id' => $admin->getKey(),
        'decision' => ActivityWaiverReview::DECISION_APPROVED,
        'reviewed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.activity-requests.show', $actionRequest->getKey()))
        ->assertOk()
        ->assertSee('Manually Approved')
        ->assertSee('aria-disabled="false"', false);
});
