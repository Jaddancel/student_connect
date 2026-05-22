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
        $this->seedProjectRequest();
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
            ['field_key' => 'nameoforganization',  'field_label' => 'Name of Organization',                         'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'presidentname',       'field_label' => 'President',                                    'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'nameOfAdviserRow',    'field_label' => 'Faculty Adviser/s',                            'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'date',                'field_label' => 'Date of 1st Recognition',                      'field_type' => 'date',     'is_required' => false],
            ['field_key' => 'freshman',            'field_label' => 'No. of Members – Freshman',                    'field_type' => 'number',   'is_required' => false],
            ['field_key' => 'sophomore',           'field_label' => 'No. of Members – Sophomore',                   'field_type' => 'number',   'is_required' => false],
            ['field_key' => 'junior',              'field_label' => 'No. of Members – Junior',                      'field_type' => 'number',   'is_required' => false],
            ['field_key' => 'total',               'field_label' => 'Total Members',                                'field_type' => 'number',   'is_required' => false],
            ['field_key' => 'objectives',          'field_label' => 'Objectives',                                   'field_type' => 'textarea', 'is_required' => false],
            ['field_key' => 'workplan',            'field_label' => 'Workplan',                                     'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'nameOfPresident',     'field_label' => 'President (Signatory)',                        'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'signaturePresident',  'field_label' => 'President Signature',                          'field_type' => 'file',     'is_required' => true],
            ['field_key' => 'adviserleft',         'field_label' => 'Adviser (Left)',                               'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'adviserright',        'field_label' => 'Adviser (Right)',                              'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'chair',               'field_label' => 'Chair, Student Organizations',                 'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'signatureChair',      'field_label' => 'Chair Signature (Recommending Approval)',      'field_type' => 'file',     'is_required' => false],
            ['field_key' => 'director',            'field_label' => 'Director, Student Services and Development',   'field_type' => 'text',     'is_required' => false],
            ['field_key' => 'signatureDirector',   'field_label' => 'Director Signature (Approved)',                'field_type' => 'file',     'is_required' => false],
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
            ['field_key' => 'photos',       'field_label' => 'Documentation',           'field_type' => 'file',     'is_required' => false],
            ['field_key' => 'name',         'field_label' => 'Prepared By (Name)',      'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'signature',    'field_label' => 'Signature (Prepared By)', 'field_type' => 'file',     'is_required' => true],
            ['field_key' => 'adviserName',  'field_label' => 'Adviser Name (Noted By)', 'field_type' => 'text',     'is_required' => false],
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
                'sidebar_group' => [],
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
            ['field_key' => 'activityTypes',                    'field_label' => 'Activity Types',                     'field_type' => 'checkbox',  'is_required' => false],
            ['field_key' => 'areaScope',                        'field_label' => 'Area Scope',                         'field_type' => 'select',    'is_required' => false],
            ['field_key' => 'sponsor',                          'field_label' => 'Sponsor',                            'field_type' => 'select',    'is_required' => false],
            ['field_key' => 'extensionServices',                'field_label' => 'Extension Services',                 'field_type' => 'radio',     'is_required' => false],
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

    private function seedProjectRequest(): void
    {
        $form = Form::updateOrCreate(
            ['route_name' => 'project-request'],
            [
                'name'          => 'Project Request',
                'is_active'     => true,
                'is_published'  => true,
                'sidebar_group' => ['president'],
            ]
        );

        $fields = [
            ['field_key' => 'organization',      'field_label' => 'Organization',       'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'projectTitle',      'field_label' => 'Project Title',      'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'natureOfProject',   'field_label' => 'Nature of Project',  'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'projectArea',       'field_label' => 'Project Area',       'field_type' => 'text',     'is_required' => true],
            ['field_key' => 'letterOfIntent',    'field_label' => 'Letter of Intent',   'field_type' => 'textarea', 'is_required' => true],
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
            // Organization details
            ['field_key' => 'organization',          'field_label' => 'Name of Organization',                      'field_type' => 'text',   'is_required' => true],
            ['field_key' => 'date',                  'field_label' => 'School Year',                               'field_type' => 'text',   'is_required' => true],
            // Source of funds rows
            ['field_key' => 'fundSource',            'field_label' => 'Source of Funds',                           'field_type' => 'text',   'is_required' => true],
            ['field_key' => 'amount',                'field_label' => 'Amount (₱)',                                'field_type' => 'number', 'is_required' => true],
            ['field_key' => 'totalFunds',            'field_label' => 'Total Funds',                               'field_type' => 'number', 'is_required' => true],
            // Expense rows
            ['field_key' => 'activityTitle',         'field_label' => 'Activity Title',                            'field_type' => 'text',   'is_required' => false],
            ['field_key' => 'activityDate',          'field_label' => 'Activity Date',                             'field_type' => 'date',   'is_required' => false],
            ['field_key' => 'item',                  'field_label' => 'Item / Description',                        'field_type' => 'text',   'is_required' => false],
            ['field_key' => 'amountPerUnit',         'field_label' => 'Amount per Unit (₱)',                       'field_type' => 'number', 'is_required' => false],
            ['field_key' => 'quantity',              'field_label' => 'Quantity',                                  'field_type' => 'number', 'is_required' => false],
            ['field_key' => 'priceTotal',            'field_label' => 'Row Total (₱)',                             'field_type' => 'number', 'is_required' => false],
            // Financial summary
            ['field_key' => 'totalExpenses',         'field_label' => 'Total Expenses',                            'field_type' => 'number', 'is_required' => true],
            ['field_key' => 'cashOnHand',            'field_label' => 'Cash on Hand',                              'field_type' => 'number', 'is_required' => true],
            // Signatories
            ['field_key' => 'name_of_treasurer',    'field_label' => 'Name of Treasurer',                         'field_type' => 'text',   'is_required' => true],
            ['field_key' => 'signature1',            'field_label' => 'Treasurer Signature',                       'field_type' => 'file',   'is_required' => true],
            ['field_key' => 'name_of_the_auditor',  'field_label' => 'Name of Auditor',                           'field_type' => 'text',   'is_required' => true],
            ['field_key' => 'signature2',            'field_label' => 'Auditor Signature',                         'field_type' => 'file',   'is_required' => true],
            ['field_key' => 'name_of_the_president','field_label' => 'Name of President',                         'field_type' => 'text',   'is_required' => true],
            ['field_key' => 'signature3',            'field_label' => 'President Signature',                       'field_type' => 'file',   'is_required' => true],
            ['field_key' => 'name_of_the_adviser',  'field_label' => 'Name of Adviser',                           'field_type' => 'text',   'is_required' => true],
            ['field_key' => 'signature4',            'field_label' => 'Adviser Signature',                         'field_type' => 'file',   'is_required' => true],
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
