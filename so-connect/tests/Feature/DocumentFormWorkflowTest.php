<?php

use App\Helpers\MenuHelper;
use App\Helpers\FormTemplateHelper;
use App\Models\Form\FormDescription;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\GeneratedDocument;
use App\Models\Organization;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use App\Models\Template;
use App\Models\Template\TemplateDescription;
use App\Models\User;
use App\Services\DocumentGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

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
    DB::table('organization_officers')->insert([
        'role'          => $role,
        'organization'  => $organizationId,
        'user'          => (int) $user->getKey(),
        'yearterm'      => null,
        'member_since'  => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);
}

function createUserWithProfile(string $email): User
{
    return createDocumentWorkflowUserWithProfile($email);
}

function createTempDocx(array $placeholders): string
{
    $docxPath = tempnam(sys_get_temp_dir(), 'docx_');

    if ($docxPath === false) {
        throw new RuntimeException('Unable to create a temporary DOCX path.');
    }

    unlink($docxPath);

    $zip = new ZipArchive();
    expect($zip->open($docxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();

    $placeholderXml = collect($placeholders)
        ->map(fn (string $placeholder) => '{{'.$placeholder.'}}')
        ->map(fn (string $placeholder) => '<w:r><w:t>'.$placeholder.'</w:t></w:r>')
        ->implode('');

    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document><w:body><w:p>'.$placeholderXml.'</w:p></w:body></w:document>');
    $zip->close();

    return $docxPath;
}

if (! function_exists('documentMenuGroupsContain')) {
    function documentMenuGroupsContain(array $groups, string $itemName): bool
    {
        foreach ($groups as $group) {
            if (($group['title'] ?? null) === $itemName) {
                return true;
            }

            foreach (($group['items'] ?? []) as $item) {
                if (($item['name'] ?? null) === $itemName) {
                    return true;
                }

                foreach (($item['subItems'] ?? []) as $subItem) {
                    if (($subItem['name'] ?? null) === $itemName) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}

it('limits form management routes to superadmins', function () {
    $member = createDocumentWorkflowUserWithProfile('doc-member-form-access@example.test');

    $this->actingAs($member)
        ->get('/forms/manage')
        ->assertForbidden();

    $superAdmin = User::query()->create([
        'user_email' => 'doc-superadmin-form-access@example.test',
        'user_password' => 'password',
        'user_type' => 1,
        'profile' => null,
    ]);

    $this->actingAs($superAdmin)
        ->get('/forms/manage')
        ->assertOk()
        ->assertSee('Manage Document Forms')
            ->assertSee('value="president"', false)
            ->assertSee('Role Level');
});

it('shows form management links only to superadmins in the menu', function () {
    $member = createDocumentWorkflowUserWithProfile('doc-member-menu@example.test');

    $this->actingAs($member);

    $memberGroups = MenuHelper::getMenuGroups();

    expect(documentMenuGroupsContain($memberGroups, 'Manage Document Forms'))->toBeFalse()
        ->and(documentMenuGroupsContain($memberGroups, 'My Documents'))->toBeTrue();

    $superAdmin = User::query()->create([
        'user_email' => 'doc-superadmin-menu@example.test',
        'user_password' => 'password',
        'user_type' => 1,
        'profile' => null,
    ]);

    $this->actingAs($superAdmin);

    $superAdminGroups = MenuHelper::getMenuGroups();

    expect(documentMenuGroupsContain($superAdminGroups, 'Manage Document Forms'))->toBeTrue()
        ->and(documentMenuGroupsContain($superAdminGroups, 'Upload Documents'))->toBeFalse();
});

it('allows creating a form for all organizations', function () {
    Storage::fake('public');

    $superAdmin = User::query()->create([
        'user_email' => 'doc-superadmin-global-form@example.test',
        'user_password' => 'password',
        'user_type' => 1,
        'profile' => null,
    ]);

    $requestType = app(App\Services\RequestTypeService::class)->resolveSystemType(
        App\Models\RequestType::SYSTEM_KEY_FORM_GENERATION,
        'Global Form Request',
        App\Models\RequestType::CATEGORY_ORGANIZATION,
        (int) $superAdmin->getKey(),
    );

    $docxPath = createTempDocx(['all_members']);

    try {
        $this->actingAs($superAdmin)
            ->get('/forms/manage')
            ->assertOk()
            ->assertSee('All organizations');

        $this->actingAs($superAdmin)
            ->post('/forms/manage', [
                'name' => 'Global Form',
                'description_text' => 'Available across all organizations',
                'sidebar_group' => 'president',
                'request_type_id' => (int) $requestType->getKey(),
                'organization_id' => '',
                'is_published' => 1,
                'template_file' => new UploadedFile($docxPath, 'global-form.docx', null, null, true),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('forms', [
            'name' => 'Global Form',
            'organization_id' => null,
            'sidebar_group' => 'president',
        ]);

        $this->actingAs($superAdmin);

        $menuGroups = MenuHelper::getMenuGroups();

        expect(documentMenuGroupsContain($menuGroups, RequestType::categoryLabelForContext(RequestType::CATEGORY_ORGANIZATION, 'forms')))->toBeTrue()
            ->and(documentMenuGroupsContain($menuGroups, 'Available Forms'))->toBeFalse()
            ->and(documentMenuGroupsContain($menuGroups, 'Global Form'))->toBeTrue();
    } finally {
        @unlink($docxPath);
    }
});

it('maps request type categories to matching sidebar buckets', function () {
    Storage::fake('public');

    $superAdmin = User::query()->create([
        'user_email' => 'doc-superadmin-sidebar-mapping@example.test',
        'user_password' => 'password',
        'user_type' => 1,
        'profile' => null,
    ]);

    $cases = [
        [RequestType::CATEGORY_ORGANIZATION, 'Organization Request Form', 'mapping_organization_request'],
        [RequestType::CATEGORY_EVENT, 'Event Request Form', 'mapping_event_request'],
        [RequestType::CATEGORY_ROLE_SECURITY, 'Role Security Request Form', 'mapping_role_security_request'],
    ];

    foreach ($cases as [$category, $requestTypeName, $systemKey]) {
        $docxPath = createTempDocx(['all_members']);

        $requestType = RequestType::query()->create([
            'system_key' => $systemKey,
            'name' => $requestTypeName,
            'category' => $category,
            'created_by' => (int) $superAdmin->getKey(),
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin)
            ->post('/forms/manage', [
                'name' => $requestTypeName,
                'description_text' => 'Category bucket mapping test',
                'sidebar_group' => 'president',
                'request_type_id' => (int) $requestType->getKey(),
                'organization_id' => '',
                'is_published' => 1,
                'template_file' => new UploadedFile($docxPath, 'sidebar-bucket.docx', null, null, true),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('forms', [
            'name' => $requestTypeName,
            'organization_id' => null,
            'sidebar_group' => 'president',
        ]);

        @unlink($docxPath);
    }

    $this->actingAs($superAdmin);

    $menuGroups = MenuHelper::getMenuGroups();

    expect(documentMenuGroupsContain($menuGroups, RequestType::categoryLabelForContext(RequestType::CATEGORY_ORGANIZATION, 'forms')))->toBeTrue()
        ->and(documentMenuGroupsContain($menuGroups, RequestType::categoryLabelForContext(RequestType::CATEGORY_EVENT, 'forms')))->toBeTrue()
        ->and(documentMenuGroupsContain($menuGroups, RequestType::categoryLabelForContext(RequestType::CATEGORY_ROLE_SECURITY, 'forms')))->toBeTrue()
        ->and(documentMenuGroupsContain($menuGroups, 'Available Forms'))->toBeFalse();
});

it('keeps non-membership requests out of the membership requests page', function () {
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
        ->get('/membership-requests')
        ->assertOk()
        ->assertSee('Membership Requests')
        ->assertDontSee('Clearance Template');
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

it('renders and submits numbered multiple input fields as arrays', function () {
    $president = createDocumentWorkflowUserWithProfile('doc-multiple-input-president@example.test');

    $organization = Organization::query()->create([
        'organization_type' => 1,
        'detail' => null,
    ]);

    assignDocumentWorkflowOfficerRole($president, (int) $organization->getKey(), 'president');

    $requestType = app(App\Services\RequestTypeService::class)->resolveSystemType(
        RequestType::SYSTEM_KEY_FORM_GENERATION,
        'Multi Input Form Request',
        RequestType::CATEGORY_ORGANIZATION,
        (int) $president->getKey(),
    );

    $form = Form::query()->create([
        'name' => 'Team Roster Form',
        'description_text' => 'Roster request form',
        'request_type_id' => (int) $requestType->getKey(),
        'organization_id' => (int) $organization->getKey(),
        'created_by' => (int) $president->getKey(),
        'is_active' => true,
        'is_published' => true,
    ]);

    $field = FormDescription::query()->create([
        'form_id' => (int) $form->getKey(),
        'field_key' => 'team_member',
        'field_label' => 'Team Member',
        'field_type' => 'multipleInputs',
        'is_required' => true,
        'field_order' => 0,
        'placeholder_hint' => '{{team_member#}}',
        'field_options' => null,
    ]);

    $template = Template::query()->create([
        'form_id' => (int) $form->getKey(),
        'organization_id' => (int) $organization->getKey(),
        'uploaded_by' => (int) $president->getKey(),
        'template_name' => 'Team Roster Template',
        'docx_path' => 'form-templates/team-roster-template.docx',
        'version' => 1,
        'is_active' => true,
    ]);

    TemplateDescription::query()->create([
        'template_id' => (int) $template->getKey(),
        'form_description_id' => (int) $field->getKey(),
        'placeholder_key' => 'team_member1',
        'field_key' => 'team_member',
        'is_required' => true,
    ]);

    TemplateDescription::query()->create([
        'template_id' => (int) $template->getKey(),
        'form_description_id' => (int) $field->getKey(),
        'placeholder_key' => 'team_member2',
        'field_key' => 'team_member',
        'is_required' => true,
    ]);

    $this->actingAs($president)
        ->get(route('forms.show', ['formId' => $form->getKey()]))
        ->assertOk()
        ->assertSee('Add Row')
        ->assertSee('Use one row per Team Member# placeholder.');

    $this->actingAs($president)
        ->post(route('forms.submit', ['formId' => $form->getKey()]), [
            'fields' => [
                'team_member' => ['Alice', 'Bob'],
            ],
        ])
        ->assertRedirect(route('forms.show', ['formId' => $form->getKey()]))
        ->assertSessionHas('success');

    $submission = FormSubmission::query()->latest('form_submission_id')->firstOrFail();

    expect($submission->payload['team_member'] ?? null)->toBe(['Alice', 'Bob']);
});
