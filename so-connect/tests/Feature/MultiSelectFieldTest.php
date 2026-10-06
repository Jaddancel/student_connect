<?php

use App\Forms\DocxTemplateData;
use App\Forms\FieldType;
use App\Forms\PdfTemplateRenderer;
use App\Forms\SubmissionPresenter;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

/** An officer of a fresh organization — the actor rendered forms require. */
function multiSelectOfficer(): \App\Models\User
{
    $user = recordsUser(3);
    \Illuminate\Support\Facades\DB::table('organization_officers')->insert([
        'role' => 'officer',
        'organization' => (int) recordsOrganization('Multi Org')->getKey(),
        'user' => (int) $user->getKey(),
        'yearterm' => null,
        'member_since' => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    return $user;
}

function multiSelectOptions(): array
{
    return ['multiple' => true, 'options' => [
        ['value' => 'gym', 'label' => 'Gymnasium'],
        ['value' => 'av', 'label' => 'AV Room'],
        ['value' => 'lab', 'label' => 'Computer Lab'],
    ]];
}

function multiSelectForm(bool $required = false): Form
{
    $form = Form::create([
        'name' => 'Facilities', 'route_name' => 'facilities', 'is_active' => true, 'is_published' => true,
        'layout' => ['rows' => [['columns' => [['span' => 12, 'fields' => ['facilities']]]]]],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);
    FormDescription::create([
        'form_id' => $form->id, 'field_key' => 'facilities', 'field_label' => 'Facilities',
        'field_type' => 'select', 'field_options' => multiSelectOptions(), 'is_required' => $required, 'field_order' => 1,
    ]);

    return $form;
}

it('saves the multiple-selection toggle only on dropdowns', function () {
    $this->actingAs(recordsUser(2))->postJson(route('admin.form-builder.store'), [
        'name' => 'Multi', 'route_name' => 'multi-builder',
        'fields' => [
            ['field_key' => 'facilities', 'field_label' => 'Facilities', 'field_type' => 'select', 'field_options' => multiSelectOptions()],
            ['field_key' => 'note', 'field_label' => 'Note', 'field_type' => 'text', 'field_options' => ['multiple' => true]],
        ],
        'rows' => [['columns' => [['span' => 12, 'fields' => ['facilities', 'note']]]]],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ])->assertOk();

    $fields = Form::where('route_name', 'multi-builder')->first()->fields->keyBy('field_key');
    expect($fields['facilities']->field_options['multiple'])->toBeTrue()
        ->and($fields['note']->field_options)->not->toHaveKey('multiple');
});

it('renders a multi-select as a checkmark list and stores each pick as a row', function () {
    $officer = multiSelectOfficer();
    multiSelectForm();

    $this->actingAs($officer)->get(route('forms.render', 'facilities'))
        ->assertOk()
        ->assertSee('data-multi-select="facilities"', false)
        ->assertSee('name="facilities[]" value="gym"', false)
        ->assertSee('aria-multiselectable="true"', false);

    $this->actingAs($officer)->post(route('forms.render.submit', 'facilities'), ['facilities' => ['gym', 'lab']])
        ->assertSessionHasNoErrors();

    expect(FormSubmission::query()->sole()->payload['facilities'])->toBe(['gym', 'lab']);
});

it('rejects unknown and missing multi-select picks', function () {
    $officer = multiSelectOfficer();
    multiSelectForm(required: true);

    $this->actingAs($officer)->post(route('forms.render.submit', 'facilities'), ['facilities' => ['gym', 'pool']])
        ->assertSessionHasErrors('facilities.1');
    $this->actingAs($officer)->post(route('forms.render.submit', 'facilities'), [])
        ->assertSessionHasErrors('facilities');

    expect(FormSubmission::query()->count())->toBe(0);
});

it('prints multi-select picks like a text list in PDF and DOCX templates', function () {
    $field = new FormDescription(['field_key' => 'facilities', 'field_label' => 'Facilities', 'field_type' => 'select', 'field_options' => multiSelectOptions()]);
    $payload = ['facilities' => ['gym', 'lab']];

    $pdf = (new PdfTemplateRenderer)->render('<p>Uses <span data-field="facilities">Facilities</span>.</p>', $payload, new Collection([$field]));
    $docx = app(DocxTemplateData::class)->build($payload, collect([$field]))['values'];

    expect($pdf)->toContain('Uses Gymnasium, Computer Lab.')
        ->and($docx['facilities'])->toBe(['Gymnasium', 'Computer Lab'])
        ->and(SubmissionPresenter::display($payload, 'facilities', 'select', multiSelectOptions()))->toBe('Gymnasium, Computer Lab');
});

it('counts multi-select picks as rows in scoring rules and flags them in the tally editor', function () {
    $engine = new \App\Services\Scoring\ScoringRuleEngine;
    $contribution = (new ReflectionClass($engine))->getMethod('contribution');
    $record = ['values' => ['facilities' => ['gym', 'lab']], 'user_id' => 0];

    expect($contribution->invoke($engine, ['kind' => 'count_list', 'var' => 'field:facilities'], $record))->toBe(2)
        ->and(FieldType::isMultiSelect('select', multiSelectOptions()))->toBeTrue()
        ->and(FieldType::isMultiSelect('select', ['options' => []]))->toBeFalse();

    $form = multiSelectForm();
    $controller = new \App\Http\Controllers\Admin\ScoringRuleController;
    $variables = (new ReflectionClass($controller))->getMethod('variablesPayload')->invoke($controller);
    $field = collect($variables['forms'])->firstWhere('id', $form->id)['fields'][0];

    expect($field['key'])->toBe('facilities')
        ->and($field['multiple'])->toBeTrue();
});
