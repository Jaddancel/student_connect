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
            ['field_key' => 'organization_description', 'field_label' => 'Purpose and Objectives', 'field_type' => FieldType::TEXTAREA, 'is_required' => true, 'field_options' => ['rows' => 4]],
            ['field_key' => 'organization_type', 'field_label' => 'Organization Type', 'field_type' => FieldType::ORGANIZATION_TYPE_SELECT, 'is_required' => true],
            ['field_key' => 'target_membership', 'field_label' => 'Target Membership', 'field_type' => FieldType::TEXTAREA, 'is_required' => true, 'field_options' => ['rows' => 3]],
            ['field_key' => 'freshman', 'field_label' => 'No. of Freshman Members', 'field_type' => FieldType::NUMBER, 'is_required' => false],
            ['field_key' => 'sophomore', 'field_label' => 'No. of Sophomore Members', 'field_type' => FieldType::NUMBER, 'is_required' => false],
            ['field_key' => 'junior', 'field_label' => 'No. of Junior Members', 'field_type' => FieldType::NUMBER, 'is_required' => false],
            ['field_key' => 'total', 'field_label' => 'Total Members', 'field_type' => FieldType::COMPUTED, 'is_required' => false, 'field_options' => [
                'formula' => 'sum', 'args' => ['freshman', 'sophomore', 'junior'],
            ]],
            ['field_key' => 'president_email', 'field_label' => 'New President Email', 'field_type' => FieldType::NEW_PRESIDENT_EMAIL, 'is_required' => true],
            ['field_key' => 'officer_email', 'field_label' => 'New Officer Email', 'field_type' => FieldType::NEW_OFFICER_EMAIL, 'is_required' => true],
            ['field_key' => 'faculty_adviser', 'field_label' => 'Proposed Faculty Adviser', 'field_type' => FieldType::TEXT, 'is_required' => true],
            ['field_key' => 'constitution_and_bylaws', 'field_label' => 'Constitution and By-Laws', 'field_type' => FieldType::FILE, 'is_required' => true],
            ['field_key' => 'founding_members_list', 'field_label' => 'List of Founding Members', 'field_type' => FieldType::FILE, 'is_required' => true],
            ['field_key' => 'proposed_officers_list', 'field_label' => 'List of Proposed Officers', 'field_type' => FieldType::FILE, 'is_required' => true],
            ['field_key' => 'faculty_adviser_acceptance', 'field_label' => 'Faculty Adviser Acceptance', 'field_type' => FieldType::FILE, 'is_required' => true],
            ['field_key' => 'organization_logo', 'field_label' => 'Organization Logo', 'field_type' => FieldType::IMAGE, 'is_required' => true],
            ['field_key' => 'gpoa_budget', 'field_label' => 'General Plan of Action (GPOA) with Proposed Budget', 'field_type' => FieldType::FILE, 'is_required' => true],
        ];

        $form = Form::query()->create([
            'route_name' => 'new-organization-registration',
            'name' => 'New Organization Registration',
            'description_text' => 'Apply for OSSD recognition of a brand-new student organization.',
            'system_function' => SystemFunction::NEW_ORGANIZATION_REGISTRATION,
            'is_active' => true,
            'is_published' => true,
            'layout' => ['rows' => $this->applicationRows()],
            'pdf_template' => [
                'html' => '<h2>New Organization Registration</h2>'
                    .'<p><strong>Organization Name:</strong> <span data-field="organization_name"></span></p>'
                    .'<p><strong>Initials:</strong> <span data-field="organization_initials"></span></p>'
                    .'<p><strong>Description:</strong> <span data-field="organization_description"></span></p>'
                    .'<p><strong>Type:</strong> <span data-field="organization_type"></span></p>'
                    .'<p><strong>Target Membership:</strong> <span data-field="target_membership"></span></p>'
                    .'<p><strong>Members - Freshman:</strong> <span data-field="freshman"></span>'
                    .' <strong>Sophomore:</strong> <span data-field="sophomore"></span>'
                    .' <strong>Junior:</strong> <span data-field="junior"></span>'
                    .' <strong>Total:</strong> <span data-field="total"></span></p>'
                    .'<p><strong>New President Email:</strong> <span data-field="president_email"></span></p>'
                    .'<p><strong>New Officer Email:</strong> <span data-field="officer_email"></span></p>'
                    .'<p><strong>Proposed Faculty Adviser:</strong> <span data-field="faculty_adviser"></span></p>'
                    .'<h3>Submitted documents</h3>'
                    .'<p>Constitution and By-Laws; List of Founding Members; List of Proposed Officers; Faculty Adviser Acceptance; Organization Logo; General Plan of Action with Proposed Budget</p>',
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

    /** @return array<int,array<string,mixed>> */
    private function applicationRows(): array
    {
        return [
            ['header' => '1. Proposed organization', 'static_text' => 'Tell OSSD what the organization will be called, why it should exist, and who it intends to serve.', 'columns' => [
                ['span' => 8, 'fields' => ['organization_name']],
                ['span' => 4, 'fields' => ['organization_initials']],
            ]],
            ['columns' => [['span' => 12, 'fields' => ['organization_description']]]],
            ['columns' => [
                ['span' => 6, 'fields' => ['organization_type']],
                ['span' => 6, 'fields' => ['target_membership']],
            ]],
            ['header' => '2. Founding membership', 'static_text' => 'Enter your estimated currently enrolled founding members.', 'columns' => [
                ['span' => 3, 'fields' => ['freshman']],
                ['span' => 3, 'fields' => ['sophomore']],
                ['span' => 3, 'fields' => ['junior']],
                ['span' => 3, 'fields' => ['total']],
            ]],
            ['header' => '3. Founding officers and adviser', 'static_text' => 'Use active email addresses. Unregistered officers will receive an invitation after approval.', 'columns' => [
                ['span' => 6, 'fields' => ['president_email']],
                ['span' => 6, 'fields' => ['officer_email']],
            ]],
            ['columns' => [['span' => 12, 'fields' => ['faculty_adviser']]]],
            ['header' => '4. Required documents', 'static_text' => 'Upload clear PDF, JPG, PNG, or HEIC files. Requirements remain subject to OSSD\'s official checklist.', 'columns' => [
                ['span' => 6, 'fields' => ['constitution_and_bylaws']],
                ['span' => 6, 'fields' => ['founding_members_list']],
            ]],
            ['columns' => [
                ['span' => 6, 'fields' => ['proposed_officers_list']],
                ['span' => 6, 'fields' => ['faculty_adviser_acceptance']],
            ]],
            ['columns' => [
                ['span' => 6, 'fields' => ['organization_logo']],
                ['span' => 6, 'fields' => ['gpoa_budget']],
            ]],
        ];
    }
}
