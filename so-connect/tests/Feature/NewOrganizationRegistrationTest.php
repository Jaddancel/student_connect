<?php

use App\Forms\FieldType;
use App\Forms\Handlers\NewOrganizationRegistrationHandler;
use App\Forms\SystemFunction;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\OrganizationInvitation;
use App\Models\Profile;
use App\Models\Request as ActionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function newOrgMakeProfile(?string $email = null): Profile
{
    $addressId = DB::table('profile_addresses')->insertGetId([
        'country' => 'Philippines',
        'province' => 'Tarlac',
        'town' => 'Camiling',
        'barangay' => 'Poblacion',
    ]);

    return Profile::query()->create([
        'first_name' => 'Test',
        'last_name' => 'User',
        'middle_name' => 'A',
        'occupation' => 'Student',
        'address' => $addressId,
    ]);
}

function newOrgMakeUser(int $type, ?string $email = null): User
{
    $profile = newOrgMakeProfile($email);

    return User::query()->create([
        'user_email' => $email ?? "user{$type}_{$profile->getKey()}@example.com",
        'user_password' => 'password',
        'user_type' => $type,
        'profile' => $profile->getKey(),
    ]);
}

function newOrgMakeAdmin(): User
{
    return newOrgMakeUser(2);
}

function newOrgMakeRegisteredOfficer(string $email): User
{
    return newOrgMakeUser(3, $email);
}

function makeNewOrganizationForm(): Form
{
    $form = Form::query()->create([
        'name' => 'New Organization Registration',
        'route_name' => 'test-new-org',
        'system_function' => SystemFunction::NEW_ORGANIZATION_REGISTRATION,
        'is_active' => true,
        'is_published' => true,
        'layout' => [
            'rows' => [
                ['columns' => [['span' => 12, 'fields' => ['organization_name']]]],
                ['columns' => [['span' => 12, 'fields' => ['organization_initials']]]],
                ['columns' => [['span' => 12, 'fields' => ['organization_description']]]],
                ['columns' => [['span' => 12, 'fields' => ['organization_type']]]],
                ['columns' => [['span' => 12, 'fields' => ['president_email']]]],
                ['columns' => [['span' => 12, 'fields' => ['officer_email']]]],
            ],
        ],
        'pdf_template' => [
            'html' => '<p><span data-field="organization_name"></span></p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
        ],
    ]);

    $fields = [
        ['field_key' => 'organization_name', 'field_label' => 'Organization Name', 'field_type' => FieldType::TEXT, 'is_required' => true, 'field_order' => 1],
        ['field_key' => 'organization_initials', 'field_label' => 'Initials', 'field_type' => FieldType::TEXT, 'is_required' => true, 'field_order' => 2],
        ['field_key' => 'organization_description', 'field_label' => 'Description', 'field_type' => FieldType::TEXTAREA, 'is_required' => false, 'field_order' => 3],
        ['field_key' => 'organization_type', 'field_label' => 'Type', 'field_type' => FieldType::ORGANIZATION_TYPE_SELECT, 'is_required' => true, 'field_order' => 4],
        ['field_key' => 'president_email', 'field_label' => 'New President Email', 'field_type' => FieldType::NEW_PRESIDENT_EMAIL, 'is_required' => true, 'field_order' => 5],
        ['field_key' => 'officer_email', 'field_label' => 'New Officer Email', 'field_type' => FieldType::NEW_OFFICER_EMAIL, 'is_required' => true, 'field_order' => 6],
    ];

    foreach ($fields as $f) {
        FormDescription::create($f + ['form_id' => $form->getKey()]);
    }

    return $form;
}

function makeSignUpForm(): Form
{
    $form = Form::query()->create([
        'name' => 'Directory of Student Officers',
        'route_name' => 'test-sign-up',
        'system_function' => SystemFunction::SIGN_UP,
        'is_active' => true,
        'is_published' => true,
        'layout' => [
            'rows' => [
                ['columns' => [['span' => 12, 'fields' => ['organization_id']]]],
                ['columns' => [['span' => 12, 'fields' => ['position']]]],
                ['columns' => [['span' => 12, 'fields' => ['first_name']]]],
                ['columns' => [['span' => 12, 'fields' => ['last_name']]]],
                ['columns' => [['span' => 12, 'fields' => ['email']]]],
                ['columns' => [['span' => 12, 'fields' => ['password']]]],
            ],
        ],
        'pdf_template' => [
            'html' => '<p><span data-field="first_name"></span></p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
        ],
    ]);

    $fields = [
        ['field_key' => 'organization_id', 'field_label' => 'Organization', 'field_type' => FieldType::ORG_SELECT, 'is_required' => true, 'field_order' => 1],
        ['field_key' => 'position', 'field_label' => 'Position', 'field_type' => FieldType::POSITION_SELECT, 'is_required' => true, 'field_order' => 2],
        ['field_key' => 'first_name', 'field_label' => 'First Name', 'field_type' => FieldType::TEXT, 'is_required' => true, 'field_order' => 3],
        ['field_key' => 'last_name', 'field_label' => 'Last Name', 'field_type' => FieldType::TEXT, 'is_required' => true, 'field_order' => 4],
        ['field_key' => 'email', 'field_label' => 'E-mail', 'field_type' => FieldType::NEW_OFFICER_EMAIL, 'is_required' => true, 'field_order' => 5],
        ['field_key' => 'password', 'field_label' => 'Password', 'field_type' => FieldType::PASSWORD, 'is_required' => true, 'field_order' => 6],
    ];

    foreach ($fields as $f) {
        FormDescription::create($f + ['form_id' => $form->getKey()]);
    }

    return $form;
}

it('renders the new organization registration form publicly', function () {
    $form = makeNewOrganizationForm();

    $this->get(route('forms.render', $form->route_name))
        ->assertOk()
        ->assertSee('New President Email');
});

it('rejects submission when required organization fields are missing', function () {
    $form = makeNewOrganizationForm();

    $this->post(route('forms.render.submit', $form->route_name), [
        'president_email' => 'founder@example.com',
        'officer_email' => 'officer@example.com',
    ])->assertSessionHasErrors(['organization_name', 'organization_initials', 'organization_type']);
});

it('submits a new organization registration request with registered status', function () {
    $form = makeNewOrganizationForm();
    $presidentEmail = 'founder@example.com';
    $officerEmail = 'officer@example.com';
    newOrgMakeRegisteredOfficer($presidentEmail);

    $this->post(route('forms.render.submit', $form->route_name), [
        'organization_name' => 'Test Org',
        'organization_initials' => 'TO',
        'organization_description' => 'A test organization.',
        'organization_type' => '1',
        'president_email' => $presidentEmail,
        'officer_email' => $officerEmail,
    ])
        ->assertRedirect(route('forms.render', $form->route_name));

    $request = ActionRequest::query()
        ->where('form_id', $form->getKey())
        ->where('action_type', NewOrganizationRegistrationHandler::ACTION_TYPE)
        ->first();

    expect($request)->not->toBeNull();
    expect($request->payload['president_email'])->toBe($presidentEmail);
    expect($request->payload['president_is_registered'])->toBeTrue();
    expect($request->payload['officer_email'])->toBe($officerEmail);
    expect($request->payload['officer_is_registered'])->toBeFalse();
});

it('marks an unregistered email as not registered', function () {
    $form = makeNewOrganizationForm();

    $this->post(route('forms.render.submit', $form->route_name), [
        'organization_name' => 'Another Test Org',
        'organization_initials' => 'AO',
        'organization_description' => 'Another test.',
        'organization_type' => '1',
        'president_email' => 'newbie@example.com',
        'officer_email' => 'officer@example.com',
    ])->assertRedirect(route('forms.render', $form->route_name));

    $request = ActionRequest::query()
        ->where('form_id', $form->getKey())
        ->first();

    expect($request->payload['president_is_registered'])->toBeFalse();
    expect($request->payload['officer_is_registered'])->toBeFalse();
});

it('shows registered status on the admin review page', function () {
    $form = makeNewOrganizationForm();
    $admin = newOrgMakeAdmin();
    $presidentEmail = 'registered@example.com';
    $officerEmail = 'officer@example.com';
    newOrgMakeRegisteredOfficer($presidentEmail);

    $this->post(route('forms.render.submit', $form->route_name), [
        'organization_name' => 'Review Org',
        'organization_initials' => 'RO',
        'organization_description' => 'Review.',
        'organization_type' => '1',
        'president_email' => $presidentEmail,
        'officer_email' => $officerEmail,
    ]);

    $request = ActionRequest::query()->where('form_id', $form->getKey())->first();

    $this->actingAs($admin)
        ->get(route('admin.form-requests.show', [$form, $request->getKey()]))
        ->assertOk()
        ->assertSee('registered');
});

it('creates the organization and assigns existing leaders their roles', function () {
    $form = makeNewOrganizationForm();
    $admin = newOrgMakeAdmin();
    $presidentEmail = 'president@example.com';
    $officerEmail = 'officer@example.com';
    $president = newOrgMakeRegisteredOfficer($presidentEmail);
    $officer = newOrgMakeRegisteredOfficer($officerEmail);

    $this->post(route('forms.render.submit', $form->route_name), [
        'organization_name' => 'Presidents Org',
        'organization_initials' => 'PO',
        'organization_description' => 'Presidential.',
        'organization_type' => '1',
        'president_email' => $presidentEmail,
        'officer_email' => $officerEmail,
    ]);

    $request = ActionRequest::query()->where('form_id', $form->getKey())->first();

    $this->actingAs($admin)
        ->post(route('admin.form-requests.decide', [$form, $request->getKey()]), [
            'decision' => 'approve',
        ])
        ->assertRedirect(route('admin.form-requests.index', $form));

    $org = DB::table('organizations as o')
        ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
        ->where('od.name', 'Presidents Org')
        ->first();

    expect($org)->not->toBeNull();

    $presidentMembership = DB::table('organization_officers')
        ->where('organization', $org->organization_id)
        ->where('user', $president->getKey())
        ->first();

    expect($presidentMembership)->not->toBeNull();
    expect($presidentMembership->role)->toBe('president');

    $officerMembership = DB::table('organization_officers')
        ->where('organization', $org->organization_id)
        ->where('user', $officer->getKey())
        ->first();

    expect($officerMembership)->not->toBeNull();
    expect($officerMembership->role)->toBe('officer');
    expect($officerMembership->position)->toBe('other');
});

it('creates the organization and issues an invitation for an unregistered founder', function () {
    $form = makeNewOrganizationForm();
    $admin = newOrgMakeAdmin();
    $presidentEmail = 'invited@example.com';
    $officerEmail = 'officer@example.com';
    $secondOfficerEmail = 'officer-two@example.com';

    FormDescription::query()->create([
        'form_id' => $form->getKey(),
        'field_key' => 'officer_email_2',
        'field_label' => 'New Officer Email Field',
        'field_type' => FieldType::NEW_OFFICER_EMAIL,
        'is_required' => false,
        'field_order' => 7,
    ]);

    $this->post(route('forms.render.submit', $form->route_name), [
        'organization_name' => 'Invited Org',
        'organization_initials' => 'IO',
        'organization_description' => 'Invitation.',
        'organization_type' => '1',
        'president_email' => $presidentEmail,
        'officer_email' => $officerEmail,
        'officer_email_2' => $secondOfficerEmail,
    ]);

    $request = ActionRequest::query()->where('form_id', $form->getKey())->first();

    $this->actingAs($admin)
        ->post(route('admin.form-requests.decide', [$form, $request->getKey()]), [
            'decision' => 'approve',
        ]);

    $org = DB::table('organizations as o')
        ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
        ->where('od.name', 'Invited Org')
        ->first();

    expect($org)->not->toBeNull();

    $invitation = OrganizationInvitation::query()
        ->where('organization_id', $org->organization_id)
        ->where('email', $presidentEmail)
        ->first();

    expect($invitation)->not->toBeNull();
    expect($invitation->role)->toBe('president');

    $officerInvitation = OrganizationInvitation::query()
        ->where('organization_id', $org->organization_id)
        ->where('email', $officerEmail)
        ->first();

    expect($officerInvitation)->not->toBeNull();
    expect($officerInvitation->role)->toBe('officer');
    expect($officerInvitation->position)->toBe('other');

    $secondOfficerInvitation = OrganizationInvitation::query()
        ->where('organization_id', $org->organization_id)
        ->where('email', $secondOfficerEmail)
        ->first();

    expect($secondOfficerInvitation)->not->toBeNull();
    expect($secondOfficerInvitation->role)->toBe('officer');
    expect($secondOfficerInvitation->position)->toBe('other');

    $this->assertDatabaseMissing('organization_officers', [
        'organization' => $org->organization_id,
    ]);
});

it('redeems an invitation and pre-fills the sign-up form', function () {
    makeSignUpForm();

    $invitation = OrganizationInvitation::query()->create([
        'email' => 'invited@example.com',
        'token_hash' => hash('sha256', 'raw-token'),
        'organization_id' => DB::table('organizations')->insertGetId([
            'organization_type' => 1,
            'detail' => DB::table('organization_details')->insertGetId([
                'name' => 'Prefilled Org',
                'initials' => 'PF',
                'detail_text' => 'Prefilled.',
            ]),
        ]),
        'role' => 'president',
        'position' => null,
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->get(route('organization-invitation.redeem', ['token' => 'raw-token']));

    $response->assertRedirect();

    $location = $response->headers->get('Location');
    expect($location)->toContain('test-sign-up');
});

it('promotes an invited sign-up applicant to president', function () {
    $signUpForm = makeSignUpForm();
    makeNewOrganizationForm();
    $admin = newOrgMakeAdmin();

    $organizationId = DB::table('organizations')->insertGetId([
        'organization_type' => 1,
        'detail' => DB::table('organization_details')->insertGetId([
            'name' => 'Invited President Org',
            'initials' => 'IPO',
            'detail_text' => 'Invited president.',
        ]),
    ]);

    $invitation = OrganizationInvitation::query()->create([
        'email' => 'invited@example.com',
        'token_hash' => hash('sha256', 'raw-token'),
        'organization_id' => $organizationId,
        'role' => 'president',
        'position' => null,
        'expires_at' => now()->addDays(7),
    ]);

    $prefill = base64_encode(Crypt::encryptString(json_encode([
        'email' => 'invited@example.com',
        'organization_id' => $organizationId,
        'role' => 'president',
        'position' => null,
        'invitation_id' => (int) $invitation->getKey(),
        'token' => 'raw-token',
    ])));

    $this->post(route('forms.render.submit', $signUpForm->route_name), [
        'invitation_prefill' => $prefill,
        'organization_id' => $organizationId,
        'position' => 'President',
        'first_name' => 'Invited',
        'last_name' => 'President',
        'email' => 'invited@example.com',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
    ])->assertRedirect(route('signup.success'));

    $applicant = User::query()->where('user_email', 'invited@example.com')->first();
    expect($applicant)->not->toBeNull();

    $request = ActionRequest::query()->where('form_id', $signUpForm->getKey())->first();
    expect($request->payload['intended_role'] ?? null)->toBe('president');

    $this->actingAs($admin)
        ->postJson("/api/requests/{$request->getKey()}/decision", [
            'decision' => 'approve',
        ])->assertOk();

    $officer = DB::table('organization_officers')
        ->where('organization', $organizationId)
        ->where('user', $applicant->getKey())
        ->first();

    expect($officer)->not->toBeNull();
    expect($officer->role)->toBe('president');
});

it('keeps a normal sign-up applicant as an ordinary officer', function () {
    $signUpForm = makeSignUpForm();
    $admin = newOrgMakeAdmin();

    $organizationId = DB::table('organizations')->insertGetId([
        'organization_type' => 1,
        'detail' => DB::table('organization_details')->insertGetId([
            'name' => 'Normal Org',
            'initials' => 'NO',
            'detail_text' => 'Normal.',
        ]),
    ]);

    $this->post(route('forms.render.submit', $signUpForm->route_name), [
        'organization_id' => $organizationId,
        'position' => 'Secretary',
        'first_name' => 'Normal',
        'last_name' => 'Officer',
        'email' => 'normal@example.com',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
    ])->assertRedirect(route('signup.success'));

    $applicant = User::query()->where('user_email', 'normal@example.com')->first();
    expect($applicant)->not->toBeNull();

    $request = ActionRequest::query()->where('form_id', $signUpForm->getKey())->first();

    $this->actingAs($admin)
        ->postJson("/api/requests/{$request->getKey()}/decision", [
            'decision' => 'approve',
        ])->assertOk();

    $officer = DB::table('organization_officers')
        ->where('organization', $organizationId)
        ->where('user', $applicant->getKey())
        ->first();

    expect($officer)->not->toBeNull();
    expect($officer->role)->toBe('officer');
    expect($officer->position)->toBe('Secretary');
});

it('reports email registration status via the availability endpoint', function () {
    $email = 'known@example.com';
    newOrgMakeRegisteredOfficer($email);

    $this->getJson(route('email-availability.check', ['email' => $email]))
        ->assertOk()
        ->assertJson(['registered' => true, 'valid' => true]);

    $this->getJson(route('email-availability.check', ['email' => 'unknown@example.com']))
        ->assertOk()
        ->assertJson(['registered' => false, 'valid' => true]);
});
