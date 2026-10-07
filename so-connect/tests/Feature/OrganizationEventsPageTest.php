<?php

use App\Forms\SystemFunction;
use App\Models\Approval;
use App\Models\EventPlan;
use App\Models\Form;
use App\Models\Request as ActionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function publicActivity(int $organizationId, Form $form, string $title, string $start, array $overrides = [], ?bool $rejected = false): EventPlan
{
    $user = recordsUser(3);
    $request = ActionRequest::query()->create([
        'action' => 'document_generation',
        'action_type' => \App\Helpers\FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION,
        'requested_at' => now(),
        'user' => $user->getKey(),
        'form_id' => $form->getKey(),
    ]);
    if ($rejected !== null) {
        Approval::query()->create([
            'request' => $request->getKey(),
            'approved_at' => now(),
            'is_rejected' => $rejected,
        ]);
    }

    return EventPlan::query()->create(array_merge([
        'organization_id' => $organizationId,
        'created_by' => $user->getKey(),
        'title' => $title,
        'target_date' => substr($start, 0, 10),
        'event_location' => 'Campus Hall',
        'event_start_time' => $start,
        'event_end_time' => \Carbon\Carbon::parse($start)->addHours(2)->toDateTimeString(),
        'status' => 'approved',
        'request_id' => $request->getKey(),
    ], $overrides));
}

it('shows only approved activity requests for the organization ordered nearest to now', function () {
    config(['app.display_timezone' => 'Asia/Manila']);
    $this->travelTo(\Carbon\Carbon::parse('2026-10-07 12:00:00', 'Asia/Manila'));
    $org = recordsOrganization('Events Guild');
    $other = recordsOrganization('Other Guild');
    $form = Form::query()->create([
        'name' => 'New Event', 'route_name' => 'event-test', 'system_function' => SystemFunction::NEW_EVENT,
    ]);
    $workplan = Form::query()->create([
        'name' => 'Workplan', 'route_name' => 'workplan-test', 'system_function' => SystemFunction::NEW_WORKPLAN,
    ]);

    publicActivity($org->getKey(), $form, 'Far Future', '2026-10-10 12:00:00');
    publicActivity($org->getKey(), $form, 'Recent Event', '2026-10-07 11:00:00');
    publicActivity($org->getKey(), $form, 'Next Concert', '2026-10-07 14:00:00');
    publicActivity($org->getKey(), $form, 'Pending Activity', '2026-10-07 13:00:00', ['status' => 'pending']);
    publicActivity($org->getKey(), $form, 'Rejected Activity', '2026-10-07 13:00:00', ['status' => 'rejected'], true);
    publicActivity($org->getKey(), $form, 'No Approval', '2026-10-07 13:00:00', [], null);
    publicActivity($org->getKey(), $form, 'Rejected Approval', '2026-10-07 13:00:00', [], true);
    $presidentOnly = publicActivity($org->getKey(), $form, 'President Approval Only', '2026-10-07 13:00:00');
    Approval::query()->where('request', $presidentOnly->request_id)->update(['stage' => 'president']);
    publicActivity($org->getKey(), $workplan, 'Workplan Only', '2026-10-07 13:00:00');
    publicActivity($other->getKey(), $form, 'Other Organization', '2026-10-07 13:00:00');
    publicActivity($org->getKey(), $form, 'Unscheduled', '2026-10-07 13:00:00', ['event_start_time' => null]);
    publicActivity($org->getKey(), $form, 'Parent Plan', '2026-10-07 13:00:00', ['request_id' => null]);

    $params = ['organizationId' => $org->getKey(), 'slug' => 'events-guild-'.$org->getKey()];
    $this->get(route('organization-events', $params))
        ->assertOk()
        ->assertSee('Next Event')
        ->assertSee('Campus Hall')
        ->assertSee('October 7, 2026')
        ->assertSee('2:00 PM')
        ->assertViewHas('events', fn ($events) => $events->pluck('title')->all() === ['Recent Event', 'Next Concert', 'Far Future'])
        ->assertViewHas('upcomingEvents', fn ($events) => $events->pluck('title')->all() === ['Next Concert', 'Far Future'])
        ->assertViewHas('upcomingEvents', fn ($events) => $events->first()['start'] === '2026-10-07T14:00:00+08:00')
        ->assertDontSee('Pending Activity')
        ->assertDontSee('Rejected Activity')
        ->assertDontSee('No Approval')
        ->assertDontSee('Rejected Approval')
        ->assertDontSee('President Approval Only')
        ->assertDontSee('Workplan Only')
        ->assertDontSee('Other Organization')
        ->assertDontSee('Unscheduled')
        ->assertDontSee('Parent Plan');

    $this->get(route('organization-feed', $params))->assertOk()->assertSee(route('organization-events', $params), false);
});

it('shows empty states and redirects stale organization event slugs', function () {
    $org = recordsOrganization('Empty Events');
    $params = ['organizationId' => $org->getKey(), 'slug' => 'empty-events-'.$org->getKey()];

    $this->get(route('organization-events', $params))
        ->assertOk()
        ->assertSee('No upcoming events.')
        ->assertSee('No events yet.');

    $this->get(route('organization-events', array_merge($params, ['slug' => 'old-slug'])))
        ->assertRedirect(route('organization-events', $params));
    $this->get(route('organization-events', ['organizationId' => 999999, 'slug' => 'missing']))->assertNotFound();
});

it('keeps past approved events visible without a next event', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-10-07 12:00:00', 'Asia/Manila'));
    $org = recordsOrganization('Past Events');
    $form = Form::query()->create([
        'name' => 'New Event', 'route_name' => 'past-event-test', 'system_function' => SystemFunction::NEW_EVENT,
    ]);
    publicActivity($org->getKey(), $form, 'Past Concert', '2026-10-01 12:00:00');

    $this->get(route('organization-events', ['organizationId' => $org->getKey(), 'slug' => 'past-events-'.$org->getKey()]))
        ->assertOk()
        ->assertSee('Past Concert')
        ->assertViewHas('upcomingEvents', fn ($events) => $events->isEmpty());
});
