<?php

use App\Forms\FieldType;
use App\Forms\Handlers\OrgAccreditationHandler;
use App\Forms\SystemFunction;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Services\AccreditationAttachmentChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function checklistForm(): Form
{
    $form = Form::query()->create([
        'name' => 'Accreditation Checklist Test',
        'route_name' => 'accreditation-checklist-test',
        'system_function' => SystemFunction::ORG_ACCREDITATION,
        'is_active' => true,
        'is_published' => true,
    ]);

    FormDescription::query()->create([
        'form_id' => $form->getKey(),
        'field_key' => 'constitution',
        'field_label' => 'Constitution and By-Laws',
        'field_type' => FieldType::FILE,
        'is_required' => true,
        'field_order' => 1,
    ]);

    // Optional uploads must not become approval requirements.
    FormDescription::query()->create([
        'form_id' => $form->getKey(),
        'field_key' => 'optional_reference',
        'field_label' => 'Optional Reference',
        'field_type' => FieldType::FILE,
        'is_required' => false,
        'field_order' => 2,
    ]);

    return $form;
}

it('blocks an accreditation submission when a required attachment is missing', function () {
    $user = recordsUser(3);
    $org = recordsOrganization('Checklist Org', 'CO');
    $form = checklistForm();
    $request = Illuminate\Http\Request::create('/', 'POST');
    $request->setUserResolver(fn () => $user);

    expect(fn () => app(OrgAccreditationHandler::class)->validatePayload(
        $form,
        ['organization_id' => (int) $org->getKey()],
        $request,
    ))->toThrow(ValidationException::class, 'Constitution and By-Laws is required');
});

it('accepts a complete accreditation attachment checklist', function () {
    $user = recordsUser(3);
    $org = recordsOrganization('Complete Org', 'CP');
    $form = checklistForm();
    $request = Illuminate\Http\Request::create('/', 'POST');
    $request->setUserResolver(fn () => $user);

    app(OrgAccreditationHandler::class)->validatePayload($form, [
        'organization_id' => (int) $org->getKey(),
        'constitution' => 'form-submissions/constitution.pdf',
    ], $request);

    expect(true)->toBeTrue();
});

it('blocks approval of a stored accreditation submission with an incomplete checklist', function () {
    $form = checklistForm();
    $submission = FormSubmission::query()->create([
        'form_id' => $form->getKey(),
        'payload' => [],
        'submitted_at' => now(),
    ]);

    expect(fn () => app(AccreditationAttachmentChecklist::class)->assertApprovable($submission))
        ->toThrow(RuntimeException::class, 'Missing required attachment(s): Constitution and By-Laws');
});

it('reports each required attachment status for the admin review checklist', function () {
    $form = checklistForm();

    expect(app(AccreditationAttachmentChecklist::class)->status($form, [
        'constitution' => 'form-submissions/constitution.pdf',
    ]))->toBe([
        ['key' => 'constitution', 'label' => 'Constitution and By-Laws', 'attached' => true],
    ]);
});

it('renders accreditation as one guided application workflow', function () {
    $user = recordsUser(3);
    $org = recordsOrganization('Accreditation Test Organization', 'ATO');
    $form = checklistForm();

    FormDescription::query()->create([
        'form_id' => $form->getKey(),
        'field_key' => 'recognition_type',
        'field_label' => 'Accreditation / Renewal',
        'field_type' => FieldType::RADIO,
        'is_required' => false,
        'field_order' => 3,
        'field_options' => ['options' => [
            ['value' => 'accreditation', 'label' => 'Accreditation'],
            ['value' => 'renewal', 'label' => 'Renewal'],
        ]],
    ]);

    DB::table('organization_officers')->insert([
        'user' => $user->getKey(),
        'organization' => $org->getKey(),
        'role' => 'president',
    ]);

    $this->actingAs($user)
        ->withSession(['active_organization_id' => $org->getKey()])
        ->get(route('forms.render', $form->route_name))
        ->assertOk()
        ->assertSee('Organization accreditation')
        ->assertSee('Accreditation journey')
        ->assertSee('data-accreditation-journey="linear"', false)
        ->assertSee('data-accreditation-overview', false)
        ->assertSee('@click="startAccreditation()"', false)
        ->assertSee('Monitor the announcement')
        ->assertSee('Update organization records')
        ->assertSee('Complete accreditation requirements')
        ->assertSee('OSSD evaluation')
        ->assertSee('Accreditation confirmed')
        ->assertSee('Required uploads set by OSSD')
        ->assertSee('Constitution and By-Laws')
        ->assertSee('Submit accreditation application')
        ->assertDontSee('Accreditation / Renewal')
        ->assertDontSee('AccreditationRenewal')
        ->assertDontSee('Renewal');
});

it('removes the obsolete renewal choice from existing accreditation forms', function () {
    $form = Form::query()->create([
        'name' => 'Existing Accreditation Form',
        'route_name' => 'existing-accreditation-form',
        'system_function' => SystemFunction::ORG_ACCREDITATION,
        'description_text' => 'Apply for organization accreditation or renewal.',
        'layout' => ['rows' => [
            ['columns' => [['span' => 12, 'fields' => ['recognition_type']]]],
            ['columns' => [['span' => 12, 'fields' => ['organization_id']]]],
        ]],
    ]);

    FormDescription::query()->create([
        'form_id' => $form->getKey(),
        'field_key' => 'recognition_type',
        'field_label' => 'Accreditation / Renewal',
        'field_type' => FieldType::RADIO,
        'is_required' => false,
        'field_order' => 1,
    ]);

    $migration = require database_path('migrations/2026_10_25_000002_remove_accreditation_renewal_distinction.php');
    $migration->up();

    $form->refresh();

    expect($form->description_text)
        ->toBe('Apply for organization accreditation for the next academic year.')
        ->and(FormDescription::query()
            ->where('form_id', $form->getKey())
            ->where('field_key', 'recognition_type')
            ->exists())->toBeFalse()
        ->and(collect($form->layout['rows'])
            ->flatMap(fn (array $row) => $row['columns'] ?? [])
            ->flatMap(fn (array $column) => $column['fields'] ?? [])
            ->all())->toBe(['organization_id']);
});

it('prevents another accreditation application while one is awaiting review', function () {
    $user = recordsUser(3);
    $org = recordsOrganization('Pending Accreditation Organization', 'PAO');
    $form = checklistForm();
    $request = Illuminate\Http\Request::create('/', 'POST');
    $request->setUserResolver(fn () => $user);

    DB::table('requests')->insert([
        'action' => 'document_generation',
        'action_type' => 3,
        'form_id' => $form->getKey(),
        'organization_id' => $org->getKey(),
        'requested_by' => $user->getKey(),
        'user' => $user->getKey(),
        'requested_at' => now(),
        'payload' => json_encode([]),
    ]);

    expect(fn () => app(OrgAccreditationHandler::class)->validatePayload($form, [
        'organization_id' => (int) $org->getKey(),
        'constitution' => 'form-submissions/constitution.pdf',
    ], $request))->toThrow(
        ValidationException::class,
        'already awaiting OSSD review',
    );
});
