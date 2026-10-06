<?php

use App\Forms\ActivityTableData;
use App\Forms\Handlers\NewEventHandler;
use App\Models\Approval;
use App\Models\Event;
use App\Models\EventPlan;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Models\Profile;
use App\Models\Semester;
use App\Models\User;
use App\Models\Workplan;
use App\Services\DocumentGenerationService;
use App\Services\WorkplanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** A type-3 officer bound to a freshly created organization. */
function atOfficer(): array
{
    $profile = Profile::query()->create([
        'first_name' => 'Wilma', 'last_name' => 'Plan', 'middle_name' => 'T', 'occupation' => 'Officer',
    ]);
    $user = User::query()->create([
        'user_email' => 'officer'.\Illuminate\Support\Str::random(6).'@example.com',
        'user_password' => 'password', 'user_type' => 3, 'profile' => $profile->getKey(),
    ]);
    $detailId = DB::table('organization_details')->insertGetId([
        'name' => 'Robotics Society', 'detail_text' => 'org', 'initials' => 'RS',
    ]);
    $orgId = DB::table('organizations')->insertGetId(['organization_type' => 1, 'detail' => $detailId]);
    DB::table('organization_officers')->insert([
        'role' => 'officer', 'organization' => $orgId, 'user' => (int) $user->getKey(),
        'member_since' => now(), 'registered_at' => now(), 'reassigned_at' => now(),
    ]);

    return [$user, (int) $orgId];
}

/** A semester whose vacation window contains today, so it is the active one. */
function atActiveSemester(): Semester
{
    return Semester::query()->create([
        'name' => 'AY Test', 'semester_number' => 1,
        'starts_at' => Carbon::today()->addDays(5), 'vacation_days' => 60,
    ]);
}

/** The bound New Events form, with the fields whose values an Activity Table can print. */
function atNewEventForm(): Form
{
    $form = Form::query()->create([
        'name' => 'New Event', 'route_name' => 'new-event-'.\Illuminate\Support\Str::random(6),
        'system_function' => 'new_event', 'is_active' => true,
        'pdf_template' => [
            'html' => '<p><span data-field="title">Event title</span></p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
        ],
    ]);
    foreach ([
        ['field_key' => 'organization_id', 'field_type' => 'text', 'field_label' => 'Organization'],
        ['field_key' => 'title', 'field_type' => 'text', 'field_label' => 'Activity Title'],
        ['field_key' => 'target_date', 'field_type' => 'date', 'field_label' => 'Target Date'],
        ['field_key' => 'event_location', 'field_type' => 'text', 'field_label' => 'Location'],
        ['field_key' => 'event_start_time', 'field_type' => 'time', 'field_label' => 'Start'],
        ['field_key' => 'expected_participants', 'field_type' => 'text', 'field_label' => 'Expected Participants'],
    ] as $i => $f) {
        FormDescription::query()->create($f + ['form_id' => $form->id, 'field_order' => $i + 1]);
    }

    return $form;
}

/**
 * A workplan form (kit-scoped via field_kit, no system function, so submit runs
 * the plain-form flow) carrying one Activity Table field with the given columns.
 */
function atWorkplanForm(array $columns): Form
{
    $form = Form::query()->create([
        'name' => 'Workplan', 'route_name' => 'workplan-'.\Illuminate\Support\Str::random(6),
        'field_kit' => 'new_workplan', 'is_active' => true, 'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => [
            'html' => '<p><span data-field="wp_activities">Activities</span></p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
        ],
    ]);
    FormDescription::query()->create([
        'form_id' => $form->id, 'field_key' => 'wp_activities', 'field_label' => 'Approved Activities',
        'field_type' => 'activity-table', 'field_order' => 1,
        'field_options' => ['columns' => $columns],
    ]);

    return $form;
}

/**
 * Seed one approved parent activity for $orgId with a child plan + originating
 * New Events submission, so every canonical + custom column resolves.
 *
 * @return string  the parent's target-date, as stored
 */
function atApprovedActivity(int $orgId, int $userId, Form $newEventForm, string $title = 'Acquaintance Party'): string
{
    $targetDate = Carbon::today()->addDays(10)->toDateString();

    $parent = EventPlan::query()->create([
        'organization_id' => $orgId, 'created_by' => $userId, 'title' => $title,
        'target_date' => $targetDate, 'purpose_of_activity' => 'Welcome the new members',
        'resources_needed' => 'Chairs and sound system', 'status' => 'approved', 'parent_plan_id' => null,
    ]);

    $submission = FormSubmission::query()->create([
        'form_id' => (int) $newEventForm->id, 'organization_id' => $orgId, 'submitted_by' => $userId,
        'payload' => [
            'title' => $title, 'target_date' => $targetDate, 'event_location' => 'AVR',
            'event_start_time' => '13:00', 'event_end_time' => '15:00',
            'expected_participants' => '120 students',
        ],
        'submitted_at' => now(),
    ]);

    $request = app(DocumentGenerationService::class)->createDocumentGenerationRequest(
        $orgId, (int) $submission->getKey(), (int) $newEventForm->id, $userId,
    );

    EventPlan::query()->create([
        'organization_id' => $orgId, 'created_by' => $userId, 'title' => $title, 'target_date' => $targetDate,
        'event_location' => 'AVR',
        'event_start_time' => $targetDate.' 13:00:00', 'event_end_time' => $targetDate.' 15:00:00',
        'status' => 'approved', 'parent_plan_id' => (int) $parent->getKey(),
        'request_id' => (int) $request->getKey(),
    ]);

    return $targetDate;
}

/** The workplan for $orgId in the active semester, so the resolver finds it. */
function atWorkplan(int $orgId, Semester $semester): Workplan
{
    return Workplan::query()->create([
        'organization_id' => $orgId, 'semester_id' => (int) $semester->getKey(), 'status' => 'active',
    ]);
}

it('creates calendar events automatically for approved activities when a workplan is accepted', function () {
    [$officer, $orgId] = atOfficer();
    $semester = atActiveSemester();
    $workplan = atWorkplan($orgId, $semester);
    $newEventForm = atNewEventForm();
    $targetDate = atApprovedActivity($orgId, (int) $officer->getKey(), $newEventForm);

    $approvedPlan = EventPlan::query()
        ->where('organization_id', $orgId)
        ->whereNull('parent_plan_id')
        ->where('status', 'approved')
        ->sole();

    $approvedPlan->update([
        'event_location' => 'Main Hall',
        'event_start_time' => $targetDate.' 09:00:00',
        'event_end_time' => $targetDate.' 11:00:00',
    ]);

    expect(Event::query()->count())->toBe(0);

    $created = app(WorkplanService::class)->convertApprovedPlansToCalendarEvents($workplan);

    expect($created)->toBe(1)
        ->and($approvedPlan->fresh()->event_id)->not->toBeNull()
        ->and(Event::query()->where('organization', $orgId)->count())->toBe(1)
        ->and(Event::query()->latest('event_id')->first()->detailOfEvent->name)->toBe($approvedPlan->title);
});

it('snapshots the approved activities on submit, ignoring client input', function () {
    [$officer, $orgId] = atOfficer();
    $semester = atActiveSemester();
    atWorkplan($orgId, $semester);
    $newEventForm = atNewEventForm();
    $targetDate = atApprovedActivity($orgId, (int) $officer->getKey(), $newEventForm);

    $form = atWorkplanForm([
        ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
        ['key' => 'target_date', 'label' => 'Target Date', 'type' => 'date'],
        ['key' => 'event_location', 'label' => 'Location', 'type' => 'text'],
        ['key' => 'event_start_time', 'label' => 'Start', 'type' => 'time'],
        ['key' => 'expected_participants', 'label' => 'Participants', 'type' => 'text'],
    ]);

    // Client input under the field key is ignored — the server snapshots.
    $this->actingAs($officer)->post(route('forms.render.submit', $form->route_name), [
        'wp_activities' => 'garbage client value',
    ])->assertSessionHasNoErrors();

    $submission = FormSubmission::query()->where('form_id', $form->id)->latest('form_submission_id')->first();

    expect($submission->payload['wp_activities'])->toEqual([[
        'title' => 'Acquaintance Party',
        'target_date' => Carbon::parse($targetDate)->format('M d, Y'),
        'event_location' => 'AVR',
        'event_start_time' => '1:00 PM',
        'expected_participants' => '120 students',
    ]]);
});

it('returns an empty snapshot when the org has no approved activities', function () {
    [$officer, $orgId] = atOfficer();
    $semester = atActiveSemester();
    atWorkplan($orgId, $semester);
    atNewEventForm();

    $form = atWorkplanForm([['key' => 'title', 'label' => 'Title', 'type' => 'text']]);

    $this->actingAs($officer)->post(route('forms.render.submit', $form->route_name), [])
        ->assertSessionHasNoErrors();

    $submission = FormSubmission::query()->where('form_id', $form->id)->latest('form_submission_id')->first();
    expect($submission->payload['wp_activities'])->toEqual([]);
});

it('blanks a stale column and fills canonical columns for a legacy plan', function () {
    [$officer, $orgId] = atOfficer();
    $semester = atActiveSemester();
    atWorkplan($orgId, $semester);
    atNewEventForm();

    // A legacy parent plan with no child / request / originating submission.
    $targetDate = Carbon::today()->addDays(12)->toDateString();
    EventPlan::query()->create([
        'organization_id' => $orgId, 'created_by' => (int) $officer->getKey(), 'title' => 'Legacy Cleanup',
        'target_date' => $targetDate, 'resources_needed' => 'Brooms', 'status' => 'approved', 'parent_plan_id' => null,
    ]);

    $form = atWorkplanForm([
        ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
        ['key' => 'event_location', 'label' => 'Location', 'type' => 'text'],
        ['key' => 'expected_participants', 'label' => 'Participants', 'type' => 'text'],
        // A column whose source field is gone from the New Events form.
        ['key' => 'ghost_column', 'label' => 'Ghost', 'type' => 'text'],
    ]);

    $this->actingAs($officer)->post(route('forms.render.submit', $form->route_name), [])
        ->assertSessionHasNoErrors();

    $rows = FormSubmission::query()->where('form_id', $form->id)->latest('form_submission_id')->first()->payload['wp_activities'];

    expect($rows)->toEqual([[
        'title' => 'Legacy Cleanup',
        'event_location' => '',        // no child plan
        'expected_participants' => '', // no originating submission
        'ghost_column' => '',          // stale column
    ]]);
});

it('renders nothing for the activity-table field on the web form', function () {
    [$officer, $orgId] = atOfficer();
    atActiveSemester();
    atNewEventForm();

    $form = atWorkplanForm([['key' => 'title', 'label' => 'Title', 'type' => 'text']]);

    $html = $this->actingAs($officer)->get(route('forms.render', $form->route_name))->assertOk()->getContent();

    // No label, no input, no wrapper for the field.
    expect($html)->not->toContain('Approved Activities')
        ->not->toContain('name="wp_activities"');
});

it('renders the snapshot as a table on the admin request page', function () {
    [$officer, $orgId] = atOfficer();
    $semester = atActiveSemester();
    atWorkplan($orgId, $semester);
    $newEventForm = atNewEventForm();
    atApprovedActivity($orgId, (int) $officer->getKey(), $newEventForm);

    $form = atWorkplanForm([
        ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
        ['key' => 'event_location', 'label' => 'Location', 'type' => 'text'],
    ]);

    $this->actingAs($officer)->post(route('forms.render.submit', $form->route_name), [])->assertSessionHasNoErrors();

    $request = \App\Models\Request::query()->where('form_id', $form->id)->latest('request_id')->firstOrFail();

    $admin = recordsUser(2);

    $html = $this->actingAs($admin)
        ->get(route('admin.form-requests.show', [$form, $request->request_id]))
        ->assertOk()->getContent();

    // The column headings and a resolved cell print in the review table.
    expect($html)->toContain('Title')->toContain('Location')->toContain('Acquaintance Party')->toContain('AVR');
});

it('resolves the same set directly through ActivityTableData', function () {
    [$officer, $orgId] = atOfficer();
    $semester = atActiveSemester();
    atWorkplan($orgId, $semester);
    $newEventForm = atNewEventForm();
    $targetDate = atApprovedActivity($orgId, (int) $officer->getKey(), $newEventForm);

    $result = ActivityTableData::forField(
        ['columns' => [
            ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['key' => 'event_end_time', 'label' => 'End', 'type' => 'time'],
        ]],
        [$orgId],
    );

    expect($result['rows'])->toEqual([[
        'title' => 'Acquaintance Party',
        'event_end_time' => '3:00 PM',
    ]]);
});

it('recognizes only admin-approved workplans for the event date', function () {
    [$officer, $orgId] = atOfficer();
    $semester = atActiveSemester();
    $workplan = atWorkplan($orgId, $semester);
    $workplan->update(['status' => 'finalized']);

    $form = Form::query()->create([
        'name' => 'Workplan',
        'route_name' => 'workplan-'.\Illuminate\Support\Str::random(6),
        'system_function' => 'new_workplan',
        'is_active' => true,
    ]);
    $submission = FormSubmission::query()->create([
        'form_id' => (int) $form->getKey(),
        'organization_id' => $orgId,
        'submitted_by' => (int) $officer->getKey(),
        'submitted_at' => now(),
        'payload' => ['semester_id' => (int) $semester->getKey()],
    ]);
    $request = app(DocumentGenerationService::class)->createDocumentGenerationRequest(
        $orgId,
        (int) $submission->getKey(),
        (int) $form->getKey(),
        (int) $officer->getKey(),
    );
    Approval::query()->create([
        'request' => (int) $request->getKey(),
        'approved_at' => now(),
        'is_rejected' => false,
    ]);

    $service = app(WorkplanService::class);
    expect($service->hasApprovedWorkplanForDate($orgId, $semester->starts_at->toDateString()))->toBeTrue()
        ->and($service->hasApprovedWorkplanForDate($orgId, $semester->starts_at->copy()->subDay()->toDateString()))->toBeFalse();

    $newEventForm = atNewEventForm();
    $newEventRequest = new HttpRequest();
    $newEventRequest->setUserResolver(fn () => $officer);
    $approvedWorkplanSubmission = FormSubmission::query()->create([
        'form_id' => (int) $newEventForm->getKey(),
        'organization_id' => $orgId,
        'submitted_by' => (int) $officer->getKey(),
        'submitted_at' => now(),
        'payload' => [],
    ]);

    (new NewEventHandler())->handle($newEventForm, $approvedWorkplanSubmission, [
        'organization_id' => $orgId,
        'title' => 'Approved Workplan Event',
        'target_date' => $semester->starts_at->toDateString(),
    ], $newEventRequest);

    expect(EventPlan::query()->where('title', 'Approved Workplan Event')->sole()->parent_plan_id)->toBeNull();

    Approval::query()->where('request', $request->getKey())->update(['is_rejected' => true]);

    expect($service->hasApprovedWorkplanForDate($orgId, $semester->starts_at->toDateString()))->toBeFalse();

    $unapprovedWorkplanSubmission = FormSubmission::query()->create([
        'form_id' => (int) $newEventForm->getKey(),
        'organization_id' => $orgId,
        'submitted_by' => (int) $officer->getKey(),
        'submitted_at' => now(),
        'payload' => [],
    ]);

    (new NewEventHandler())->handle($newEventForm, $unapprovedWorkplanSubmission, [
        'organization_id' => $orgId,
        'title' => 'Unapproved Workplan Event',
        'target_date' => $semester->starts_at->toDateString(),
    ], $newEventRequest);

    $unapprovedWorkplanPlans = EventPlan::query()
        ->where('title', 'Unapproved Workplan Event')
        ->get();
    $parentPlan = $unapprovedWorkplanPlans->whereNull('parent_plan_id')->sole();
    $requestPlan = $unapprovedWorkplanPlans->whereNotNull('parent_plan_id')->sole();

    expect($unapprovedWorkplanPlans)->toHaveCount(2)
        ->and($requestPlan->parent_plan_id)->toBe((int) $parentPlan->getKey());
});

it('creates an admin activity request when an officer submits on an approved workplan date', function () {
    [$officer, $orgId] = atOfficer();
    $semester = atActiveSemester();
    $workplan = atWorkplan($orgId, $semester);
    $workplan->update(['status' => 'finalized']);

    $workplanForm = Form::query()->create([
        'name' => 'Workplan',
        'route_name' => 'workplan-'.\Illuminate\Support\Str::random(6),
        'system_function' => 'new_workplan',
        'is_active' => true,
    ]);
    $workplanSubmission = FormSubmission::query()->create([
        'form_id' => (int) $workplanForm->getKey(),
        'organization_id' => $orgId,
        'submitted_by' => (int) $officer->getKey(),
        'submitted_at' => now(),
        'payload' => ['semester_id' => (int) $semester->getKey()],
    ]);
    $workplanRequest = app(DocumentGenerationService::class)->createDocumentGenerationRequest(
        $orgId,
        (int) $workplanSubmission->getKey(),
        (int) $workplanForm->getKey(),
        (int) $officer->getKey(),
    );
    Approval::query()->create([
        'request' => (int) $workplanRequest->getKey(),
        'approved_at' => now(),
        'is_rejected' => false,
    ]);

    $eventForm = atNewEventForm();
    $this->actingAs($officer)
        ->post(route('forms.render.submit', $eventForm->route_name), [
            'organization_id' => (string) $orgId,
            'title' => 'Approved Workplan Activity Request',
            'target_date' => $semester->starts_at->toDateString(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('forms.render', $eventForm->route_name));

    $activityRequest = \App\Models\Request::query()
        ->where('form_id', (int) $eventForm->getKey())
        ->sole();
    $submission = FormSubmission::query()
        ->findOrFail((int) $activityRequest->payload['submission_id']);
    $eventPlan = EventPlan::query()
        ->where('request_id', (int) $activityRequest->getKey())
        ->sole();

    expect($activityRequest->organization_id)->toBe($orgId)
        ->and($activityRequest->requested_by)->toBe((int) $officer->getKey())
        ->and((int) $activityRequest->action_type)->toBe(\App\Helpers\FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION)
        ->and($submission->organization_id)->toBe($orgId)
        ->and($activityRequest->payload['event_plan_id'])->toBe((int) $eventPlan->getKey())
        ->and($activityRequest->payload['parent_plan_id'])->toBeNull()
        ->and($eventPlan->parent_plan_id)->toBeNull();

    $this->actingAs(recordsUser(2))
        ->get(route('admin.activity-requests.index'))
        ->assertOk()
        ->assertSee('Approved Workplan Activity Request');
});
