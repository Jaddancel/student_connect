<?php

namespace Database\Seeders;

use App\Forms\FieldType;
use App\Forms\SystemFunction;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Services\RequestTypeService;
use Illuminate\Database\Seeder;

/**
 * Seeds the data-driven Form Builder pages that replaced the hardcoded form
 * controllers/blades: the Sign Up "Directory of Student Officers" (bound to the
 * sign_up system function) plus the five standalone document forms
 * (joint-statement, project-request, organization-recognition,
 * accomplishment-report, financial-report).
 *
 * Each form is created with its historical route_name so its /forms/... URL is
 * served by the generic FormRenderController. Idempotent (updateOrCreate by
 * route_name). Fields un-expressible in the base palette use the special kit
 * types (see App\Forms\FieldKit); those forms carry a `field_kit`.
 */
class FormPagesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDirectory();
        $this->seedNewOrganizationRegistration();
        $this->seedMembershipRegistration();
        $this->seedNewEvent();
        $this->seedJointStatement();
        $this->seedProjectRequest();
        $this->seedOrganizationRecognition();
        $this->seedAccomplishmentReport();
        $this->seedFinancialReport();
    }

    /**
     * Create (or refresh) a form, its fields, layout, printed
     * template and — for plain forms — its request type.
     *
     * `layout` may supply explicit builder rows (headers, multi-column spans);
     * when omitted, a one-field-per-row layout is generated from the fields.
     *
     * @param  array<string,mixed>  $spec
     */
    private function buildForm(array $spec): Form
    {
        $fields = $spec['fields'];

        $form = Form::updateOrCreate(
            ['route_name' => $spec['route_name']],
            [
                'name' => $spec['name'],
                'description_text' => $spec['description'] ?? null,
                'system_function' => $spec['system_function'] ?? null,
                'field_kit' => $spec['field_kit'] ?? null,
                'organization_id' => null,
                'created_by' => null,
                'is_active' => true,
                'is_published' => true,
                'layout' => $spec['layout'] ?? ['rows' => array_map(
                    fn ($f) => ['columns' => [['span' => 12, 'fields' => [$f['key']]]]],
                    $fields,
                )],
                'pdf_template' => [
                    'html' => $spec['pdf']['html'],
                    'page' => ['size' => 'a4', 'orientation' => 'portrait'],
                    'font' => ['family' => "'Times New Roman', Times, serif", 'size' => '12px'],
                    'header' => [
                        'title' => $spec['pdf']['title'] ?? $spec['name'],
                        'subtitle' => $spec['pdf']['subtitle'] ?? '',
                        'align' => 'center',
                    ],
                    'footer' => [],
                ],
            ],
        );

        // Replace field rows so re-seeding tracks spec changes exactly.
        FormDescription::where('form_id', $form->getKey())->delete();
        foreach ($fields as $order => $f) {
            FormDescription::create([
                'form_id' => $form->getKey(),
                'field_key' => $f['key'],
                'field_label' => $f['label'],
                'field_type' => $f['type'],
                'is_required' => (bool) ($f['required'] ?? false),
                'field_order' => $order + 1,
                'placeholder_hint' => $f['placeholder'] ?? null,
                'field_options' => $f['options'] ?? null,
                'universal_key' => $f['universal_key'] ?? null,
            ]);
        }

        // Plain forms get their own request type (system-function forms don't).
        if (($spec['system_function'] ?? null) === null) {
            app(RequestTypeService::class)->resolveFormType($form->fresh(), null);
        }

        return $form;
    }

    /** A labelled field. */
    private function f(string $key, string $label, string $type, array $extra = []): array
    {
        return array_merge(['key' => $key, 'label' => $label, 'type' => $type], $extra);
    }

    private function heading(string $label): array
    {
        return ['key' => 'h_'.md5($label), 'label' => $label, 'type' => FieldType::HEADING];
    }

    /** `<p><strong>Label:</strong> <span data-field="key"></span></p>` */
    private function line(string $label, string $key): string
    {
        return '<p><strong>'.$label.':</strong> <span data-field="'.$key.'"></span></p>';
    }

    // ------------------------------------------------------------------ Sign Up

    private function seedDirectory(): void
    {
        $fields = [
            $this->heading('Period & Identity'),
            $this->f('organization_id', 'Organization', FieldType::ORG_SELECT, ['required' => true]),
            $this->f('position', 'Position', FieldType::POSITION_SELECT, ['required' => true]),
            $this->f('school_year', 'School Year', FieldType::TEXT),
            $this->heading('Scan your ID'),
            $this->f('student_id', 'Student ID number', FieldType::ID_SCAN, ['required' => true]),
            $this->heading('Basic Information'),
            $this->f('first_name', 'First Name', FieldType::TEXT, ['required' => true, 'universal_key' => 'first_name']),
            $this->f('middle_name', 'Middle Name', FieldType::TEXT, ['universal_key' => 'middle_name']),
            $this->f('last_name', 'Last Name', FieldType::TEXT, ['required' => true, 'universal_key' => 'last_name']),
            $this->f('email', 'E-mail', FieldType::NEW_OFFICER_EMAIL, ['required' => true]),
            $this->f('contact_number', 'Contact Number', FieldType::TEXT),
            $this->f('age', 'Age', FieldType::NUMBER),
            $this->f('sex', 'Sex', FieldType::SELECT, ['options' => ['options' => [
                ['value' => 'Male', 'label' => 'Male'], ['value' => 'Female', 'label' => 'Female'],
            ]]]),
            $this->f('religious_affiliation', 'Religious Affiliation', FieldType::TEXT),
            $this->f('nationality', 'Nationality', FieldType::TEXT),
            $this->f('birthday', 'Birthday', FieldType::DATE),
            $this->f('birthplace', 'Birthplace', FieldType::TEXT),
            $this->f('present_address', 'Present Address', FieldType::TEXT, ['required' => true]),
            $this->f('home_address', 'Home Address', FieldType::TEXT),
            $this->f('parents_guardian', 'Parents / Guardian', FieldType::TEXT),
            $this->f('course', 'Course', FieldType::TEXT),
            $this->f('year_level', 'Year Level', FieldType::TEXT),
            $this->f('talents_hobbies', 'Talents / Hobbies', FieldType::TEXT),
            $this->f('scholar_provider', 'Scholarship Provider', FieldType::TEXT),
            $this->f('photo', '1×1 Photo', FieldType::IMAGE),
            $this->f('signature', 'Signature', FieldType::SIGNATURE),
            $this->heading('Account Setup'),
            $this->f('password', 'Password', FieldType::PASSWORD, ['required' => true]),
        ];

        $html = '<h2>Directory of Student Officers</h2>'
            .$this->line('Organization', 'organization_id')
            .$this->line('Position', 'position')
            .$this->line('School Year', 'school_year')
            .$this->line('Name', 'first_name').$this->line('Last Name', 'last_name')
            .$this->line('Email', 'email').$this->line('Contact Number', 'contact_number')
            .$this->line('Course & Year', 'course')
            .$this->line('Present Address', 'present_address')
            .'<p><strong>Signature:</strong></p><p><span data-field="signature"></span></p>';

        $this->buildForm([
            'route_name' => 'student-leader-directory',
            'name' => 'Directory of Student Officers',
            'description' => 'Officer sign-up: request a new officer account. An admin approval creates the account.',
            'system_function' => SystemFunction::SIGN_UP,
            'fields' => $fields,
            'pdf' => [
                'title' => 'Directory of Student Officers',
                'subtitle' => 'Student Organization Registration',
                'html' => $html,
            ],
        ]);
    }

    // ---------------------------------------------------- New Organization Registration

    /**
     * Placeholder form for the New Organization Registration system function.
     * The approval workflow requires the builder to add fields whose keys (or
     * universal keys) are: organization_name, organization_initials,
     * organization_description, and organization_type. Until those fields are
     * present, submissions will be rejected with a configuration message.
     */
    private function seedNewOrganizationRegistration(): void
    {
        $fields = [
            $this->heading('Organization Information'),
            $this->f('organization_name', 'Organization Name', FieldType::TEXT, ['required' => true]),
            $this->f('organization_initials', 'Organization Initials', FieldType::TEXT, ['required' => true]),
            $this->f('organization_description', 'Description', FieldType::TEXTAREA, ['options' => ['rows' => 4]]),
            $this->f('organization_type', 'Organization Type', FieldType::NUMBER, ['required' => true]),
            $this->heading('Membership'),
            $this->f('freshman', 'No. of Freshman Members', FieldType::NUMBER),
            $this->f('sophomore', 'No. of Sophomore Members', FieldType::NUMBER),
            $this->f('junior', 'No. of Junior Members', FieldType::NUMBER),
            $this->f('total', 'Total Members', FieldType::COMPUTED, ['options' => [
                'formula' => 'sum', 'args' => ['freshman', 'sophomore', 'junior'],
            ]]),
            $this->heading('President Contact'),
            $this->f('president_email', 'New President Email', FieldType::NEW_PRESIDENT_EMAIL, ['required' => true]),
            $this->heading('Officer Contact'),
            $this->f('officer_email', 'New Officer Email', FieldType::NEW_OFFICER_EMAIL, ['required' => true]),
        ];

        $html = '<h2>New Organization Registration</h2>'
            .$this->line('Organization Name', 'organization_name')
            .$this->line('Initials', 'organization_initials')
            .$this->line('Description', 'organization_description')
            .$this->line('Type', 'organization_type')
            .'<p><strong>Members - Freshman:</strong> <span data-field="freshman"></span>'
            .' <strong>Sophomore:</strong> <span data-field="sophomore"></span>'
            .' <strong>Junior:</strong> <span data-field="junior"></span>'
            .' <strong>Total:</strong> <span data-field="total"></span></p>'
            .$this->line('New President Email', 'president_email')
            .$this->line('New Officer Email', 'officer_email');

        $this->buildForm([
            'route_name' => 'new-organization-registration',
            'name' => 'New Organization Registration',
            'description' => 'Register a new organization in the system.',
            'system_function' => SystemFunction::NEW_ORGANIZATION_REGISTRATION,
            'fields' => $fields,
            'pdf' => [
                'title' => 'New Organization Registration',
                'subtitle' => 'Student Organization Registration',
                'html' => $html,
            ],
        ]);
    }

    // ------------------------------------------------------ Membership Registration

    /**
     * Org Membership Registration (bound to the membership_registration system
     * function): the same organization + position pickers as the Sign Up kit
     * above, since this asks to hold that position in a DIFFERENT org rather
     * than create a new account. Submissions run through
     * {@see \App\Forms\Handlers\MembershipRegistrationHandler}.
     */
    private function seedMembershipRegistration(): void
    {
        $fields = [
            $this->f('organization_id', 'Organization', FieldType::ORG_SELECT, ['required' => true]),
            $this->f('position', 'Position', FieldType::POSITION_SELECT, ['required' => true]),
        ];

        $html = '<h2>Organization Membership Request</h2>'
            .$this->line('Organization', 'organization_id')
            .$this->line('Position', 'position');

        $this->buildForm([
            'route_name' => 'organization-membership',
            'name' => 'Organization Membership',
            'description' => 'Request membership in another student organization. Requests require approval from the organization president and an admin.',
            'system_function' => SystemFunction::MEMBERSHIP_REGISTRATION,
            'fields' => $fields,
            'pdf' => [
                'title' => 'Organization Membership Request',
                'subtitle' => 'Student Organization Membership',
                'html' => $html,
            ],
        ]);
    }

    // ---------------------------------------------------------------- New Event

    /**
     * The New Event builder form (bound to the new_event system function) that
     * replaced the calendar's hardcoded "Event Plan" drawer and the standalone
     * /events create page. Its submissions run through {@see NewEventHandler}:
     * an approved parent plan + a pending child plan tied to a document request
     * the admin decides in the Activity Requests queue.
     *
     * The field catalog mirrors the event-plan page's "Submit Event Creation
     * Request" form. The system-function handler still owns the event-request
     * lifecycle, while this definition owns the fields shown by the builder.
     */
    private function seedNewEvent(): void
    {
        // Mirrors the form's current Step-1 (builder) configuration: the field
        // catalog plus the explicit row layout (section headers and two-column
        // rows) exactly as arranged in the builder.
        $fields = [
            $this->f('title', 'Activity / Title', FieldType::TEXT, ['required' => true]),
            $this->f('target_date', 'Target Date', FieldType::DATE, ['required' => true]),
            $this->f('event_location', 'Event Location', FieldType::TEXT, ['required' => true]),
            $this->f('event_start_time', 'Start', FieldType::TIME, ['required' => true]),
            $this->f('event_end_time', 'End', FieldType::TIME, ['required' => true]),
            $this->f('purpose_of_activity', 'Purpose of Activity', FieldType::TEXTAREA, [
                'required' => true,
                'options' => ['rows' => 4],
            ]),
            $this->f('university_facilities', 'University Facilities / Equipment to be Used', FieldType::TEXT_LIST, [
                'required' => true,
                'placeholder' => 'e.g. Projector, Sound System, Chairs',
            ]),
            $this->f('president_name', 'President Name', FieldType::TEXT, [
                'required' => true,
                'universal_key' => 'org_president',
            ]),
            $this->f('president_contact', 'President Contact Number', FieldType::TEXT, ['required' => true]),
            $this->f('faculty_advisers', 'Faculty Advisers', FieldType::TEXT_LIST, [
                'required' => true,
                'placeholder' => 'Adviser full name',
            ]),
            $this->f('area_scope', 'Area Scope', FieldType::SELECT, ['required' => true]),
            $this->f('area_scope_other', 'Other Area Scope', FieldType::TEXT),
            $this->f('sponsor', 'Sponsor', FieldType::SELECT, ['required' => true]),
            $this->f('extension_services', 'Extension Services', FieldType::RADIO),
            $this->f('waiver', 'Signed Waiver', FieldType::WAIVER_SCAN, [
                'required' => true,
                'placeholder' => 'Scan or upload the signed activity waiver.',
            ]),
            $this->f('event_type', 'Event Type', FieldType::SELECT, [
                'required' => true,
                'options' => ['options' => [
                    ['label' => 'Seminar / Conference', 'value' => 'option_1'],
                    ['label' => 'Meeting', 'value' => 'option_2'],
                    ['label' => 'Activity', 'value' => 'option_3'],
                ]],
            ]),
            $this->f('other_sponsor', 'Other Sponsor', FieldType::TEXT, [
                'options' => ['visible_when' => ['op' => 'equals', 'field' => 'sponsor', 'value' => 'option_3']],
            ]),
            $this->f('organization_id', 'Organization id', FieldType::ORG_SELECT),
            $this->f('current_semester', 'Current Semester', FieldType::TEXT, [
                'required' => true,
                'universal_key' => 'current_semester',
            ]),
            $this->f('adviser_1_name', 'Adviser 1 Name', FieldType::TEXT, ['required' => true]),
            $this->f('adviser_1_signature', 'Adviser 1 Signature', FieldType::SIGNATURE),
            $this->f('adviser_2_name', 'Adviser 2 Name', FieldType::TEXT, ['required' => true]),
            $this->f('adviser_2_signature', 'Adviser 2 Signature', FieldType::SIGNATURE),
            $this->f('current_date', 'Current Date', FieldType::DATE, [
                'required' => true,
                'universal_key' => 'current_date',
                'options' => ['autofill_now' => true],
            ]),
        ];

        $layout = ['rows' => [
            ['columns' => [
                ['span' => 6, 'fields' => ['current_semester']],
                ['span' => 6, 'fields' => ['current_date']],
            ]],
            ['header' => 'Event Details', 'columns' => [
                ['span' => 12, 'fields' => ['organization_id', 'title', 'target_date', 'event_location', 'event_start_time', 'event_end_time']],
            ]],
            ['header' => 'Activity Details', 'columns' => [
                ['span' => 12, 'fields' => ['purpose_of_activity', 'university_facilities']],
            ]],
            ['header' => 'President & Advisers', 'columns' => [
                ['span' => 12, 'fields' => ['president_name', 'president_contact', 'faculty_advisers']],
            ]],
            ['header' => 'Event Details', 'columns' => [
                ['span' => 12, 'fields' => ['event_type', 'area_scope', 'area_scope_other', 'extension_services']],
            ]],
            ['header' => 'Sponsor Details', 'columns' => [
                ['span' => 12, 'fields' => ['sponsor', 'other_sponsor']],
            ]],
            ['header' => 'Waiver', 'columns' => [
                ['span' => 12, 'fields' => ['waiver']],
            ]],
            ['header' => 'Adviser Details', 'columns' => [
                ['span' => 6, 'fields' => ['adviser_1_name', 'adviser_1_signature']],
                ['span' => 6, 'fields' => ['adviser_2_name', 'adviser_2_signature']],
            ]],
        ]];

        $html = '<h2>Event Request</h2>'
            .$this->line('Organization', 'organization_id')
            .$this->line('Activity / Title', 'title')
            .$this->line('Target Date', 'target_date')
            .$this->line('Location', 'event_location')
            .$this->line('Start', 'event_start_time')
            .$this->line('End', 'event_end_time')
            .'<p><strong>Purpose of Activity:</strong></p><p><span data-field="purpose_of_activity"></span></p>'
            .'<p><strong>Facilities / Equipment:</strong></p><p><span data-field="university_facilities"></span></p>'
            .$this->line('President', 'president_name')
            .$this->line('President Contact', 'president_contact')
            .'<p><strong>Faculty Advisers:</strong></p><p><span data-field="faculty_advisers"></span></p>'
            .$this->line('Event Type', 'event_type')
            .$this->line('Area Scope', 'area_scope')
            .$this->line('Other Area Scope', 'area_scope_other')
            .$this->line('Sponsor', 'sponsor')
            .$this->line('Other Sponsor', 'other_sponsor')
            .$this->line('Extension Services', 'extension_services')
            .$this->line('Adviser 1', 'adviser_1_name')
            .'<p><strong>Adviser 1 Signature:</strong></p><p><span data-field="adviser_1_signature"></span></p>'
            .$this->line('Adviser 2', 'adviser_2_name')
            .'<p><strong>Adviser 2 Signature:</strong></p><p><span data-field="adviser_2_signature"></span></p>'
            .$this->line('Semester', 'current_semester')
            .$this->line('Date', 'current_date');

        $this->buildForm([
            'route_name' => 'new-event',
            'name' => 'New Event',
            'description' => 'Request a new event or activity. An admin approval schedules it on the calendar.',
            'system_function' => SystemFunction::NEW_EVENT,
            'fields' => $fields,
            'layout' => $layout,
            'pdf' => [
                'title' => 'Event Request',
                'subtitle' => 'Student Organization Activity',
                'html' => $html,
            ],
        ]);
    }

    // ------------------------------------------------------------ Joint Statement

    private function seedJointStatement(): void
    {
        $fields = [
            $this->heading('Joint Statement'),
            $this->f('organization_id', 'Organization', FieldType::ORG_SELECT, ['required' => true]),
            $this->f('date', 'Date', FieldType::DATE, ['required' => true, 'options' => ['autofill_now' => true]]),
            $this->f('category', 'Category', FieldType::TEXT, ['universal_key' => 'org_category']),
            $this->f('president_name', 'President Full Name', FieldType::TEXT, ['required' => true, 'universal_key' => 'org_president']),
            $this->f('president_contact', 'President Contact #', FieldType::TEXT, ['required' => true]),
            $this->f('president_signature', 'President Signature', FieldType::SIGNATURE, ['required' => true]),
            $this->f('adviser1_name', 'Adviser 1 Name', FieldType::TEXT, ['required' => true]),
            $this->f('adviser1_contact', 'Adviser 1 Contact #', FieldType::TEXT, ['required' => true]),
            $this->f('has_second_adviser', 'Add a second adviser?', FieldType::CHECKBOX),
            $this->f('adviser2_name', 'Adviser 2 Name', FieldType::TEXT, [
                'options' => ['visible_when' => ['field' => 'has_second_adviser', 'op' => 'filled', 'value' => '']],
            ]),
        ];

        $html = '<h2>Joint Statement</h2>'
            .$this->line('Organization', 'organization_id')
            .$this->line('Category', 'category')
            .$this->line('Date', 'date')
            .$this->line('President', 'president_name')
            .$this->line('Contact', 'president_contact')
            .$this->line('Adviser', 'adviser1_name')
            .'<p><strong>Signature:</strong></p><p><span data-field="president_signature"></span></p>';

        $this->buildForm([
            'route_name' => 'joint-statement',
            'name' => 'Joint Statement',
            'description' => 'Joint statement of the organization and its adviser(s).',
            'field_kit' => null,
            'fields' => $fields,
            'pdf' => ['title' => 'Joint Statement', 'html' => $html],
        ]);
    }

    // ------------------------------------------------------------- Project Request

    private function seedProjectRequest(): void
    {
        $fields = [
            $this->heading('Project Request'),
            $this->f('organization_id', 'Organization', FieldType::ORG_SELECT, ['required' => true]),
            $this->f('is_donation', 'Type', FieldType::RADIO, ['required' => true, 'options' => ['options' => [
                ['value' => '0', 'label' => 'Project'], ['value' => '1', 'label' => 'Donation'],
            ]]]),
            $this->f('donation_amount', 'Expected Donation Amount (₱)', FieldType::NUMBER, [
                'options' => ['visible_when' => ['field' => 'is_donation', 'op' => 'equals', 'value' => '1']],
            ]),
            $this->f('in_kinds', 'Donated Materials', FieldType::TEXT_LIST),
            $this->f('projectTitle', 'Project Title', FieldType::TEXT, ['required' => true]),
            $this->f('natureOfProject', 'Nature of Project', FieldType::TEXT, ['required' => true]),
            $this->f('projectArea', 'Project Area', FieldType::TEXT, ['required' => true]),
            $this->f('letterOfIntent', 'Letter of Intent', FieldType::TEXTAREA, ['required' => true, 'options' => ['rows' => 6]]),
        ];

        $html = '<h2>Project Request</h2>'
            .$this->line('Organization', 'organization_id')
            .$this->line('Project Title', 'projectTitle')
            .$this->line('Nature of Project', 'natureOfProject')
            .$this->line('Project Area', 'projectArea')
            .'<p><strong>Donated Materials:</strong> <span data-field="in_kinds"></span></p>'
            .'<p><strong>Letter of Intent:</strong></p><p><span data-field="letterOfIntent"></span></p>';

        $this->buildForm([
            'route_name' => 'project-request',
            'name' => 'Project Request',
            'description' => 'Request approval for a project or donation drive.',
            'field_kit' => 'project_request',
            'fields' => $fields,
            'pdf' => ['title' => 'Project Request', 'html' => $html],
        ]);
    }

    // ------------------------------------------------- Organization Recognition

    private function seedOrganizationRecognition(): void
    {
        $fields = [
            $this->heading('Organization Accreditation'),
            $this->f('recognition_type', 'Accreditation / Renewal', FieldType::RADIO, ['options' => ['options' => [
                ['value' => 'recognition', 'label' => 'Accreditation'], ['value' => 'renewal', 'label' => 'Renewal'],
            ]]]),
            $this->f('organization_id', 'Organization', FieldType::ORG_SELECT, ['required' => true]),
            $this->f('name_of_president', 'President', FieldType::TEXT, ['universal_key' => 'org_president']),
            $this->f('adviser_names', 'Faculty Adviser/s', FieldType::TEXT_LIST),
            $this->f('date', 'Date of 1st Recognition', FieldType::DATE),
            $this->f('freshman', 'No. of Freshman Members', FieldType::NUMBER),
            $this->f('sophomore', 'No. of Sophomore Members', FieldType::NUMBER),
            $this->f('junior', 'No. of Junior Members', FieldType::NUMBER),
            $this->f('total', 'Total Members', FieldType::COMPUTED, ['options' => [
                'formula' => 'sum', 'args' => ['freshman', 'sophomore', 'junior'],
            ]]),
            $this->f('objectives', 'Objectives', FieldType::TEXTAREA, ['options' => ['rows' => 5]]),
            $this->f('workplan_id', 'Workplan', FieldType::WORKPLAN_SELECT),
            $this->f('president_signature', 'President Signature', FieldType::SIGNATURE),
        ];

        $html = '<h2>Application for Accreditation</h2>'
            .$this->line('Organization', 'organization_id')
            .$this->line('President', 'name_of_president')
            .'<p><strong>Faculty Adviser/s:</strong> <span data-field="adviser_names"></span></p>'
            .$this->line('Date of First Recognition', 'date')
            .'<p><strong>Members — Freshman:</strong> <span data-field="freshman"></span>'
            .' <strong>Sophomore:</strong> <span data-field="sophomore"></span>'
            .' <strong>Junior:</strong> <span data-field="junior"></span>'
            .' <strong>Total:</strong> <span data-field="total"></span></p>'
            .'<p><strong>Objectives:</strong></p><p><span data-field="objectives"></span></p>'
            .'<p><strong>Signature:</strong></p><p><span data-field="president_signature"></span></p>';

        $this->buildForm([
            // Route kept as organization-recognition for URL/back-compat; the
            // page is now bound to the Organization Accreditation system function.
            'route_name' => 'organization-recognition',
            'name' => 'Organization Accreditation',
            'description' => 'Apply for organization accreditation or renewal.',
            'field_kit' => 'organization_recognition',
            'system_function' => SystemFunction::ORG_ACCREDITATION,
            'fields' => $fields,
            'pdf' => ['title' => 'Application for Accreditation', 'html' => $html],
        ]);
    }

    // -------------------------------------------------- Accomplishment Report

    private function seedAccomplishmentReport(): void
    {
        $fields = [
            $this->heading('Accomplishment Report'),
            $this->f('organization_id', 'Organization', FieldType::ORG_SELECT, ['required' => true]),
            $this->f('schoolYear', 'School Year', FieldType::TEXT, ['required' => true]),
            $this->f('event_id', 'Event', FieldType::EVENT_SELECT, ['options' => [
                'dedupe' => true,
                'autofill_map' => ['title' => 'title', 'date' => 'date', 'people' => 'people'],
            ]]),
            $this->f('title', 'Title of Activity', FieldType::TEXT, ['required' => true]),
            $this->f('date', 'Date', FieldType::DATE, ['required' => true]),
            $this->f('people', 'Persons Involved', FieldType::TEXTAREA, ['required' => true, 'options' => ['rows' => 3]]),
            $this->f('problem', 'Problem/s Encountered', FieldType::TEXTAREA, ['options' => ['rows' => 3]]),
            $this->f('members_attended', 'Members Attended', FieldType::NUMBER),
            $this->f('summary_of_expenses', 'Summary of Expenses (₱)', FieldType::NUMBER),
            $this->f('photos', 'Documentation', FieldType::MULTI_IMAGE, ['options' => ['max_files' => 5, 'media_copy' => 'accomplishment']]),
            $this->f('name', 'Prepared by', FieldType::TEXT, ['required' => true, 'universal_key' => 'org_president']),
            $this->f('signature', 'Signature', FieldType::SIGNATURE, ['required' => true]),
            $this->f('adviser_names', 'Noted by (Adviser/s)', FieldType::TEXT_LIST),
        ];

        $html = '<h2>Accomplishment Report</h2>'
            .$this->line('Organization', 'organization_id')
            .$this->line('School Year', 'schoolYear')
            .$this->line('Title of Activity', 'title')
            .$this->line('Date', 'date')
            .'<p><strong>Persons Involved:</strong></p><p><span data-field="people"></span></p>'
            .'<p><strong>Problems Encountered:</strong></p><p><span data-field="problem"></span></p>'
            .$this->line('Members Attended', 'members_attended')
            .$this->line('Summary of Expenses', 'summary_of_expenses')
            .$this->line('Prepared by', 'name')
            .'<p><strong>Adviser/s:</strong> <span data-field="adviser_names"></span></p>'
            .'<p><strong>Signature:</strong></p><p><span data-field="signature"></span></p>';

        $this->buildForm([
            'route_name' => 'accomplishment-report',
            'name' => 'Accomplishment Report',
            'description' => 'Report a completed activity or event.',
            'field_kit' => 'accomplishment_report',
            'fields' => $fields,
            'pdf' => ['title' => 'Accomplishment Report', 'html' => $html],
        ]);
    }

    // ------------------------------------------------------- Financial Report

    private function seedFinancialReport(): void
    {
        $fields = [
            $this->heading('Financial Report'),
            $this->f('organization_id', 'Organization', FieldType::ORG_SELECT, ['required' => true]),
            $this->f('date', 'School Year', FieldType::TEXT, ['required' => true]),
            $this->f('fund_table', 'Fund Table', FieldType::TABLE_INPUT, ['required' => true, 'options' => [
                'columns' => [
                    ['key' => 'fundSource', 'label' => 'Fund Source', 'type' => 'text', 'required' => true],
                    ['key' => 'amount', 'label' => 'Fund Amount (₱)', 'type' => 'number', 'required' => true],
                ],
            ]]),
            $this->f('totalFunds', 'Total Funds', FieldType::COMPUTED, ['options' => [
                'formula' => 'sum', 'args' => ['fund_table.amount'],
            ]]),
            $this->f('expenses_summary', 'Expenses Summary', FieldType::TABLE_INPUT, ['required' => true, 'options' => [
                'columns' => [
                    ['key' => 'activity_and_date', 'label' => 'Activity Title and Date', 'type' => 'event-select', 'scope' => 'current_semester', 'required' => true],
                    ['key' => 'item_title', 'label' => 'Item', 'type' => 'text', 'required' => true],
                    ['key' => 'amount_per_unit', 'label' => 'Amount per Unit (₱)', 'type' => 'number', 'required' => true],
                    ['key' => 'quantity', 'label' => 'Quantity', 'type' => 'number', 'required' => true],
                ],
                'row_total' => ['key' => 'priceTotal', 'label' => 'Total Price', 'multiply' => ['amount_per_unit', 'quantity']],
            ]]),
            $this->f('totalExpenses', 'Total Expenses', FieldType::COMPUTED, ['options' => [
                'formula' => 'sum', 'args' => ['expenses_summary.priceTotal'],
            ]]),
            $this->f('sum', 'Sum (Total Funds − Total Expenses)', FieldType::COMPUTED, ['options' => [
                'formula' => 'difference', 'args' => ['totalFunds', 'totalExpenses'],
            ]]),
            $this->f('name_of_treasurer', 'Treasurer', FieldType::TEXT, ['required' => true]),
            $this->f('signature1', 'Treasurer Signature', FieldType::SIGNATURE, ['required' => true]),
            $this->f('name_of_the_president', 'President', FieldType::TEXT, ['required' => true, 'universal_key' => 'org_president']),
            $this->f('signature3', 'President Signature', FieldType::SIGNATURE, ['required' => true]),
        ];

        $html = '<h2>Financial Report</h2>'
            .$this->line('Organization', 'organization_id')
            .$this->line('School Year', 'date')
            .'<h3>Fund Table</h3>'
            .'<table><thead><tr><th>Fund Source</th><th>Fund Amount</th></tr></thead>'
            .'<tbody data-field-rows="fund_table"><tr><td data-col="fundSource"></td><td data-col="amount"></td></tr></tbody></table>'
            .'<p><strong>Total Funds:</strong> <span data-field="totalFunds"></span></p>'
            .'<h3>Expenses Summary</h3>'
            .'<table><thead><tr><th>Activity Title and Date</th><th>Item</th><th>Amount per Unit</th><th>Quantity</th><th>Total Price</th></tr></thead>'
            .'<tbody data-field-rows="expenses_summary"><tr><td data-col="activity_and_date"></td><td data-col="item_title"></td><td data-col="amount_per_unit"></td><td data-col="quantity"></td><td data-col="priceTotal"></td></tr></tbody></table>'
            .'<p><strong>Total Expenses:</strong> <span data-field="totalExpenses"></span></p>'
            .'<p><strong>Sum:</strong> <span data-field="sum"></span></p>'
            .$this->line('Prepared by (Treasurer)', 'name_of_treasurer')
            .'<p><span data-field="signature1"></span></p>';

        $this->buildForm([
            'route_name' => 'financial-report',
            'name' => 'Financial Report',
            'description' => 'Report the organization\'s funds and expenses.',
            'field_kit' => 'financial_report',
            'fields' => $fields,
            'pdf' => ['title' => 'Financial Report', 'html' => $html],
        ]);
    }
}
