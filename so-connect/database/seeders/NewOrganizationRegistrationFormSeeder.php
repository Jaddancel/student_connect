<?php

namespace Database\Seeders;

use App\Forms\FieldType;
use App\Forms\SystemFunction;
use App\Models\Form;
use App\Models\Form\FormDescription;
use Illuminate\Database\Seeder;

class NewOrganizationRegistrationFormSeeder extends Seeder
{
    /** Create the public registration form without replacing an admin's form. */
    public function run(): void
    {
        if (Form::query()->where('system_function', SystemFunction::NEW_ORGANIZATION_REGISTRATION)->exists()) {
            return;
        }

        $fields = [
            ['field_key' => 'organization_name', 'field_label' => 'Organization Name', 'field_type' => FieldType::TEXT, 'is_required' => true],
            ['field_key' => 'organization_initials', 'field_label' => 'Organization Initials', 'field_type' => FieldType::TEXT, 'is_required' => true],
            ['field_key' => 'organization_description', 'field_label' => 'Description', 'field_type' => FieldType::TEXTAREA, 'is_required' => false, 'field_options' => ['rows' => 4]],
            ['field_key' => 'organization_type', 'field_label' => 'Organization Type', 'field_type' => FieldType::ORGANIZATION_TYPE_SELECT, 'is_required' => true],
            ['field_key' => 'president_email', 'field_label' => 'New President Email', 'field_type' => FieldType::NEW_PRESIDENT_EMAIL, 'is_required' => true],
            ['field_key' => 'officer_email', 'field_label' => 'New Officer Email', 'field_type' => FieldType::NEW_OFFICER_EMAIL, 'is_required' => true],
        ];

        $form = Form::query()->create([
            'route_name' => 'new-organization-registration',
            'name' => 'New Organization Registration',
            'description_text' => 'Register a new organization in the system.',
            'system_function' => SystemFunction::NEW_ORGANIZATION_REGISTRATION,
            'is_active' => true,
            'is_published' => true,
            'layout' => ['rows' => array_map(
                fn (array $field) => ['columns' => [['span' => 12, 'fields' => [$field['field_key']]]]],
                $fields,
            )],
            'pdf_template' => [
                'html' => '<h2>New Organization Registration</h2>'
                    .'<p><strong>Organization Name:</strong> <span data-field="organization_name"></span></p>'
                    .'<p><strong>Initials:</strong> <span data-field="organization_initials"></span></p>'
                    .'<p><strong>Description:</strong> <span data-field="organization_description"></span></p>'
                    .'<p><strong>Type:</strong> <span data-field="organization_type"></span></p>'
                    .'<p><strong>New President Email:</strong> <span data-field="president_email"></span></p>'
                    .'<p><strong>New Officer Email:</strong> <span data-field="officer_email"></span></p>',
                'page' => ['size' => 'a4', 'orientation' => 'portrait'],
                'font' => ['family' => "'Times New Roman', Times, serif", 'size' => '12px'],
                'header' => ['title' => 'New Organization Registration', 'subtitle' => 'Student Organization Registration', 'align' => 'center'],
                'footer' => [],
            ],
        ]);

        foreach ($fields as $index => $field) {
            FormDescription::query()->create($field + [
                'form_id' => $form->getKey(),
                'field_order' => $index + 1,
            ]);
        }
    }
}