<?php

use App\Forms\FieldType;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('lets a required single checkbox be left unchecked', function () {
    $officer = recordsUser(3);
    DB::table('organization_officers')->insert([
        'role' => 'officer', 'organization' => (int) recordsOrganization('Checkbox Org')->getKey(),
        'user' => (int) $officer->getKey(), 'yearterm' => null,
        'member_since' => now(), 'registered_at' => now(), 'reassigned_at' => now(),
    ]);
    $form = Form::create([
        'name' => 'Consent', 'route_name' => 'consent', 'is_active' => true, 'is_published' => true,
        'layout' => ['rows' => [['columns' => [['span' => 12, 'fields' => ['agree']]]]]],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);
    FormDescription::create([
        'form_id' => $form->id, 'field_key' => 'agree', 'field_label' => 'Co-sponsored?',
        'field_type' => 'checkbox', 'is_required' => true, 'field_order' => 1,
    ]);

    expect(FieldType::validationRules('checkbox', true))->toBe(['nullable']);

    $this->actingAs($officer)->get(route('forms.render', 'consent'))
        ->assertOk()
        ->assertDontSee('<span class="text-error-500">*</span>', false);

    $this->actingAs($officer)->post(route('forms.render.submit', 'consent'), [])->assertSessionHasNoErrors();
    $this->actingAs($officer)->post(route('forms.render.submit', 'consent'), ['agree' => '1'])->assertSessionHasNoErrors();

    expect(FormSubmission::query()->orderBy('form_submission_id')->pluck('payload')->map(fn ($p) => $p['agree'])->all())
        ->toBe([0, 1]);
});
