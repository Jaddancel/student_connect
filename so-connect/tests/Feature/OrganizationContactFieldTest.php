<?php

use App\Forms\FormRenderContext;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Models\Officer;
use App\Models\Organization;
use App\Models\User;
use App\Reports\ReportPalette;
use App\Support\OrganizationField;
use App\Support\UniversalField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function contactOfficer(Organization $organization, string $role, ?string $position, ?string $contact): User
{
    $user = recordsUser(3, ['contact_number' => $contact]);
    Officer::query()->create([
        'organization' => $organization->getKey(),
        'user' => $user->getKey(),
        'role' => $role,
        'position' => $position,
    ]);

    return $user;
}

it('registers an org-source text field for each officer contact', function (string $key) {
    expect(UniversalField::get($key))->toMatchArray([
        'source' => 'org',
        'type' => \App\Forms\FieldType::TEXT,
        'group' => 'officer_contact',
    ])->and(UniversalField::isOrgField($key))->toBeTrue();
})->with(['org_president_contact', 'org_treasurer_contact', 'org_auditor_contact', 'org_secretary_contact']);

it('resolves each officer contact number from the organization', function () {
    $organization = recordsOrganization('Contacts');
    $other = recordsOrganization('Elsewhere');

    contactOfficer($organization, 'president', 'President', '9170000001');
    contactOfficer($organization, 'officer', 'Treasurer', '9170000002');
    contactOfficer($organization, 'officer', 'Auditor', '9170000003');
    contactOfficer($organization, 'officer', 'Secretary', '9170000004');
    contactOfficer($other, 'officer', 'Treasurer', '9179999999');

    expect(OrganizationField::value($organization, 'org_president_contact'))->toBe('09170000001')
        ->and(OrganizationField::value($organization, 'org_treasurer_contact'))->toBe('09170000002')
        ->and(OrganizationField::value($organization, 'org_auditor_contact'))->toBe('09170000003')
        ->and(OrganizationField::value($organization, 'org_secretary_contact'))->toBe('09170000004')
        ->and(OrganizationField::value($other, 'org_treasurer_contact'))->toBe('09179999999')
        ->and(OrganizationField::value(null, 'org_treasurer_contact'))->toBeNull();
});

it('normalizes stored contact numbers to digit-only local numbers', function (?string $stored, ?string $expected) {
    $organization = recordsOrganization('Formats');
    contactOfficer($organization, 'officer', 'Treasurer', $stored);

    expect(OrganizationField::value($organization, 'org_treasurer_contact'))->toBe($expected);
})->with([
    'profile form (10 digits)' => ['9171234567', '09171234567'],
    'local with zero' => ['09171234567', '09171234567'],
    'international' => ['+63 917-123-4567', '09171234567'],
    'landline' => ['(045) 123-4567', '0451234567'],
    'missing' => [null, null],
    'blank' => ['', null],
]);

it('returns null when the position is vacant', function () {
    $organization = recordsOrganization('Vacant');
    contactOfficer($organization, 'officer', 'Treasurer', '9171234567');

    expect(OrganizationField::value($organization, 'org_secretary_contact'))->toBeNull()
        ->and(OrganizationField::value($organization, 'org_president_contact'))->toBeNull();
});

it('offers officer contacts as Organization tokens in the Step 2 report palette', function () {
    $tokens = collect(ReportPalette::tokens(['tokens' => []]));

    expect($tokens->firstWhere('key', 'profile.org_president_contact'))->toMatchArray([
        'label' => 'Organization President Contact Number',
        'group' => 'Organization',
        'icon' => 'text',
    ]);
});

it('saves a text field autofilled with an officer contact', function () {
    $admin = recordsUser(2);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), [
        'name' => 'Contact Form', 'route_name' => 'contact-form',
        'fields' => [
            ['field_key' => 'pres_contact', 'field_label' => 'President Contact', 'field_type' => 'text', 'universal_key' => 'org_president_contact'],
        ],
        'rows' => [],
        'pdf_template' => ['html' => '', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ])->assertOk();

    expect(Form::where('route_name', 'contact-form')->first()
        ->fields()->where('field_key', 'pres_contact')->value('universal_key'))->toBe('org_president_contact');

    $this->actingAs($admin)->get(route('admin.form-builder.create'))
        ->assertOk()
        ->assertSee('Officer Contact Number')
        ->assertSee('Organization Treasurer Contact Number');
});

it('prefills a text field with the officer contact and accepts it on submit', function () {
    $organization = recordsOrganization('Prefill');
    contactOfficer($organization, 'president', 'President', '9171234567');
    $submitter = contactOfficer($organization, 'officer', 'Secretary', null);

    $form = Form::query()->create([
        'name' => 'Contact Prefill', 'route_name' => 'contact-prefill', 'is_active' => true, 'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);
    FormDescription::query()->create([
        'form_id' => $form->id, 'field_key' => 'pres_contact', 'field_label' => 'President Contact',
        'field_type' => 'text', 'field_order' => 1, 'universal_key' => 'org_president_contact',
    ]);

    $request = Request::create('/');
    $request->setUserResolver(fn () => $submitter);
    expect(FormRenderContext::profilePrefill($request, $form->fields()->get(), $organization))
        ->toBe(['pres_contact' => '09171234567']);

    $this->actingAs($submitter)->get(route('forms.render', $form->route_name))
        ->assertOk()
        ->assertSee('value="09171234567"', false);

    $this->actingAs($submitter)->post(route('forms.render.submit', $form->route_name), [
        'pres_contact' => '09171234567',
    ])->assertSessionHasNoErrors();

    expect(FormSubmission::query()->where('form_id', $form->id)->first()->payload['pres_contact'])
        ->toBe('09171234567');
});
