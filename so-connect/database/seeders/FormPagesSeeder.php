<?php

namespace Database\Seeders;

use App\Forms\FieldType;
use App\Forms\SystemFunction;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Services\RequestTypeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
        $this->seedNewEvent();
        $this->seedJointStatement();
        $this->seedProjectRequest();
        $this->seedOrganizationRecognition();
        $this->seedAccomplishmentReport();
        $this->seedFinancialReport();
    }

    /**
     * Create (or refresh) a form, its fields, one-field-per-row layout, printed
     * template and — for plain forms — its request type.
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
                'layout' => ['rows' => array_map(
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

    // ---------------------------------------------------------------- New Event

    /**
     * The New Event builder form (bound to the new_event system function) that
     * replaced the calendar's hardcoded "Event Plan" drawer and the standalone
     * /events create page. Its submissions run through {@see NewEventHandler}:
     * an approved parent plan + a pending child plan tied to a document request
     * the admin decides in the Activity Requests queue.
     *
     * The field keys mirror NewEventHandler::REQUIRED_KEYS plus the two
     * optional keys it reads (purpose_of_activity, resources_needed). While no
     * form is bound to new_event, the calendar's "Create Event" action is
     * disabled (see calendar-area.blade.php / SystemFunction::form()).
     */
    private function seedNewEvent(): void
    {
        $fields = [
            $this->heading('Event Details'),
            $this->f('organization_id', 'Organization', FieldType::ORG_SELECT, ['required' => true]),
            $this->f('title', 'Activity / Title', FieldType::TEXT),
            $this->f('target_date', 'Target Date', FieldType::DATE),
            $this->f('event_location', 'Event Location', FieldType::TEXT),
            $this->f('event_start_time', 'Start', FieldType::TIME),
            $this->f('event_end_time', 'End', FieldType::TIME),
            $this->heading('Activity Details'),
            $this->f('purpose_of_activity', 'Purpose of Activity', FieldType::TEXTAREA, ['options' => ['rows' => 4]]),
            $this->f('resources_needed', 'Resources Needed', FieldType::TEXTAREA, ['options' => ['rows' => 3]]),
            $this->heading('Waiver'),
            $this->f('waiver', 'Signed Waiver', FieldType::WAIVER_SCAN, [
                'placeholder' => 'Scan or upload the signed activity waiver.',
            ]),
        ];

        $html = '<h2>Event Request</h2>'
            .$this->line('Organization', 'organization_id')
            .$this->line('Activity / Title', 'title')
            .$this->line('Target Date', 'target_date')
            .$this->line('Location', 'event_location')
            .$this->line('Start', 'event_start_time')
            .$this->line('End', 'event_end_time')
            .'<p><strong>Purpose of Activity:</strong></p><p><span data-field="purpose_of_activity"></span></p>'
            .'<p><strong>Resources Needed:</strong></p><p><span data-field="resources_needed"></span></p>';

        $this->buildForm([
            'route_name' => 'new-event',
            'name' => 'New Event',
            'description' => 'Request a new event or activity. An admin approval schedules it on the calendar.',
            'system_function' => SystemFunction::NEW_EVENT,
            'fields' => $fields,
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
            $this->f('fund_sources', 'Source of Funds', FieldType::TABLE_INPUT, ['required' => true, 'options' => [
                'columns' => [
                    ['key' => 'source', 'label' => 'Source of Funds', 'type' => 'text', 'required' => true],
                    ['key' => 'amount', 'label' => 'Amount (₱)', 'type' => 'number', 'required' => true],
                ],
            ]]),
            $this->f('total_funds', 'Total Funds', FieldType::COMPUTED, ['options' => [
                'formula' => 'sum', 'args' => ['fund_sources.amount'],
            ]]),
            $this->f('expenses', 'Statement of Expenses', FieldType::TABLE_INPUT, ['options' => [
                'columns' => [
                    ['key' => 'activity', 'label' => 'Activity', 'type' => 'event-select', 'required' => false],
                    ['key' => 'ex_date', 'label' => 'Date', 'type' => 'date', 'required' => false],
                    ['key' => 'item', 'label' => 'Item', 'type' => 'text', 'required' => false],
                    ['key' => 'price', 'label' => 'Price/Unit', 'type' => 'number', 'required' => false],
                    ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'required' => false],
                ],
                'row_total' => ['key' => 'line_total', 'label' => 'Total', 'multiply' => ['price', 'qty']],
            ]]),
            $this->f('total_expenses', 'Total Expenses', FieldType::COMPUTED, ['options' => [
                'formula' => 'sum', 'args' => ['expenses.line_total'],
            ]]),
            $this->f('cash_on_hand', 'Cash on Hand', FieldType::COMPUTED, ['options' => [
                'formula' => 'difference', 'args' => ['total_funds', 'total_expenses'],
            ]]),
            $this->f('name_of_treasurer', 'Treasurer', FieldType::TEXT, ['required' => true]),
            $this->f('signature1', 'Treasurer Signature', FieldType::SIGNATURE, ['required' => true]),
            $this->f('name_of_the_president', 'President', FieldType::TEXT, ['required' => true, 'universal_key' => 'org_president']),
            $this->f('signature3', 'President Signature', FieldType::SIGNATURE, ['required' => true]),
        ];

        $html = '<h2>Financial Report</h2>'
            .$this->line('Organization', 'organization_id')
            .$this->line('School Year', 'date')
            .'<h3>Source of Funds</h3>'
            .'<table><thead><tr><th>Source</th><th>Amount</th></tr></thead>'
            .'<tbody data-field-rows="fund_sources"><tr><td data-col="source"></td><td data-col="amount"></td></tr></tbody></table>'
            .'<p><strong>Total Funds:</strong> <span data-field="total_funds"></span></p>'
            .'<h3>Statement of Expenses</h3>'
            .'<table><thead><tr><th>Item</th><th>Price</th><th>Qty</th><th>Total</th></tr></thead>'
            .'<tbody data-field-rows="expenses"><tr><td data-col="item"></td><td data-col="price"></td><td data-col="qty"></td><td data-col="line_total"></td></tr></tbody></table>'
            .'<p><strong>Total Expenses:</strong> <span data-field="total_expenses"></span></p>'
            .'<p><strong>Cash on Hand:</strong> <span data-field="cash_on_hand"></span></p>'
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
