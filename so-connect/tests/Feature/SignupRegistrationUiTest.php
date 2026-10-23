<?php

use App\Models\Form;
use App\Models\Form\FormDescription;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the guided registration experience for a sign-up form', function () {
    $form = Form::query()->create([
        'name' => 'Officer Sign Up',
        'route_name' => 'guided-signup',
        'system_function' => 'sign_up',
        'is_active' => true,
        'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => [
            'html' => '<p><span data-field="first_name"></span></p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
        ],
    ]);

    FormDescription::query()->create([
        'form_id' => $form->getKey(),
        'field_key' => 'student_id',
        'field_label' => 'Student ID number',
        'field_type' => 'id-scan',
        'is_required' => true,
        'field_order' => 1,
    ]);

    FormDescription::query()->create([
        'form_id' => $form->getKey(),
        'field_key' => 'first_name',
        'field_label' => 'First name',
        'field_type' => 'text',
        'is_required' => true,
        'field_order' => 2,
    ]);

    $this->get(route('forms.render', $form->route_name))
        ->assertOk()
        ->assertSee('Create your officer account')
        ->assertSee('Student officer registration')
        ->assertSee('Scan student ID')
        ->assertSee('Details and signature')
        ->assertSee('Submit registration')
        ->assertSee('What happens next?')
        ->assertDontSee('Start Manual Filling')
        ->assertDontSee('>Clear<', false);
});

it('keeps the standard form presentation for non-registration forms', function () {
    $form = Form::query()->create([
        'name' => 'Standard Request',
        'route_name' => 'standard-request',
        'system_function' => 'new_organization_registration',
        'is_active' => true,
        'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => [
            'html' => '<p>Request</p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
        ],
    ]);

    $this->get(route('forms.render', $form->route_name))
        ->assertOk()
        ->assertDontSee('Create your officer account')
        ->assertSee('Submit for approval');
});
