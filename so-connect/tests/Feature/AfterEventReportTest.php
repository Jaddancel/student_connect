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
    $form = Form::query()->create([
        'name' => 'New Event',
        'route_name' => 'new-event',
        'system_function' => SystemFunction::NEW_EVENT,
        'is_active' => true,
        'is_published' => true,
    ]);
    foreach (['title' => 'text', 'event_location' => 'text'] as $key => $type) {
        FormDescription::query()->create([
            'form_id' => $form->id, 'field_key' => $key, 'field_label' => ucfirst($key),
            'field_type' => $type, 'is_required' => false, 'field_order' => 1,
        ]);
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

    // A second filing for the same event is refused.
    $this->actingAs($officer)->post(route('forms.render.submit', $form->route_name), [
        'summary' => 'Again',
        AfterEventReportHandler::EVENT_INPUT => $event->getKey(),
    ])->assertSessionHasErrors('form');
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
