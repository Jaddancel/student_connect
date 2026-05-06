<?php

use App\Helpers\FormTemplateHelper;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\GeneratedDocument;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Request as ActionRequest;
use App\Models\Template;
use App\Models\User;
use App\Services\DocumentGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createDocumentWorkflowUserWithProfile(string $email): User
{
    $addressId = DB::table('profile_addresses')->insertGetId([
        'country' => 'Philippines',
        'province' => 'Cebu',
        'town' => 'Cebu City',
        'barangay' => 'Lahug',
    ]);

    $profile = App\Models\Profile::query()->create([
        'first_name' => 'Test',
        'last_name' => 'User',
        'middle_name' => 'T',
        'occupation' => 'Student',
        'address' => $addressId,
    ]);

    return User::query()->create([
        'user_email' => $email,
        'user_password' => 'password',
        'user_type' => 3,
        'profile' => $profile->getKey(),
    ]);
}

function assignDocumentWorkflowOfficerRole(User $user, int $organizationId, string $role = 'officer'): void
{
    $member = Member::query()->create([
        'organization' => $organizationId,
        'approval' => null,
        'user' => (int) $user->getKey(),
        'member_since' => now(),
    ]);

    DB::table('organization_officers')->insert([
        'role' => $role,
        'organization' => $organizationId,
        'member' => $member->getKey(),
        'yearterm' => null,
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);
}

it('shows form upload requests in the approval queue for presidents', function () {
    $president = createUserWithProfile('doc-president@example.test');

    $organization = Organization::query()->create([
        'organization_type' => 1,
        'detail' => null,
    ]);

        assignDocumentWorkflowOfficerRole($president, (int) $organization->getKey(), 'president');

        $requester = createDocumentWorkflowUserWithProfile('doc-requester@example.test');

    $form = Form::query()->create([
        'name' => 'Clearance Form',
        'description_text' => 'Clearance request form',
        'organization_id' => (int) $organization->getKey(),
        'created_by' => (int) $requester->getKey(),
        'is_active' => true,
        'is_published' => true,
    ]);

    $template = Template::query()->create([
        'form_id' => (int) $form->getKey(),
        'organization_id' => (int) $organization->getKey(),
        'uploaded_by' => (int) $president->getKey(),
        'template_name' => 'Clearance Template',
        'docx_path' => 'form-templates/clearance-template.docx',
        'version' => 1,
        'is_active' => true,
    ]);

    ActionRequest::query()->create([
        'action' => FormTemplateHelper::encodeFormUploadAction(
            (int) $organization->getKey(),
            (int) $form->getKey(),
            (int) $template->getKey(),
            (int) $requester->getKey(),
        ),
        'requested_at' => now(),
        'user' => (int) $requester->getKey(),
        'action_type' => FormTemplateHelper::ACTION_TYPE_FORM_UPLOAD,
    ]);

    $this->actingAs($president)
        ->get('/approval-requests')
        ->assertOk()
        ->assertSee('Form Upload Request')
        ->assertSee('Clearance Template');
});

it('forbids officers from approving form upload requests', function () {
    $officer = createUserWithProfile('doc-officer@example.test');

    $organization = Organization::query()->create([
        'organization_type' => 1,
        'detail' => null,
    ]);

        assignDocumentWorkflowOfficerRole($officer, (int) $organization->getKey(), 'officer');

    $requester = createUserWithProfile('doc-requester-officer@example.test');
    $form = Form::query()->create([
        'name' => 'Enrollment Form',
        'description_text' => 'Enrollment request form',
        'organization_id' => (int) $organization->getKey(),
        'created_by' => (int) $requester->getKey(),
        'is_active' => true,
        'is_published' => true,
    ]);

    $template = Template::query()->create([
        'form_id' => (int) $form->getKey(),
        'organization_id' => (int) $organization->getKey(),
        'uploaded_by' => (int) $officer->getKey(),
        'template_name' => 'Enrollment Template',
        'docx_path' => 'form-templates/enrollment-template.docx',
        'version' => 1,
        'is_active' => true,
    ]);

    $actionRequest = ActionRequest::query()->create([
        'action' => FormTemplateHelper::encodeFormUploadAction(
            (int) $organization->getKey(),
            (int) $form->getKey(),
            (int) $template->getKey(),
            (int) $requester->getKey(),
        ),
        'requested_at' => now(),
        'user' => (int) $requester->getKey(),
        'action_type' => FormTemplateHelper::ACTION_TYPE_FORM_UPLOAD,
    ]);

    $this->actingAs($officer)
        ->postJson('/api/requests/'.$actionRequest->getKey().'/decision', [
            'decision' => 'approve',
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'You are not authorized to decide this request.');

    $this->assertDatabaseMissing('approvals', [
        'request' => $actionRequest->getKey(),
    ]);
});

it('allows presidents to approve form upload requests', function () {
    $president = createUserWithProfile('doc-president-approve@example.test');

    $organization = Organization::query()->create([
        'organization_type' => 1,
        'detail' => null,
    ]);

        assignDocumentWorkflowOfficerRole($president, (int) $organization->getKey(), 'president');

    $requester = createUserWithProfile('doc-requester-president@example.test');
    $form = Form::query()->create([
        'name' => 'Clearance Form',
        'description_text' => 'Clearance request form',
        'organization_id' => (int) $organization->getKey(),
        'created_by' => (int) $requester->getKey(),
        'is_active' => true,
        'is_published' => true,
    ]);

    $template = Template::query()->create([
        'form_id' => (int) $form->getKey(),
        'organization_id' => (int) $organization->getKey(),
        'uploaded_by' => (int) $president->getKey(),
        'template_name' => 'Clearance Template',
        'docx_path' => 'form-templates/clearance-template.docx',
        'version' => 1,
        'is_active' => true,
    ]);

    $actionRequest = ActionRequest::query()->create([
        'action' => FormTemplateHelper::encodeFormUploadAction(
            (int) $organization->getKey(),
            (int) $form->getKey(),
            (int) $template->getKey(),
            (int) $requester->getKey(),
        ),
        'requested_at' => now(),
        'user' => (int) $requester->getKey(),
        'action_type' => FormTemplateHelper::ACTION_TYPE_FORM_UPLOAD,
    ]);

    $this->actingAs($president)
        ->postJson('/api/requests/'.$actionRequest->getKey().'/decision', [
            'decision' => 'approve',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Request approved successfully.');

    $this->assertDatabaseHas('approvals', [
        'request' => $actionRequest->getKey(),
        'admin' => $president->getKey(),
        'is_rejected' => 0,
    ]);
});

it('returns a generated document id when a generation request is approved', function () {
    $president = createUserWithProfile('doc-generation-president@example.test');

    $organization = Organization::query()->create([
        'organization_type' => 1,
        'detail' => null,
    ]);

        assignDocumentWorkflowOfficerRole($president, (int) $organization->getKey(), 'president');

    $requester = createUserWithProfile('doc-generation-requester@example.test');

    $form = Form::query()->create([
        'name' => 'Transcript Request',
        'description_text' => 'Transcript request form',
        'organization_id' => (int) $organization->getKey(),
        'created_by' => (int) $requester->getKey(),
        'is_active' => true,
        'is_published' => true,
    ]);

    $template = Template::query()->create([
        'form_id' => (int) $form->getKey(),
        'organization_id' => (int) $organization->getKey(),
        'uploaded_by' => (int) $president->getKey(),
        'template_name' => 'Transcript Template',
        'docx_path' => 'form-templates/transcript-template.docx',
        'version' => 1,
        'is_active' => true,
    ]);

    $submission = FormSubmission::query()->create([
        'form_id' => (int) $form->getKey(),
        'organization_id' => (int) $organization->getKey(),
        'submitted_by' => (int) $requester->getKey(),
        'payload' => [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ],
        'submitted_at' => now(),
    ]);

    $actionRequest = ActionRequest::query()->create([
        'action' => FormTemplateHelper::encodeDocumentGenerationAction(
            (int) $organization->getKey(),
            (int) $submission->getKey(),
            (int) $form->getKey(),
            (int) $requester->getKey(),
        ),
        'requested_at' => now(),
        'user' => (int) $requester->getKey(),
        'action_type' => FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION,
    ]);

    $generatedDocument = GeneratedDocument::query()->create([
        'form_submission_id' => (int) $submission->getKey(),
        'template_id' => (int) $template->getKey(),
        'request_id' => (int) $actionRequest->getKey(),
        'document_id' => null,
        'generated_by' => (int) $president->getKey(),
        'docx_path' => 'generated-documents/transcript.docx',
        'pdf_path' => 'generated-documents/transcript.pdf',
        'status' => 'generated',
        'failure_reason' => null,
        'generated_at' => now(),
    ]);

    $generationService = new class($generatedDocument) extends DocumentGenerationService {
        public function __construct(private GeneratedDocument $generatedDocument)
        {
        }

        public function generateFromApprovedRequest(ActionRequest $request, int $generatedByUserId): GeneratedDocument
        {
            return $this->generatedDocument;
        }
    };

    $this->app->instance(DocumentGenerationService::class, $generationService);

    $this->actingAs($president)
        ->postJson('/api/requests/'.$actionRequest->getKey().'/decision', [
            'decision' => 'approve',
        ])
        ->assertOk()
        ->assertJsonPath('generated_document_id', $generatedDocument->getKey());

    $this->assertDatabaseHas('approvals', [
        'request' => $actionRequest->getKey(),
        'admin' => $president->getKey(),
        'is_rejected' => 0,
    ]);
});

it('downloads generated pdfs for the submitting user', function () {
    Storage::fake('public');

    $owner = createUserWithProfile('doc-owner@example.test');

    $organization = Organization::query()->create([
        'organization_type' => 1,
        'detail' => null,
    ]);

        assignDocumentWorkflowOfficerRole($owner, (int) $organization->getKey(), 'officer');

    $form = Form::query()->create([
        'name' => 'Clearance Form',
        'description_text' => 'Clearance request form',
        'organization_id' => (int) $organization->getKey(),
        'created_by' => (int) $owner->getKey(),
        'is_active' => true,
        'is_published' => true,
    ]);

    $template = Template::query()->create([
        'form_id' => (int) $form->getKey(),
        'organization_id' => (int) $organization->getKey(),
        'uploaded_by' => (int) $owner->getKey(),
        'template_name' => 'Clearance Template',
        'docx_path' => 'form-templates/clearance-template.docx',
        'version' => 1,
        'is_active' => true,
    ]);

    $submission = FormSubmission::query()->create([
        'form_id' => (int) $form->getKey(),
        'organization_id' => (int) $organization->getKey(),
        'submitted_by' => (int) $owner->getKey(),
        'payload' => [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ],
        'submitted_at' => now(),
    ]);

    $pdfPath = 'generated-documents/submission.pdf';
    Storage::disk('public')->put($pdfPath, '%PDF-1.4 test file');

    $generatedDocument = GeneratedDocument::query()->create([
        'form_submission_id' => (int) $submission->getKey(),
        'template_id' => (int) $template->getKey(),
        'request_id' => null,
        'document_id' => null,
        'generated_by' => (int) $owner->getKey(),
        'docx_path' => 'generated-documents/submission.docx',
        'pdf_path' => $pdfPath,
        'status' => 'generated',
        'failure_reason' => null,
        'generated_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('generated-documents.download', ['generatedDocumentId' => $generatedDocument->getKey()]))
        ->assertDownload('submission.pdf');
});

it('creates a document access request when another user tries to download a generated pdf', function () {
    Storage::fake('public');

    $owner = createUserWithProfile('doc-owner-cross-org@example.test');
    $requester = createUserWithProfile('doc-requester-cross-org@example.test');

    $organization = Organization::query()->create([
        'organization_type' => 1,
        'detail' => null,
    ]);

        assignDocumentWorkflowOfficerRole($owner, (int) $organization->getKey(), 'officer');

    $form = Form::query()->create([
        'name' => 'Clearance Form',
        'description_text' => 'Clearance request form',
        'organization_id' => (int) $organization->getKey(),
        'created_by' => (int) $owner->getKey(),
        'is_active' => true,
        'is_published' => true,
    ]);

    $template = Template::query()->create([
        'form_id' => (int) $form->getKey(),
        'organization_id' => (int) $organization->getKey(),
        'uploaded_by' => (int) $owner->getKey(),
        'template_name' => 'Clearance Template',
        'docx_path' => 'form-templates/clearance-template.docx',
        'version' => 1,
        'is_active' => true,
    ]);

    $submission = FormSubmission::query()->create([
        'form_id' => (int) $form->getKey(),
        'organization_id' => (int) $organization->getKey(),
        'submitted_by' => (int) $owner->getKey(),
        'payload' => [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ],
        'submitted_at' => now(),
    ]);

    $pdfPath = 'generated-documents/cross-org-submission.pdf';
    Storage::disk('public')->put($pdfPath, '%PDF-1.4 test file');

    $generatedDocument = GeneratedDocument::query()->create([
        'form_submission_id' => (int) $submission->getKey(),
        'template_id' => (int) $template->getKey(),
        'request_id' => null,
        'document_id' => null,
        'generated_by' => (int) $owner->getKey(),
        'docx_path' => 'generated-documents/cross-org-submission.docx',
        'pdf_path' => $pdfPath,
        'status' => 'generated',
        'failure_reason' => null,
        'generated_at' => now(),
    ]);

    $this->actingAs($requester)
        ->get(route('generated-documents.download', ['generatedDocumentId' => $generatedDocument->getKey()]))
        ->assertRedirect();

    $this->assertDatabaseHas('requests', [
        'action_type' => FormTemplateHelper::ACTION_TYPE_DOCUMENT_ACCESS,
        'action' => FormTemplateHelper::encodeDocumentAccessAction(
            (int) $organization->getKey(),
            (int) $generatedDocument->getKey(),
            (int) $requester->getKey(),
        ),
        'user' => (int) $requester->getKey(),
    ]);
});
