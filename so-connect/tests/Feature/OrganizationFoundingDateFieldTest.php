<?php

use App\Forms\FieldType;
use App\Forms\FormRenderContext;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Models\Officer;
use App\Support\OrganizationField;
use App\Support\UniversalField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

it('registers the organization founding date as an org-source date field', function () {
    expect(UniversalField::get('org_founding_date'))->toMatchArray([
        'label' => 'Organization Founding Date',
        'source' => 'org',
        'type' => FieldType::DATE,
        'group' => 'org_date',
    ])->and(UniversalField::isOrgField('org_founding_date'))->toBeTrue();
});

it('resolves the founding date from the organization registration date', function () {
    $organization = recordsOrganization('Founded');
    $organization->forceFill(['created_at' => '2023-06-01 09:30:00'])->save();

    expect(OrganizationField::value($organization->fresh(), 'org_founding_date'))->toBe('2023-06-01')
        ->and(OrganizationField::value(null, 'org_founding_date'))->toBeNull();
});

it('offers the founding date autofill in the Step 1 builder and saves it', function () {
    $admin = recordsUser(2);

    $this->actingAs($admin)->get(route('admin.form-builder.create'))
        ->assertOk()
        ->assertDontSee('label="Organization Date"', false)
        ->assertSeeInOrder(['<optgroup label="Organization">', 'Organization Category', 'Organization Founding Date', '</optgroup>'], false);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), [
        'name' => 'Founding Form', 'route_name' => 'founding-form',
        'fields' => [
            ['field_key' => 'founded', 'field_label' => 'Date Founded', 'field_type' => 'date', 'universal_key' => 'org_founding_date'],
        ],
        'rows' => [],
        'pdf_template' => ['html' => '', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ])->assertOk();

    expect(Form::where('route_name', 'founding-form')->first()
        ->fields()->where('field_key', 'founded')->value('universal_key'))->toBe('org_founding_date');
});

it('prefills a date field with the founding date and accepts it on submit', function () {
    $organization = recordsOrganization('Prefill Founded');
    $organization->forceFill(['created_at' => '2023-06-01 09:30:00'])->save();
    $submitter = recordsUser(3);
    Officer::query()->create([
        'organization' => $organization->getKey(),
        'user' => $submitter->getKey(),
        'role' => 'president',
        'position' => 'President',
    ]);

    $form = Form::query()->create([
        'name' => 'Founding Prefill', 'route_name' => 'founding-prefill', 'is_active' => true, 'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);
    FormDescription::query()->create([
        'form_id' => $form->id, 'field_key' => 'founded', 'field_label' => 'Date Founded',
        'field_type' => 'date', 'field_order' => 1, 'universal_key' => 'org_founding_date',
    ]);

    $request = Request::create('/');
    $request->setUserResolver(fn () => $submitter);
    expect(FormRenderContext::profilePrefill($request, $form->fields()->get(), $organization->fresh()))
        ->toBe(['founded' => '2023-06-01']);

    $this->actingAs($submitter)->get(route('forms.render', $form->route_name))
        ->assertOk()
        ->assertSee('value="2023-06-01"', false);

    $this->actingAs($submitter)->post(route('forms.render.submit', $form->route_name), [
        'founded' => '2023-06-01',
    ])->assertSessionHasNoErrors();

    expect(FormSubmission::query()->where('form_id', $form->id)->first()->payload['founded'])
        ->toBe('2023-06-01');
});
