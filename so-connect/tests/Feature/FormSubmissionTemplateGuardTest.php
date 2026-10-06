<?php

use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function templateGuardOfficer(): User
{
    $user = recordsUser(3);

    DB::table('organization_officers')->insert([
        'role' => 'officer',
        'organization' => (int) recordsOrganization('Template Guard Org', 'TGO')->getKey(),
        'user' => (int) $user->getKey(),
        'yearterm' => null,
        'member_since' => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    return $user;
}

/** A form built in the current builder: Step 2 stores a .docx, not legacy HTML. */
function templateGuardForm(bool $withDocx): Form
{
    $form = Form::query()->create([
        'name' => 'Organization Fund Form',
        'route_name' => 'fund-form-'.Str::lower(Str::random(8)),
        'is_active' => true,
        'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => ['html' => '', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);

    FormDescription::query()->create([
        'form_id' => $form->id,
        'field_key' => 'funds_table',
        'field_label' => 'Funds Table',
        'field_type' => 'table-input',
        'is_required' => false,
        'field_order' => 1,
        'field_options' => ['columns' => [
            ['key' => 'fund_source', 'label' => 'Fund Source', 'type' => 'text'],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'number'],
        ]],
    ]);

    if ($withDocx) {
        $relativePath = 'form-templates/'.$form->getKey().'/printed.docx';
        Storage::disk('public')->put($relativePath, '');
        makeDocxTemplate(Storage::disk('public')->path($relativePath));
        Template::query()->create([
            'form_id' => $form->getKey(),
            'template_name' => 'Printed',
            'docx_path' => $relativePath,
            'version' => 1,
            'is_active' => true,
        ]);
    }

    return $form;
}

it('accepts submissions for a form whose printed template is a .docx', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $form = templateGuardForm(withDocx: true);

    $this->actingAs(templateGuardOfficer())
        ->post(route('forms.render.submit', $form->route_name), [
            'funds_table' => [
                ['fund_source' => 'Membership fees', 'amount' => '4500'],
                ['fund_source' => 'Sponsorship', 'amount' => '12000'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $submission = FormSubmission::query()->where('form_id', $form->getKey())->sole();
    expect($submission->payload['funds_table'])->toHaveCount(2);
});

it('still refuses submissions for a form with no printed template at all', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $form = templateGuardForm(withDocx: false);

    $this->actingAs(templateGuardOfficer())
        ->post(route('forms.render.submit', $form->route_name), ['funds_table' => []])
        ->assertStatus(422);

    expect(FormSubmission::query()->where('form_id', $form->getKey())->exists())->toBeFalse();
});
