<?php

use App\Forms\SystemFunction;
use App\Models\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function newEventWaiverBuilderPayload(?array $waiverField): array
{
    $fields = [
        ['field_key' => 'title', 'field_label' => 'Title', 'field_type' => 'text', 'is_required' => true],
        ['field_key' => 'target_date', 'field_label' => 'Target Date', 'field_type' => 'date', 'is_required' => true],
        ['field_key' => 'event_location', 'field_label' => 'Location', 'field_type' => 'text', 'is_required' => true],
        ['field_key' => 'event_start_time', 'field_label' => 'Start', 'field_type' => 'time', 'is_required' => true],
        ['field_key' => 'event_end_time', 'field_label' => 'End', 'field_type' => 'time', 'is_required' => true],
    ];

    if ($waiverField !== null) {
        $fields[] = $waiverField;
    }

    return [
        'name' => 'New Event',
        'route_name' => 'new-event-waiver-check',
        'system_function' => SystemFunction::NEW_EVENT,
        'fields' => $fields,
        'rows' => [['columns' => [['span' => 12, 'fields' => array_column($fields, 'field_key')]]]],
        'pdf_template' => ['html' => '<p>Event request</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ];
}

it('publishes a New Event form without a waiver scan field', function () {
    $this->actingAs(recordsUser(2))
        ->postJson(route('admin.form-builder.store'), newEventWaiverBuilderPayload(null))
        ->assertOk();

    $form = Form::query()->where('system_function', SystemFunction::NEW_EVENT)->sole();
    expect($form->is_published)->toBeTrue()
        ->and($form->fields()->where('field_type', 'waiver-scan')->exists())->toBeFalse();
});

it('publishes a New Event form whose waiver scan field is optional', function () {
    $this->actingAs(recordsUser(2))
        ->postJson(route('admin.form-builder.store'), newEventWaiverBuilderPayload([
            'field_key' => 'participant_waivers',
            'field_label' => 'Participant Waivers',
            'field_type' => 'waiver-scan',
            'is_required' => false,
        ]))
        ->assertOk();

    $form = Form::query()->where('system_function', SystemFunction::NEW_EVENT)->sole();
    expect($form->is_published)->toBeTrue()
        ->and($form->fields()->where('field_type', 'waiver-scan')->firstOrFail()->is_required)->toBeFalse();
});

it('publishes a New Event form with a required waiver scan field', function () {
    $this->actingAs(recordsUser(2))
        ->postJson(route('admin.form-builder.store'), newEventWaiverBuilderPayload([
            'field_key' => 'participant_waivers',
            'field_label' => 'Participant Waivers',
            'field_type' => 'waiver-scan',
            'is_required' => true,
        ]))
        ->assertOk();

    $form = Form::query()->where('system_function', SystemFunction::NEW_EVENT)->sole();
    expect($form->is_published)->toBeTrue()
        ->and($form->fields()->where('field_type', 'waiver-scan')->firstOrFail()->is_required)->toBeTrue();
});

it('updates a published New Event form when its waiver becomes optional', function () {
    $admin = recordsUser(2);
    $payload = newEventWaiverBuilderPayload([
        'field_key' => 'waiver',
        'field_label' => 'Signed Waiver',
        'field_type' => 'waiver-scan',
        'is_required' => true,
    ]);

    $this->actingAs($admin)
        ->postJson(route('admin.form-builder.store'), $payload)
        ->assertOk();

    $form = Form::query()->where('system_function', SystemFunction::NEW_EVENT)->sole();
    $payload['fields'][5]['is_required'] = false;

    $this->actingAs($admin)
        ->putJson(route('admin.form-builder.update', $form), $payload)
        ->assertOk();

    expect($form->fresh()->is_published)->toBeTrue()
        ->and($form->fields()->where('field_type', 'waiver-scan')->firstOrFail()->is_required)->toBeFalse();
});

it('updates a published New Event form when its waiver is removed', function () {
    $this->actingAs(recordsUser(2))
        ->postJson(route('admin.form-builder.store'), newEventWaiverBuilderPayload([
            'field_key' => 'waiver',
            'field_label' => 'Signed Waiver',
            'field_type' => 'waiver-scan',
            'is_required' => true,
        ]))
        ->assertOk();

    $form = Form::query()->where('system_function', SystemFunction::NEW_EVENT)->sole();

    $this->putJson(route('admin.form-builder.update', $form), newEventWaiverBuilderPayload(null))
        ->assertOk();

    expect($form->fresh()->is_published)->toBeTrue()
        ->and($form->fields()->where('field_type', 'waiver-scan')->exists())->toBeFalse();
});

it('rejects a submission when a required waiver scan field is left blank', function () {
    $form = Form::query()->create([
        'name' => 'Waiver Required',
        'route_name' => 'waiver-required',
        'is_active' => true,
        'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);
    \App\Models\Form\FormDescription::query()->create([
        'form_id' => $form->id, 'field_key' => 'waiver', 'field_label' => 'Waiver',
        'field_type' => 'waiver-scan', 'field_order' => 1, 'is_required' => true,
    ]);

    $officer = recordsUser(3);
    \Illuminate\Support\Facades\DB::table('organization_officers')->insert([
        'role' => 'officer',
        'organization' => (int) recordsOrganization('Waiver Org', 'WO')->getKey(),
        'user' => (int) $officer->getKey(),
        'yearterm' => null,
        'member_since' => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    // The widget's hidden input posts an empty string when nothing is captured.
    $this->actingAs($officer)
        ->post(route('forms.render.submit', $form->route_name), ['waiver' => ['']])
        ->assertSessionHasErrors('waiver');

    $this->actingAs($officer)
        ->post(route('forms.render.submit', $form->route_name), [])
        ->assertSessionHasErrors('waiver');
});
