<?php

use App\Forms\FieldKit;
use App\Forms\SystemFunction;
use App\Models\EventPlan;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function orgPickerFormPayload(string $systemFunction, array $fields): array
{
    return [
        'name' => 'Kit form', 'route_name' => 'kit-'.str_replace('_', '-', $systemFunction),
        'system_function' => $systemFunction,
        'fields' => $fields,
        'rows' => [['columns' => [['span' => 12, 'fields' => array_column($fields, 'field_key')]]]],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ];
}

function newEventFieldsWithoutPicker(): array
{
    return [
        ['field_key' => 'title', 'field_label' => 'Title', 'field_type' => 'text'],
        ['field_key' => 'target_date', 'field_label' => 'Target Date', 'field_type' => 'date'],
        ['field_key' => 'event_location', 'field_label' => 'Location', 'field_type' => 'text'],
        ['field_key' => 'event_start_time', 'field_label' => 'Start', 'field_type' => 'time'],
        ['field_key' => 'event_end_time', 'field_label' => 'End', 'field_type' => 'time'],
    ];
}

it('only requires the organization picker on the sign-up and membership forms', function () {
    foreach (FieldKit::keys() as $kit) {
        $requiresPicker = array_key_exists('organization_id', FieldKit::required($kit));
        expect($requiresPicker)->toBe(in_array($kit, [SystemFunction::SIGN_UP, SystemFunction::MEMBERSHIP_REGISTRATION], true), $kit);
    }

    $admin = recordsUser(2);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), orgPickerFormPayload('new_event', newEventFieldsWithoutPicker()))
        ->assertOk();

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), orgPickerFormPayload('membership_registration', [
        ['field_key' => 'position', 'field_label' => 'Position', 'field_type' => 'position-select'],
    ]))->assertStatus(422)->assertJsonFragment(['message' => 'This form must include the following required field(s): organization_id (Organization picker).']);
});

it('files a New Event without an organization picker under the officer\'s organization', function () {
    $org = recordsOrganization('Picker-less Org');
    $officer = recordsUser(3);
    DB::table('organization_officers')->insert([
        'role' => 'officer', 'organization' => (int) $org->getKey(), 'user' => (int) $officer->getKey(),
        'yearterm' => null, 'member_since' => now(), 'registered_at' => now(), 'reassigned_at' => now(),
    ]);

    $this->actingAs(recordsUser(2))->postJson(route('admin.form-builder.store'), orgPickerFormPayload('new_event', newEventFieldsWithoutPicker()))
        ->assertOk();
    $form = Form::query()->where('system_function', SystemFunction::NEW_EVENT)->sole();
    $form->forceFill(['is_published' => true, 'is_active' => true])->save();

    $this->actingAs($officer)->post(route('forms.render.submit', $form->route_name), [
        'title' => 'Org-less Seminar',
        'target_date' => now()->addWeek()->toDateString(),
        'event_location' => 'Gym',
        'event_start_time' => '09:00',
        'event_end_time' => '11:00',
    ])->assertSessionHasNoErrors();

    $submission = FormSubmission::query()->where('form_id', $form->id)->sole();
    expect((int) $submission->organization_id)->toBe((int) $org->getKey())
        ->and(EventPlan::query()->where('title', 'Org-less Seminar')->pluck('organization_id')->map(fn ($id) => (int) $id)->unique()->all())
        ->toBe([(int) $org->getKey()]);
});
