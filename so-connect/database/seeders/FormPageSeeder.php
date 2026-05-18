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
        $this->seedOrganizationRecognition();
        $this->seedAccomplishmentReport();
        $this->seedWorkplan();
        $this->seedFinancialReport();
        $this->seedActivityRequest();
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
            ['field_key' => 'semester',             'field_label' => 'Semester',              'field_type' => 'select',   'is_required' => true],
            ['field_key' => 'season',               'field_label' => 'Season',                'field_type' => 'select',   'is_required' => true],
            ['field_key' => 'schoolYear',           'field_label' => 'School Year',           'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'name',                 'field_label' => 'Name',                  'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'position',             'field_label' => 'Position',              'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'contactNumber',        'field_label' => 'Contact Number',        'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'photo',                'field_label' => 'Photo',                 'field_type' => 'file',     'is_required' => true],
            ['field_key' => 'organization',         'field_label' => 'Organization',          'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'facultyAdvisers',      'field_label' => 'Faculty Advisers',      'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'age',                  'field_label' => 'Age',                   'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'sex',                  'field_label' => 'Sex',                   'field_type' => 'select',   'is_required' => true],
            ['field_key' => 'religiousAffiliation', 'field_label' => 'Religious Affiliation', 'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'nationality',          'field_label' => 'Nationality',           'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'birthplace',           'field_label' => 'Birthplace',            'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'birthday',             'field_label' => 'Birthday',              'field_type' => 'date',     'is_required' => true],
            ['field_key' => 'presentAddress',       'field_label' => 'Present Address',       'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'homeAddress',          'field_label' => 'Home Address',          'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'parentsGuardian',      'field_label' => 'Parents/Guardian',      'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'course',               'field_label' => 'Course',                'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'yearLevel',            'field_label' => 'Year Level',            'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'talentsHobbies',       'field_label' => 'Talents & Hobbies',     'field_type' => 'textarea', 'is_required' => false],
            ['field_key' => 'c1',                   'field_label' => 'Financial Support: Parents/Guardians', 'field_type' => 'checkbox', 'is_required' => false],
            ['field_key' => 'c2',                   'field_label' => 'Financial Support: Scholarship',       'field_type' => 'checkbox', 'is_required' => false],
            ['field_key' => 'scholarProvider',      'field_label' => 'Scholar Provider',      'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'c3',                   'field_label' => 'Financial Support: Assistantship',    'field_type' => 'checkbox', 'is_required' => false],
            ['field_key' => 'c4',                   'field_label' => 'Financial Support: Others',           'field_type' => 'checkbox', 'is_required' => false],
            ['field_key' => 'othersSpecify',        'field_label' => 'Others (Specify)',       'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'dateFiled',            'field_label' => 'Date Filed',            'field_type' => 'date',     'is_required' => true],
            ['field_key' => 'signature',            'field_label' => 'Signature',             'field_type' => 'file',     'is_required' => true],
        ];

        $keys = array_column($fields, 'field_key');

        foreach ($fields as $order => $field) {
            FormDescription::updateOrCreate(
                ['form_id' => $form->id, 'field_key' => $field['field_key']],
                array_merge($field, ['field_order' => $order + 1])
            );
        }

        FormDescription::where('form_id', $form->id)->whereNotIn('field_key', $keys)->delete();
    }

    private function seedOrganizationRecognition(): void
    {
        $form = Form::updateOrCreate(
            ['route_name' => 'organization-recognition'],
            [
                'name' => 'Application for Recognition/Renewal',
                'is_active' => true,
                'is_published' => true,
                'sidebar_group' => ['president', 'admin'],
            ]
        );

        $fields = [
            ['field_key' => 'c1',                  'field_label' => 'Recognition',                                  'field_type' => 'radio',    'is_required' => false],
            ['field_key' => 'c2',                  'field_label' => 'Renewal',                                      'field_type' => 'radio',    'is_required' => false],
            ['field_key' => 'nameOfOrganization',  'field_label' => 'Name of Organization',                         'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'presidentName',       'field_label' => 'President',                                    'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'facultyAdvisers',     'field_label' => 'Faculty Adviser/s',                            'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'recognitionDate',     'field_label' => 'Date of 1st Recognition',                      'field_type' => 'date',     'is_required' => false],
            ['field_key' => 'freshmanNumber',      'field_label' => 'No. of Members – Freshman',                    'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'sophomoreNumber',     'field_label' => 'No. of Members – Sophomore',                   'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'juniorNumber',        'field_label' => 'No. of Members – Junior',                      'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'total',               'field_label' => 'Total Members',                                'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'objectives',          'field_label' => 'Objectives',                                   'field_type' => 'textarea', 'is_required' => false],
            ['field_key' => 'workplan',            'field_label' => 'Workplan',                                     'field_type' => 'textarea', 'is_required' => false],
            ['field_key' => 'adviserLeft',         'field_label' => 'Adviser',                                      'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'adviserRight',        'field_label' => 'Adviser',                                      'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'chair',               'field_label' => 'Chair, Student Organizations',                 'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'director',            'field_label' => 'Director, Student Services and Development',   'field_type' => 'text',     'is_required' => false],
        ];

        $keys = array_column($fields, 'field_key');

        foreach ($fields as $order => $field) {
            FormDescription::updateOrCreate(
                ['form_id' => $form->id, 'field_key' => $field['field_key']],
                array_merge($field, ['field_order' => $order + 1])
            );
        }

        FormDescription::where('form_id', $form->id)->whereNotIn('field_key', $keys)->delete();
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
            ['field_key' => 'date',               'field_label' => 'Date',                  'field_type' => 'date', 'is_required' => true],
            ['field_key' => 'organization',       'field_label' => 'Organization',          'field_type' => 'text', 'is_required' => true],
            ['field_key' => 'category',           'field_label' => 'Category',              'field_type' => 'text', 'is_required' => true],
            ['field_key' => 'presidentName',      'field_label' => 'President Name',        'field_type' => 'text', 'is_required' => true],
            ['field_key' => 'presidentContact',   'field_label' => 'President Contact',     'field_type' => 'text', 'is_required' => true],
            ['field_key' => 'presidentSignature', 'field_label' => 'President Signature',   'field_type' => 'file', 'is_required' => true],
            ['field_key' => 'adviser1Name',       'field_label' => 'Adviser 1 Name',        'field_type' => 'text', 'is_required' => true],
            ['field_key' => 'adviser1Contact',    'field_label' => 'Adviser 1 Contact',     'field_type' => 'text', 'is_required' => true],
            ['field_key' => 'adviser1Signature',  'field_label' => 'Adviser 1 Signature',   'field_type' => 'file', 'is_required' => true],
            ['field_key' => 'adviser2Name',       'field_label' => 'Adviser 2 Name',        'field_type' => 'text', 'is_required' => false],
            ['field_key' => 'adviser2Contact',    'field_label' => 'Adviser 2 Contact',     'field_type' => 'text', 'is_required' => false],
            ['field_key' => 'adviser2Signature',  'field_label' => 'Adviser 2 Signature',   'field_type' => 'file', 'is_required' => false],
        ];

        foreach ($fields as $order => $field) {
            FormDescription::updateOrCreate(
                ['form_id' => $form->id, 'field_key' => $field['field_key']],
                array_merge($field, ['field_order' => $order + 1])
            );
        }
    }

    private function seedWorkplan(): void
    {
        $form = Form::updateOrCreate(
            ['route_name' => 'workplan'],
            [
                'name'          => 'Workplan',
                'is_active'     => true,
                'is_published'  => true,
                'sidebar_group' => ['president', 'admin'],
            ]
        );

        $fields = [
            ['field_key' => 'organization',     'field_label' => 'Name of Organization',  'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'schoolyear',       'field_label' => 'School Year',           'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'activities',       'field_label' => 'Title of Activity',     'field_type' => 'textarea', 'is_required' => false],
            ['field_key' => 'target',           'field_label' => 'Target Date',           'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'resources',        'field_label' => 'Resources Needed',      'field_type' => 'textarea', 'is_required' => true],
            ['field_key' => 'people',           'field_label' => 'Persons Involved',      'field_type' => 'textarea', 'is_required' => true],
            ['field_key' => 'name',             'field_label' => 'Prepared By (Name)',    'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'advisername',      'field_label' => 'Adviser Name',          'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'signature',        'field_label' => 'President Signature',   'field_type' => 'file',     'is_required' => false],
        ];

        $keys = array_column($fields, 'field_key');

        foreach ($fields as $order => $field) {
            FormDescription::updateOrCreate(
                ['form_id' => $form->id, 'field_key' => $field['field_key']],
                array_merge($field, ['field_order' => $order + 1])
            );
        }

        FormDescription::where('form_id', $form->id)->whereNotIn('field_key', $keys)->delete();
    }

    private function seedAccomplishmentReport(): void
    {
        $form = Form::updateOrCreate(
            ['route_name' => 'accomplishment-report'],
            [
                'name'          => 'Accomplishment Report',
                'is_active'     => true,
                'is_published'  => true,
                'sidebar_group' => ['president', 'admin'],
            ]
        );

        $fields = [
            ['field_key' => 'organization', 'field_label' => 'Name of Organization',   'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'schoolYear',   'field_label' => 'School Year',             'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'title',        'field_label' => 'Title of Activity',       'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'date',         'field_label' => 'Date',                    'field_type' => 'date',     'is_required' => true],
            ['field_key' => 'people',       'field_label' => 'Persons Involved',        'field_type' => 'textarea', 'is_required' => true],
            ['field_key' => 'problem',      'field_label' => 'Problem/s Encountered',   'field_type' => 'textarea', 'is_required' => false],
            ['field_key' => 'phots',        'field_label' => 'Documentation',           'field_type' => 'file',     'is_required' => false],
            ['field_key' => 'name',         'field_label' => 'Prepared By (Name)',      'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'signature1',   'field_label' => 'Signature (Prepared By)', 'field_type' => 'file',     'is_required' => true],
        ];

        foreach ($fields as $order => $field) {
            FormDescription::updateOrCreate(
                ['form_id' => $form->id, 'field_key' => $field['field_key']],
                array_merge($field, ['field_order' => $order + 1])
            );
        }

        $keys = array_column($fields, 'field_key');
        FormDescription::where('form_id', $form->id)->whereNotIn('field_key', $keys)->delete();
    }

    private function seedActivityRequest(): void
    {
        $form = Form::updateOrCreate(
            ['route_name' => 'activity-request'],
            [
                'name'          => 'Request for Organizational Meeting/Services/Projects/Activities',
                'is_active'     => true,
                'is_published'  => true,
                'sidebar_group' => ['president', 'admin'],
            ]
        );

        $fields = [
            ['field_key' => 'date',                             'field_label' => 'Date',                               'field_type' => 'date',     'is_required' => true],
            ['field_key' => 'organization',                     'field_label' => 'Name of Organization',               'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'projectActivity',                  'field_label' => 'Nature of Project/Activity',         'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'purposed',                         'field_label' => 'Purpose of Activity',                'field_type' => 'textarea', 'is_required' => true],
            ['field_key' => 'dayOfTheWeek',                     'field_label' => 'Day of the Week',                    'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'time',                             'field_label' => 'Time',                               'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'placeAndVenue',                    'field_label' => 'Place/Venue',                        'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'facilitiesOrEquipmentToBeUsedRow', 'field_label' => 'Facilities/Equipment to be Used',   'field_type' => 'textarea', 'is_required' => false],
            ['field_key' => 'presidentName',                    'field_label' => 'President Name',                     'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'presidentContactNo',               'field_label' => 'President Contact Number',           'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'adviserRow',                       'field_label' => 'Faculty Advisers',                   'field_type' => 'textarea', 'is_required' => true],
            ['field_key' => 'collegeDean',                      'field_label' => 'College Dean',                       'field_type' => 'text',     'is_required' => false],
        ];

        $keys = array_column($fields, 'field_key');

        foreach ($fields as $order => $field) {
            FormDescription::updateOrCreate(
                ['form_id' => $form->id, 'field_key' => $field['field_key']],
                array_merge($field, ['field_order' => $order + 1])
            );
        }

        FormDescription::where('form_id', $form->id)->whereNotIn('field_key', $keys)->delete();
    }

    private function seedFinancialReport(): void
    {
        $form = Form::updateOrCreate(
            ['route_name' => 'financial-report'],
            [
                'name'          => 'Financial Report',
                'is_active'     => true,
                'is_published'  => true,
                'sidebar_group' => ['president', 'admin'],
            ]
        );

        $fields = [
            ['field_key' => 'organization', 'field_label' => 'Name of Organization', 'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'schoolYear',   'field_label' => 'School Year',           'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'period',       'field_label' => 'Period Covered',        'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'income',       'field_label' => 'Total Income',          'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'expenses',     'field_label' => 'Total Expenses',        'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'balance',      'field_label' => 'Balance',               'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'remarks',      'field_label' => 'Remarks',               'field_type' => 'textarea', 'is_required' => false],
            ['field_key' => 'name',         'field_label' => 'Prepared By (Name)',    'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'signature',    'field_label' => 'Signature',             'field_type' => 'file',     'is_required' => false],
            ['field_key' => 'adviserName',  'field_label' => 'Adviser Name',          'field_type' => 'text',     'is_required' => false],
        ];

        $keys = array_column($fields, 'field_key');

        foreach ($fields as $order => $field) {
            FormDescription::updateOrCreate(
                ['form_id' => $form->id, 'field_key' => $field['field_key']],
                array_merge($field, ['field_order' => $order + 1])
            );
        }

        FormDescription::where('form_id', $form->id)->whereNotIn('field_key', $keys)->delete();
    }
}
