<?php

namespace Database\Seeders;

use App\Models\Form;
use App\Models\Form\FormDescription;
use Illuminate\Database\Seeder;

class FormPageSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedStudentLeaderDirectory();
        $this->seedJointStatement();
    }

    private function seedStudentLeaderDirectory(): void
    {
        $form = Form::updateOrCreate(
            ['route_name' => 'student-leader-directory'],
            [
                'name' => 'Directory of Student Leader',
                'is_active' => true,
                'is_published' => false,
                'sidebar_group' => ['president'],
            ]
        );

        $fields = [
            ['field_key' => 'semester',              'field_label' => 'Semester',              'field_type' => 'select',   'is_required' => true],
            ['field_key' => 'season',                'field_label' => 'Season',                'field_type' => 'select',   'is_required' => true],
            ['field_key' => 'school_year',           'field_label' => 'School Year',           'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'name',                  'field_label' => 'Name',                  'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'position',              'field_label' => 'Position',              'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'contact_number',        'field_label' => 'Contact Number',        'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'photo',                 'field_label' => 'Photo',                 'field_type' => 'file',     'is_required' => false],
            ['field_key' => 'organization',          'field_label' => 'Organization',          'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'faculty_advisers',      'field_label' => 'Faculty Advisers',      'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'age',                   'field_label' => 'Age',                   'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'sex',                   'field_label' => 'Sex',                   'field_type' => 'select',   'is_required' => true],
            ['field_key' => 'religious_affiliation', 'field_label' => 'Religious Affiliation', 'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'nationality',           'field_label' => 'Nationality',           'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'birthplace',            'field_label' => 'Birthplace',            'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'birthday',              'field_label' => 'Birthday',              'field_type' => 'date',     'is_required' => false],
            ['field_key' => 'present_address',       'field_label' => 'Present Address',       'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'home_address',          'field_label' => 'Home Address',          'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'parents_guardian',      'field_label' => 'Parents/Guardian',      'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'course',                'field_label' => 'Course',                'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'year_level',            'field_label' => 'Year Level',            'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'talents_hobbies',       'field_label' => 'Talents & Hobbies',     'field_type' => 'textarea', 'is_required' => false],
            ['field_key' => 'financial_support',     'field_label' => 'Financial Support',     'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'scholar_provider',      'field_label' => 'Scholar Provider',      'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'others_specify',        'field_label' => 'Others (Specify)',       'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'date_filed',            'field_label' => 'Date Filed',            'field_type' => 'date',     'is_required' => true],
            ['field_key' => 'signature',             'field_label' => 'Signature',             'field_type' => 'file',     'is_required' => false],
        ];

        foreach ($fields as $order => $field) {
            FormDescription::updateOrCreate(
                ['form_id' => $form->id, 'field_key' => $field['field_key']],
                array_merge($field, ['field_order' => $order + 1])
            );
        }
    }

    private function seedJointStatement(): void
    {
        $form = Form::updateOrCreate(
            ['route_name' => 'joint-statement'],
            [
                'name' => 'Joint Statement of Involvement/Commitment',
                'is_active' => true,
                'is_published' => false,
                'sidebar_group' => ['president'],
            ]
        );

        $fields = [
            ['field_key' => 'date',                 'field_label' => 'Date',                  'field_type' => 'date', 'is_required' => true],
            ['field_key' => 'organization',         'field_label' => 'Organization',          'field_type' => 'text', 'is_required' => true],
            ['field_key' => 'category',             'field_label' => 'Category',              'field_type' => 'text', 'is_required' => true],
            ['field_key' => 'president_name',       'field_label' => 'President Name',        'field_type' => 'text', 'is_required' => true],
            ['field_key' => 'president_contact',    'field_label' => 'President Contact',     'field_type' => 'text', 'is_required' => true],
            ['field_key' => 'president_signature',  'field_label' => 'President Signature',   'field_type' => 'file', 'is_required' => false],
            ['field_key' => 'adviser1_name',        'field_label' => 'Adviser 1 Name',        'field_type' => 'text', 'is_required' => true],
            ['field_key' => 'adviser1_contact',     'field_label' => 'Adviser 1 Contact',     'field_type' => 'text', 'is_required' => true],
            ['field_key' => 'adviser1_signature',   'field_label' => 'Adviser 1 Signature',   'field_type' => 'file', 'is_required' => false],
            ['field_key' => 'adviser2_name',        'field_label' => 'Adviser 2 Name',        'field_type' => 'text', 'is_required' => false],
            ['field_key' => 'adviser2_contact',     'field_label' => 'Adviser 2 Contact',     'field_type' => 'text', 'is_required' => false],
            ['field_key' => 'adviser2_signature',   'field_label' => 'Adviser 2 Signature',   'field_type' => 'file', 'is_required' => false],
        ];

        foreach ($fields as $order => $field) {
            FormDescription::updateOrCreate(
                ['form_id' => $form->id, 'field_key' => $field['field_key']],
                array_merge($field, ['field_order' => $order + 1])
            );
        }
    }
}
