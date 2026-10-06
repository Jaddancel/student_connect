<?php

use App\Forms\AfterEventTokenData;
use App\Forms\Handlers\AfterEventReportHandler;
use App\Forms\SystemFunction;
use App\Helpers\NotificationBellHelper;
use App\Mail\AfterEventReportDueMail;
use App\Models\AppSetting;
use App\Models\Event;
use App\Models\Event\EventDetail;
use App\Models\EventPlan;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Models\Request as ActionRequest;
use App\Models\Semester;
use App\Models\Template;
use App\Models\User;
use App\Services\AfterEventReportService;
use App\Services\DocxTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function aeOfficial(int $organizationId, string $role = 'officer'): User
{
    $user = recordsUser(3);
    DB::table('organization_officers')->insert([
        'role' => $role,
        'organization' => $organizationId,
        'user' => (int) $user->getKey(),
        'yearterm' => null,
        'member_since' => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    return $user;
}

function aeEvent(int $organizationId, string $name, Carbon $endedAt): Event
{
    $detail = EventDetail::query()->create([
        'name' => $name,
        'location' => 'Gym',
        'desc_text' => '',
        'start_time' => $endedAt->copy()->subHours(2),
        'end_time' => $endedAt,
    ]);

    return Event::query()->create([
        'organization' => $organizationId,
        'creator' => null,
        'event_detail' => (int) $detail->getKey(),
    ]);
}

function aeForm(): Form
{
    $form = Form::query()->create([
        'name' => 'After Event Report',
        'route_name' => 'after-event-report',
        'system_function' => SystemFunction::AFTER_EVENT_REPORT,
        'is_active' => true,
        'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => ['html' => '<p>After event</p>'],
    ]);
    FormDescription::query()->create([
        'form_id' => $form->id,
        'field_key' => 'summary',
        'field_label' => 'Summary',
        'field_type' => 'text',
        'is_required' => true,
        'field_order' => 1,
    ]);
    Storage::disk('public')->put('form-templates/ae.docx', 'docx');
    Template::query()->create([
        'form_id' => $form->id,
        'template_name' => 'After Event',
        'docx_path' => 'form-templates/ae.docx',
        'version' => 1,
        'is_active' => true,
    ]);

    return $form;
}

/** The New Event form + a submission/request/plan chain that created $event. */
function aeNewEventSource(Event $event, User $submitter, string $title): FormSubmission
{
    $form = Form::query()->firstOrCreate(['route_name' => 'new-event'], [
        'name' => 'New Event',
        'system_function' => SystemFunction::NEW_EVENT,
        'is_active' => true,
        'is_published' => true,
    ]);
    if ($form->wasRecentlyCreated) {
        foreach (['title' => 'text', 'event_location' => 'text'] as $key => $type) {
            FormDescription::query()->create([
                'form_id' => $form->id, 'field_key' => $key, 'field_label' => ucfirst($key),
                'field_type' => $type, 'is_required' => false, 'field_order' => 1,
            ]);
        }
    }
    $submission = FormSubmission::query()->create([
        'form_id' => $form->id,
        'organization_id' => $event->organization,
        'submitted_by' => $submitter->getKey(),
        'payload' => ['title' => $title, 'event_location' => 'Main Hall'],
        'submitted_at' => now(),
    ]);
    $request = ActionRequest::query()->create([
        'action' => 'test',
        'action_type' => 3,
        'requested_at' => now(),
        'user' => $submitter->getKey(),
        'payload' => ['submission_id' => (int) $submission->getKey()],
    ]);
    EventPlan::query()->create([
        'organization_id' => $event->organization,
        'created_by' => $submitter->getKey(),
        'title' => $title,
        'target_date' => now()->toDateString(),
        'status' => 'approved',
        'event_id' => $event->getKey(),
        'request_id' => $request->getKey(),
    ]);

    return $submission;
}

function aeMockDocx(array &$captured): void
{
    test()->mock(DocxTemplateService::class, function ($mock) use (&$captured) {
        $mock->shouldReceive('populate')->andReturnUsing(function ($template, $values) use (&$captured) {
            $captured = $values;
            $dir = storage_path('app/tmp/ae-'.bin2hex(random_bytes(6)));
            File::ensureDirectoryExists($dir);
            File::put($dir.'/populated.docx', 'docx');

            return $dir.'/populated.docx';
        });
        $mock->shouldReceive('toPdf')->andReturnUsing(function ($path) {
            File::put(dirname($path).'/populated.pdf', '%PDF-1.4');

            return dirname($path).'/populated.pdf';
        });
    });
}

beforeEach(function () {
    Storage::fake('public');
    Semester::create(['name' => 'Current Sem', 'semester_number' => 1, 'starts_at' => Carbon::today()->subDays(60), 'vacation_days' => 0]);
    AppSetting::put(AfterEventReportService::SETTING_ELAPSED_DAYS, 3);
});

it('lists concluded events past the elapsed period within the running semester', function () {
    aeForm();
    $org = recordsOrganization('After Org');
    $officer = aeOfficial((int) $org->getKey());

    aeEvent((int) $org->getKey(), 'Due Seminar', now()->subDays(5));
    aeEvent((int) $org->getKey(), 'Too Recent Fair', now()->subDay());
    aeEvent((int) $org->getKey(), 'Last Semester Gala', now()->subDays(90));
    aeEvent((int) recordsOrganization('Other Org')->getKey(), 'Other Org Event', now()->subDays(5));

    $this->actingAs($officer)->get(route('after-event-reports.index'))
        ->assertOk()
        ->assertSee('Due Seminar')
        ->assertSee('Not filed')
        ->assertSee('File report')
        ->assertDontSee('Too Recent Fair')
        ->assertDontSee('Last Semester Gala')
        ->assertDontSee('Other Org Event');
});

it('requires an eligible event to open the bound form', function () {
    $form = aeForm();
    $org = recordsOrganization('After Org');
    $officer = aeOfficial((int) $org->getKey());
    $event = aeEvent((int) $org->getKey(), 'Due Seminar', now()->subDays(5));

    $this->actingAs($officer)->get(route('forms.render', $form->route_name))
        ->assertRedirect(route('after-event-reports.index'));

    $this->actingAs($officer)->get(route('forms.render', ['routeName' => $form->route_name, 'event' => $event->getKey()]))
        ->assertOk()
        ->assertSee('After event report for')
        ->assertSee('Due Seminar')
        ->assertSee('name="'.AfterEventReportHandler::EVENT_INPUT.'"', false);

    $outsider = aeOfficial((int) recordsOrganization('Other Org')->getKey());
    $this->actingAs($outsider)->get(route('forms.render', ['routeName' => $form->route_name, 'event' => $event->getKey()]))
        ->assertRedirect(route('after-event-reports.index'));
});

it('generates the report immediately with New Event tokens and marks the event filed', function () {
    Mail::fake();
    $form = aeForm();
    $org = recordsOrganization('After Org');
    $officer = aeOfficial((int) $org->getKey());
    $event = aeEvent((int) $org->getKey(), 'Due Seminar', now()->subDays(5));
    aeNewEventSource($event, $officer, 'Leadership Seminar');

    $captured = [];
    aeMockDocx($captured);

    $this->actingAs($officer)->post(route('forms.render.submit', $form->route_name), [
        'summary' => 'It went well.',
        AfterEventReportHandler::EVENT_INPUT => $event->getKey(),
    ])->assertRedirect(route('after-event-reports.index'));

    $submission = FormSubmission::query()->where('form_id', $form->id)->sole();
    expect((int) $submission->event_id)->toBe((int) $event->getKey())
        ->and((int) $submission->organization_id)->toBe((int) $org->getKey())
        ->and($submission->generatedDocuments()->where('status', 'generated')->count())->toBe(1)
        ->and($captured['summary'])->toBe('It went well.')
        ->and($captured['event.title'])->toBe('Leadership Seminar')
        ->and($captured['event.event_location'])->toBe('Main Hall')
        ->and($captured['eventinfo.name'])->toBe('Due Seminar')
        ->and($captured['eventinfo.organization'])->toBe('After Org');

    expect(DB::table('requests')->where('form_id', $form->id)->count())->toBe(0);

    $this->actingAs($officer)->get(route('after-event-reports.index'))
        ->assertSee('Filed')
        ->assertSee('View document');

    // A second filing for the same event is refused with the already-filed dialog.
    $this->actingAs($officer)->post(route('forms.render.submit', $form->route_name), [
        'summary' => 'Again',
        AfterEventReportHandler::EVENT_INPUT => $event->getKey(),
    ])->assertRedirect(route('after-event-reports.index'))
        ->assertSessionHasErrors(AfterEventReportService::ERROR_ALREADY_FILED);
    expect(FormSubmission::query()->where('form_id', $form->id)->count())->toBe(1);
});

it('refuses filing for an event from an elapsed semester', function () {
    $form = aeForm();
    $org = recordsOrganization('After Org');
    $officer = aeOfficial((int) $org->getKey());
    $event = aeEvent((int) $org->getKey(), 'Last Semester Gala', now()->subDays(90));

    $this->actingAs($officer)->post(route('forms.render.submit', $form->route_name), [
        'summary' => 'Late',
        AfterEventReportHandler::EVENT_INPUT => $event->getKey(),
    ])->assertSessionHasErrors('form');

    expect(FormSubmission::query()->where('form_id', $form->id)->count())->toBe(0);
});

it('warns officials through the bell until the report is filed', function () {
    aeForm();
    $org = recordsOrganization('After Org');
    $officer = aeOfficial((int) $org->getKey());
    aeEvent((int) $org->getKey(), 'Due Seminar', now()->subDays(5));

    $items = NotificationBellHelper::afterEventReportNotifications($officer);

    expect($items)->toHaveCount(1)
        ->and($items->first()['title'])->toBe('After Event Report Due')
        ->and($items->first()['description'])->toContain('Due Seminar')
        ->and($items->first()['description'])->toContain('cannot be filed after')
        ->and(NotificationBellHelper::afterEventReportNotifications(recordsUser(2)))->toHaveCount(0);
});

it('emails every official once when a report becomes due', function () {
    Mail::fake();
    aeForm();
    $org = recordsOrganization('After Org');
    $officer = aeOfficial((int) $org->getKey());
    $president = aeOfficial((int) $org->getKey(), 'president');
    aeOfficial((int) $org->getKey(), 'member');
    aeEvent((int) $org->getKey(), 'Due Seminar', now()->subDays(5));
    aeEvent((int) $org->getKey(), 'Too Recent Fair', now()->subDay());

    $this->artisan('after-event:notify')->assertSuccessful();
    $this->artisan('after-event:notify')->assertSuccessful();

    Mail::assertSent(AfterEventReportDueMail::class, 2);
    Mail::assertSent(AfterEventReportDueMail::class, fn ($mail) => $mail->hasTo($officer->user_email) && $mail->eventName === 'Due Seminar');
    Mail::assertSent(AfterEventReportDueMail::class, fn ($mail) => $mail->hasTo($president->user_email));
    expect(DB::table('after_event_report_notifications')->count())->toBe(1);
});

it('lets admins set the elapsed period', function () {
    $admin = recordsUser(2);

    $this->actingAs($admin)->post(route('settings.after-event-days'), ['after_event_days' => 7])
        ->assertSessionHasNoErrors();
    expect(app(AfterEventReportService::class)->elapsedDays())->toBe(7);

    $this->actingAs($admin)->post(route('settings.after-event-days'), ['after_event_days' => -1])
        ->assertSessionHasErrors('after_event_days');
    $this->actingAs(recordsUser(3))->post(route('settings.after-event-days'), ['after_event_days' => 1])
        ->assertForbidden();
});

it('offers New Event form tokens in the after-event palette', function () {
    $org = recordsOrganization('After Org');
    $event = aeEvent((int) $org->getKey(), 'Due Seminar', now()->subDays(5));
    aeNewEventSource($event, recordsUser(3), 'Leadership Seminar');

    $tokens = AfterEventTokenData::paletteTokens(fn ($fields) => collect($fields)->map(fn ($f) => [
        'key' => $f->field_key, 'label' => $f->field_label, 'icon' => 'text', 'group' => 'Basic',
    ])->push(['key' => 'profile.first_name', 'label' => 'First', 'icon' => 'text', 'group' => 'Profile'])->all());

    $keys = array_column($tokens, 'key');
    expect($keys)->toContain('eventinfo.name', 'event.title', 'event.event_location')
        ->and($keys)->not->toContain('event.profile.first_name');
});

/** A criterion with an enabled trigger watching the After Event form. */
function aeScoringRule(array $trigger): \App\Models\ScoringCriterion
{
    $criterion = \App\Models\ScoringCriterion::query()->create([
        'key' => 'custom_after_event_'.bin2hex(random_bytes(3)),
        'category_key' => 'cat1',
        'label' => 'After event filed',
        'weight' => 5,
        'sort_order' => 1000,
        'is_system' => false,
        'is_active' => true,
    ]);
    \App\Models\ScoringRule::query()->create([
        'criterion_id' => $criterion->getKey(),
        'trigger' => $trigger,
        'enabled' => true,
    ]);

    return $criterion;
}

it('tallies filed after-event reports by the fields of their linked New Event submission', function () {
    $form = aeForm();
    $org = recordsOrganization('After Org');
    $officer = aeOfficial((int) $org->getKey());

    foreach (['Leadership Seminar' => 'It went well.', 'Sports Fest' => 'Fun.'] as $title => $summary) {
        $event = aeEvent((int) $org->getKey(), $title, now()->subDays(5));
        aeNewEventSource($event, $officer, $title);
        FormSubmission::query()->create([
            'form_id' => $form->id,
            'organization_id' => $org->getKey(),
            'event_id' => $event->getKey(),
            'submitted_by' => $officer->getKey(),
            'payload' => ['summary' => $summary],
            'submitted_at' => now(),
        ]);
    }

    $trigger = [
        'when' => ['source' => 'form_submission', 'form_id' => $form->id, 'status' => 'approved'],
        'if' => ['op' => 'contains', 'left' => ['var' => 'new_event:title'], 'right' => ['value' => 'seminar']],
        'then' => ['add' => ['kind' => 'const', 'value' => 1]],
    ];
    (new \App\Services\Scoring\TriggerValidator)->validate($trigger);
    $criterion = aeScoringRule($trigger);
    $all = aeScoringRule(array_merge($trigger, ['if' => null]));

    $instances = app(\App\Services\Scoring\ScoringRuleEngine::class)
        ->instancesFor((int) $org->getKey(), Semester::query()->sole());

    expect($instances[$criterion->key])->toBe(1)
        ->and($instances[$all->key])->toBe(2)
        ->and(\App\Services\Scoring\TriggerSummary::text($trigger))->toContain('New Event title contains seminar');
});

it('rejects linked New Event variables on triggers that do not watch the After Event form', function () {
    aeForm();
    $other = Form::query()->create(['name' => 'Other', 'route_name' => 'other', 'is_active' => true, 'is_published' => true]);

    $trigger = [
        'when' => ['source' => 'form_submission', 'form_id' => $other->id],
        'if' => ['op' => 'not_empty', 'left' => ['var' => 'new_event:title']],
        'then' => ['add' => ['kind' => 'const', 'value' => 1]],
    ];

    expect(fn () => (new \App\Services\Scoring\TriggerValidator)->validate($trigger))
        ->toThrow(\Illuminate\Validation\ValidationException::class, 'Linked New Event fields');
});

it('sends a second report for an already-filed event back to the index with an error dialog', function () {
    Mail::fake();
    $form = aeForm();
    $org = recordsOrganization('After Org');
    $officer = aeOfficial((int) $org->getKey());
    $event = aeEvent((int) $org->getKey(), 'Due Seminar', now()->subDays(5));
    aeNewEventSource($event, $officer, 'Leadership Seminar');
    $captured = [];
    aeMockDocx($captured);

    $submit = fn () => $this->actingAs($officer)->post(route('forms.render.submit', $form->route_name), [
        'summary' => 'It went well.',
        AfterEventReportHandler::EVENT_INPUT => $event->getKey(),
    ]);

    $submit()->assertRedirect(route('after-event-reports.index'))->assertSessionHasNoErrors();
    $submit()->assertRedirect(route('after-event-reports.index'))
        ->assertSessionHasErrors(AfterEventReportService::ERROR_ALREADY_FILED);

    expect(FormSubmission::query()->where('form_id', $form->id)->count())->toBe(1);

    $this->actingAs($officer)->get(route('after-event-reports.index'))
        ->assertOk()
        ->assertSee('data-testid="after-event-already-filed"', false)
        ->assertSee('Report already filed')
        ->assertSee('already been filed for “Due Seminar”', false);
});

it('discards a racing duplicate report that slipped past validation', function () {
    $form = aeForm();
    $org = recordsOrganization('After Org');
    $officer = aeOfficial((int) $org->getKey());
    $event = aeEvent((int) $org->getKey(), 'Due Seminar', now()->subDays(5));

    $existing = FormSubmission::query()->create([
        'form_id' => $form->id, 'organization_id' => $org->getKey(), 'event_id' => $event->getKey(),
        'submitted_by' => $officer->getKey(), 'payload' => ['summary' => 'First'], 'submitted_at' => now(),
    ]);
    $late = FormSubmission::query()->create([
        'form_id' => $form->id, 'organization_id' => $org->getKey(),
        'submitted_by' => $officer->getKey(), 'payload' => ['summary' => 'Second'], 'submitted_at' => now(),
    ]);

    $request = \Illuminate\Http\Request::create('/', 'POST', [AfterEventReportHandler::EVENT_INPUT => $event->getKey()]);
    $request->setUserResolver(fn () => $officer);

    try {
        app(AfterEventReportHandler::class)->handle($form, $late, ['summary' => 'Second'], $request);
        $this->fail('Expected the duplicate to be redirected.');
    } catch (\Illuminate\Http\Exceptions\HttpResponseException $exception) {
        expect($exception->getResponse()->getTargetUrl())->toBe(route('after-event-reports.index'));
    }

    expect(FormSubmission::query()->whereKey($late->getKey())->exists())->toBeFalse()
        ->and(FormSubmission::query()->whereKey($existing->getKey())->exists())->toBeTrue();
});

it('shows the already-filed dialog when opening the form for a filed event', function () {
    $form = aeForm();
    $org = recordsOrganization('After Org');
    $officer = aeOfficial((int) $org->getKey());
    $event = aeEvent((int) $org->getKey(), 'Due Seminar', now()->subDays(5));
    FormSubmission::query()->create([
        'form_id' => $form->id, 'organization_id' => $org->getKey(), 'event_id' => $event->getKey(),
        'submitted_by' => $officer->getKey(), 'payload' => ['summary' => 'First'], 'submitted_at' => now(),
    ]);

    $this->actingAs($officer)->get(route('forms.render', ['routeName' => $form->route_name, 'event' => $event->getKey()]))
        ->assertRedirect(route('after-event-reports.index'))
        ->assertSessionHasErrors(AfterEventReportService::ERROR_ALREADY_FILED);
});
