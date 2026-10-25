<?php

use App\Forms\SystemFunction;
use App\Helpers\MenuHelper;
use App\Models\Approval;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Request as ActionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function submittedFormsOfficer(string $organizationName = 'Submission Records Organization'): array
{
    $officer = recordsUser(3);
    $organization = recordsOrganization($organizationName);
    DB::table('organization_officers')->insert([
        'role' => 'officer',
        'organization' => $organization->getKey(),
        'user' => $officer->getKey(),
        'member_since' => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    return [$officer, $organization];
}

function submittedFormsRecord(Form $form, int $userId, int $organizationId, array $payload = []): FormSubmission
{
    return FormSubmission::query()->create([
        'form_id' => $form->getKey(),
        'organization_id' => $organizationId,
        'submitted_by' => $userId,
        'payload' => $payload,
        'submitted_at' => now(),
    ]);
}

it('lists submitted forms with event and non-event process context', function () {
    [$officer, $organization] = submittedFormsOfficer();
    $eventForm = Form::query()->create([
        'name' => 'Event Application',
        'route_name' => 'submitted-event-application',
        'system_function' => SystemFunction::NEW_EVENT,
    ]);
    $membershipForm = Form::query()->create([
        'name' => 'Membership Application',
        'route_name' => 'submitted-membership-application',
        'system_function' => SystemFunction::MEMBERSHIP_REGISTRATION,
    ]);
    $accreditationForm = Form::query()->create([
        'name' => 'Organization Accreditation',
        'route_name' => 'submitted-accreditation',
        'system_function' => SystemFunction::ORG_ACCREDITATION,
    ]);

    $eventSubmission = submittedFormsRecord(
        $eventForm,
        (int) $officer->getKey(),
        (int) $organization->getKey(),
        ['title' => 'Leadership Forum'],
    );
    submittedFormsRecord($membershipForm, (int) $officer->getKey(), (int) $organization->getKey());
    $accreditationSubmission = submittedFormsRecord(
        $accreditationForm,
        (int) $officer->getKey(),
        (int) $organization->getKey(),
    );

    $pendingRequest = ActionRequest::query()->create([
        'action' => 'pending-event',
        'requested_at' => now(),
        'user' => $officer->getKey(),
        'requested_by' => $officer->getKey(),
        'form_id' => $eventForm->getKey(),
        'organization_id' => $organization->getKey(),
        'action_type' => 3,
        'payload' => ['submission_id' => $eventSubmission->getKey()],
    ]);
    $approvedRequest = ActionRequest::query()->create([
        'action' => 'approved-accreditation',
        'requested_at' => now(),
        'user' => $officer->getKey(),
        'requested_by' => $officer->getKey(),
        'form_id' => $accreditationForm->getKey(),
        'organization_id' => $organization->getKey(),
        'action_type' => 3,
        'payload' => ['submission_id' => $accreditationSubmission->getKey()],
    ]);
    Approval::query()->create([
        'approved_at' => now(),
        'request' => $approvedRequest->getKey(),
        'is_rejected' => false,
    ]);

    $response = $this->actingAs($officer)->get(route('submitted-forms.index'));

    $response->assertOk()
        ->assertSee('All submitted forms in one place')
        ->assertSee('Leadership Forum')
        ->assertSee('Membership process')
        ->assertSee('Accreditation process')
        ->assertSee('Pending OSSD review')
        ->assertSee('Approved');

    expect($pendingRequest->exists)->toBeTrue();
});

it('filters submitted forms by process type and hides other users records', function () {
    [$officer, $organization] = submittedFormsOfficer('Filtered Forms Organization');
    $otherUser = recordsUser(3);
    $eventForm = Form::query()->create([
        'name' => 'Event Application',
        'route_name' => 'filtered-event-application',
        'system_function' => SystemFunction::NEW_EVENT,
    ]);
    $membershipForm = Form::query()->create([
        'name' => 'Membership Application',
        'route_name' => 'filtered-membership-application',
        'system_function' => SystemFunction::MEMBERSHIP_REGISTRATION,
    ]);

    submittedFormsRecord($eventForm, (int) $officer->getKey(), (int) $organization->getKey(), ['title' => 'Visible Event']);
    submittedFormsRecord($membershipForm, (int) $officer->getKey(), (int) $organization->getKey());
    submittedFormsRecord($eventForm, (int) $otherUser->getKey(), (int) $organization->getKey(), ['title' => 'Private Event']);

    $this->actingAs($officer)
        ->get(route('submitted-forms.index', ['type' => 'event']))
        ->assertOk()
        ->assertSee('Visible Event')
        ->assertDontSee('Membership process')
        ->assertDontSee('Private Event');

    $this->get(route('submitted-forms.index', ['type' => 'membership']))
        ->assertOk()
        ->assertSee('Membership process')
        ->assertDontSee('Visible Event');

    $menu = collect(MenuHelper::getMenuGroups())->firstWhere('title', 'Reports & Records');
    expect(collect($menu['items'])->firstWhere('path', '/submitted-forms')['name'])->toBe('Submitted Forms');
});

it('limits the submitted forms page to student officers', function () {
    $this->actingAs(recordsUser(2))
        ->get(route('submitted-forms.index'))
        ->assertForbidden();
});
