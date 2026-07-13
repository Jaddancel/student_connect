<?php

use App\Models\Form;
use App\Models\RequestType;
use App\Services\RequestTypeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function saveFormPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Community Outreach',
        'route_name' => 'community-outreach',
        'fields' => [
            ['field_key' => 'topic', 'field_label' => 'Topic', 'field_type' => 'text', 'is_required' => true],
        ],
        'rows' => [],
        'pdf_template' => [
            'html' => '<p><span class="field-token" data-field="topic" contenteditable="false">Topic</span></p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
        ],
    ], $overrides);
}

it('provisions a request type named after the form on save', function () {
    $admin = recordsUser(2);

    $this->actingAs($admin)
        ->postJson(route('admin.form-builder.store'), saveFormPayload())
        ->assertOk();

    $form = Form::where('route_name', 'community-outreach')->firstOrFail();
    $type = $form->requestType()->first();

    expect($type)->not->toBeNull()
        ->and($type->name)->toBe('Community Outreach Request')
        ->and($type->system_key)->toBe(RequestTypeService::FORM_KEY_PREFIX.$form->getKey())
        ->and($type->category)->toBe(RequestType::CATEGORY_ORGANIZATION)
        ->and($type->is_active)->toBeTrue();
});

it('keeps the request type name in sync when the form is renamed', function () {
    $admin = recordsUser(2);

    $this->actingAs($admin)->postJson(route('admin.form-builder.store'), saveFormPayload())->assertOk();
    $form = Form::where('route_name', 'community-outreach')->firstOrFail();

    $this->actingAs($admin)
        ->putJson(route('admin.form-builder.update', $form), saveFormPayload(['name' => 'Outreach Proposal']))
        ->assertOk();

    expect($form->requestType()->first()->name)->toBe('Outreach Proposal Request')
        // Identity never changes.
        ->and($form->fresh()->request_type_id)->toBe($form->request_type_id);
});

it('suffixes the type name when another request type already holds it', function () {
    RequestType::query()->create([
        'name' => 'Community Outreach Request',
        'category' => RequestType::CATEGORY_ORGANIZATION,
        'is_active' => true,
    ]);

    $this->actingAs(recordsUser(2))
        ->postJson(route('admin.form-builder.store'), saveFormPayload())
        ->assertOk();

    $form = Form::where('route_name', 'community-outreach')->firstOrFail();

    expect($form->requestType()->first()->name)
        ->toBe('Community Outreach Request #'.$form->getKey());
});

it('skips provisioning for sign-up, new-event and new-workplan bindings', function () {
    $admin = recordsUser(2);

    $this->actingAs($admin)
        ->postJson(route('admin.form-builder.store'), saveFormPayload(['system_function' => 'new_event']))
        ->assertOk();

    $form = Form::where('route_name', 'community-outreach')->firstOrFail();
    expect($form->request_type_id)->toBeNull();

    // Unbinding on a later save provisions the type…
    $this->actingAs($admin)
        ->putJson(route('admin.form-builder.update', $form), saveFormPayload(['system_function' => null]))
        ->assertOk();
    expect($form->fresh()->request_type_id)->not->toBeNull();

    // …and re-binding to an excepted function clears the link again.
    $this->actingAs($admin)
        ->putJson(route('admin.form-builder.update', $form), saveFormPayload(['system_function' => 'new_workplan']))
        ->assertOk();
    expect($form->fresh()->request_type_id)->toBeNull();
});

it('provisions a request type for membership-bound forms', function () {
    $this->actingAs(recordsUser(2))
        ->postJson(route('admin.form-builder.store'), saveFormPayload([
            'system_function' => 'membership_registration',
        ]))
        ->assertOk();

    $form = Form::where('route_name', 'community-outreach')->firstOrFail();
    expect($form->request_type_id)->not->toBeNull();
});

it('deactivates per-form request types on forms:wipe --force', function () {
    $this->actingAs(recordsUser(2))
        ->postJson(route('admin.form-builder.store'), saveFormPayload())
        ->assertOk();

    $form = Form::where('route_name', 'community-outreach')->firstOrFail();
    $typeId = (int) $form->request_type_id;

    $this->artisan('forms:wipe', ['--force' => true])->assertSuccessful();

    expect(Form::query()->count())->toBe(0)
        ->and(RequestType::query()->findOrFail($typeId)->is_active)->toBeFalse();
});
