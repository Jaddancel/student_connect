<?php

use App\Helpers\FormTemplateHelper;
use App\Helpers\MenuHelper;
use App\Models\Approval;
use App\Models\Form;
use App\Models\GeneratedDocument;
use App\Models\Request as ActionRequest;
use App\Models\Semester;
use App\Models\Workplan;
use App\Services\DocumentGenerationService;
use App\Services\WorkplanService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function unifiedRequestForm(string $route): Form
{
    return Form::query()->updateOrCreate(
        ['route_name' => $route],
        ['name' => str($route)->replace('-', ' ')->title(), 'is_active' => true, 'is_published' => true],
    );
}

function unifiedActionRequest(Form $form, $user): ActionRequest
{
    return ActionRequest::query()->create([
        'action' => 'Generate document',
        'action_type' => FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION,
        'requested_at' => now(),
        'user' => $user->getKey(),
        'form_id' => $form->getKey(),
        'payload' => ['form_id' => $form->getKey()],
    ]);
}

it('shows the six workflow tabs with pending counters and links to the existing detail page', function () {
    $admin = recordsUser(2);
    $requester = recordsUser(3);
    $projectForm = unifiedRequestForm('project-request');
    foreach (['joint-statement', 'accomplishment-report', 'financial-report', 'organization-recognition'] as $route) {
        unifiedRequestForm($route);
    }
    $item = unifiedActionRequest($projectForm, $requester);

    $response = $this->actingAs($admin)->get(route('admin.requests.index'));

    $response->assertOk()
        ->assertSee('Requests &amp; Approvals Center', false)
        ->assertSee('Projects')
        ->assertSee('Joint Statements')
        ->assertSee('Accomplishments')
        ->assertSee('Financial')
        ->assertSee('Recognition')
        ->assertSee('Workplans')
        ->assertSee(route('admin.project-requests.show', $item->getKey()), false);
});

it('bulk rejects only pending unified requests and requires a reason', function () {
    $admin = recordsUser(2);
    $requester = recordsUser(3);
    $form = unifiedRequestForm('financial-report');
    $first = unifiedActionRequest($form, $requester);
    $second = unifiedActionRequest($form, $requester);
    Approval::query()->create([
        'request' => $second->getKey(), 'admin' => $admin->getKey(),
        'approved_at' => now(), 'is_rejected' => false,
    ]);

    $this->actingAs($admin)->post(route('admin.requests.bulk'), [
        'request_ids' => [$first->getKey(), $second->getKey()],
        'decision' => 'reject',
        'rejection_reason' => 'Required documents are incomplete.',
    ])->assertRedirect()->assertSessionHas('bulk_failures');

    $this->assertDatabaseHas('approvals', [
        'request' => $first->getKey(),
        'is_rejected' => true,
        'rejection_reason' => 'Required documents are incomplete.',
    ]);
    expect(Approval::query()->where('request', $second->getKey())->count())->toBe(1);

    $third = unifiedActionRequest($form, $requester);
    $this->actingAs($admin)->post(route('admin.requests.bulk'), [
        'request_ids' => [$third->getKey()], 'decision' => 'reject',
    ])->assertSessionHasErrors('rejection_reason');
});

it('uses the shared guarded decision flow from an existing request detail page', function () {
    $admin = recordsUser(2);
    $requester = recordsUser(3);
    $item = unifiedActionRequest(unifiedRequestForm('project-request'), $requester);

    $this->actingAs($admin)->post(route('admin.project-requests.decide', $item), [
        'decision' => 'reject',
        'rejection_reason' => '  The required project schedule is incomplete.  ',
    ])->assertRedirect(route('admin.project-requests.index'));

    $this->assertDatabaseHas('approvals', [
        'request' => $item->getKey(),
        'is_rejected' => true,
        'rejection_reason' => 'The required project schedule is incomplete.',
    ]);

    $this->actingAs($admin)->post(route('admin.project-requests.decide', $item), [
        'decision' => 'reject',
        'rejection_reason' => 'A second decision must not replace the first.',
    ])->assertRedirect()->assertSessionHasErrors('request');

    expect(Approval::query()->where('request', $item->getKey())->count())->toBe(1);
    $this->assertDatabaseMissing('approvals', [
        'request' => $item->getKey(),
        'rejection_reason' => 'A second decision must not replace the first.',
    ]);
});

it('bulk approves through the existing document generation workflow', function () {
    $admin = recordsUser(2);
    $requester = recordsUser(3);
    $item = unifiedActionRequest(unifiedRequestForm('project-request'), $requester);
    $generated = Mockery::mock(\App\Models\GeneratedDocument::class);
    $generated->shouldReceive('getKey')->once()->andReturn(901);
    $documents = Mockery::mock(DocumentGenerationService::class);
    $documents->shouldReceive('generateAllFromApprovedRequest')
        ->once()
        ->withArgs(fn ($request, $adminId) => $request->is($item) && $adminId === (int) $admin->getKey())
        ->andReturn(collect([$generated]));
    $this->app->instance(DocumentGenerationService::class, $documents);

    $this->actingAs($admin)->post(route('admin.requests.bulk'), [
        'request_ids' => [$item->getKey()],
        'decision' => 'approve',
    ])->assertRedirect()->assertSessionHas('success', '1 request(s) approved.');

    $this->assertDatabaseHas('approvals', [
        'request' => $item->getKey(), 'admin' => $admin->getKey(), 'is_rejected' => false,
    ]);
});

it('does not record an approval when required document generation fails', function () {
    $admin = recordsUser(2);
    $requester = recordsUser(3);
    $item = unifiedActionRequest(unifiedRequestForm('project-request'), $requester);
    $documents = Mockery::mock(DocumentGenerationService::class);
    $documents->shouldReceive('generateAllFromApprovedRequest')
        ->once()
        ->andThrow(new RuntimeException('Template conversion failed.'));
    $this->app->instance(DocumentGenerationService::class, $documents);

    $this->actingAs($admin)->post(route('admin.requests.bulk'), [
        'request_ids' => [$item->getKey()],
        'decision' => 'approve',
    ])->assertRedirect()
        ->assertSessionMissing('success')
        ->assertSessionHas('bulk_failures', fn ($failures) => str_contains($failures[0], 'Template conversion failed.'));

    $this->assertDatabaseMissing('approvals', ['request' => $item->getKey()]);
});

it('keeps joint statement approval and reports a warning when document generation is deferred', function () {
    $admin = recordsUser(2);
    $requester = recordsUser(3);
    $item = unifiedActionRequest(unifiedRequestForm('joint-statement'), $requester);
    $documents = Mockery::mock(DocumentGenerationService::class);
    $documents->shouldReceive('generateAllFromApprovedRequest')
        ->once()
        ->andThrow(new RuntimeException('No printable template is configured.'));
    $this->app->instance(DocumentGenerationService::class, $documents);

    $this->actingAs($admin)->post(route('admin.requests.bulk'), [
        'request_ids' => [$item->getKey()],
        'decision' => 'approve',
    ])->assertRedirect()
        ->assertSessionHas('success', '1 request(s) approved.')
        ->assertSessionHas('bulk_warnings', fn ($warnings) => str_contains($warnings[0], 'Document generation is still pending'));

    $this->assertDatabaseHas('approvals', [
        'request' => $item->getKey(),
        'is_rejected' => false,
    ]);
});

it('synchronizes the matching workplan to the calendar after approval', function () {
    $admin = recordsUser(2);
    $requester = recordsUser(3);
    $organization = recordsOrganization('Calendar Sync Organization');
    $semester = Semester::query()->create([
        'name' => 'Calendar Sync Semester',
        'semester_number' => 1,
        'starts_at' => '2027-01-10',
        'vacation_days' => 30,
    ]);
    $form = Form::query()->create([
        'name' => 'Workplan Form',
        'route_name' => 'calendar-sync-workplan',
        'system_function' => \App\Forms\SystemFunction::NEW_WORKPLAN,
        'is_active' => true,
        'is_published' => true,
    ]);
    $workplan = Workplan::query()->create([
        'organization_id' => $organization->getKey(),
        'semester_id' => $semester->getKey(),
        'status' => 'finalized',
    ]);
    $item = unifiedActionRequest($form, $requester);
    $item->update([
        'organization_id' => $organization->getKey(),
        'payload' => [
            'form_id' => $form->getKey(),
            'organization_id' => $organization->getKey(),
            'semester_id' => $semester->getKey(),
        ],
    ]);

    $generated = Mockery::mock(GeneratedDocument::class);
    $generated->shouldReceive('getKey')->once()->andReturn(902);
    $documents = Mockery::mock(DocumentGenerationService::class);
    $documents->shouldReceive('generateAllFromApprovedRequest')->once()->andReturn(collect([$generated]));
    $workplans = Mockery::mock(WorkplanService::class);
    $workplans->shouldReceive('convertApprovedPlansToCalendarEvents')
        ->once()
        ->withArgs(fn (Workplan $candidate) => $candidate->is($workplan))
        ->andReturn(2);
    $this->app->instance(DocumentGenerationService::class, $documents);
    $this->app->instance(WorkplanService::class, $workplans);

    $this->actingAs($admin)->post(route('admin.requests.bulk'), [
        'request_ids' => [$item->getKey()],
        'decision' => 'approve',
    ])->assertRedirect()->assertSessionHas('success', '1 request(s) approved.');

    $this->assertDatabaseHas('approvals', [
        'request' => $item->getKey(),
        'is_rejected' => false,
    ]);
});

it('reports calendar synchronization failures without undoing workplan approval', function () {
    $admin = recordsUser(2);
    $requester = recordsUser(3);
    $organization = recordsOrganization('Calendar Warning Organization');
    $semester = Semester::query()->create([
        'name' => 'Calendar Warning Semester',
        'semester_number' => 1,
        'starts_at' => '2027-06-10',
        'vacation_days' => 30,
    ]);
    $form = Form::query()->create([
        'name' => 'Warning Workplan Form',
        'route_name' => 'calendar-warning-workplan',
        'system_function' => \App\Forms\SystemFunction::NEW_WORKPLAN,
        'is_active' => true,
        'is_published' => true,
    ]);
    Workplan::query()->create([
        'organization_id' => $organization->getKey(),
        'semester_id' => $semester->getKey(),
        'status' => 'finalized',
    ]);
    $item = unifiedActionRequest($form, $requester);
    $item->update([
        'organization_id' => $organization->getKey(),
        'payload' => [
            'form_id' => $form->getKey(),
            'organization_id' => $organization->getKey(),
            'semester_id' => $semester->getKey(),
        ],
    ]);

    $generated = Mockery::mock(GeneratedDocument::class);
    $generated->shouldReceive('getKey')->once()->andReturn(903);
    $documents = Mockery::mock(DocumentGenerationService::class);
    $documents->shouldReceive('generateAllFromApprovedRequest')->once()->andReturn(collect([$generated]));
    $workplans = Mockery::mock(WorkplanService::class);
    $workplans->shouldReceive('convertApprovedPlansToCalendarEvents')
        ->once()
        ->andThrow(new RuntimeException('Calendar database is unavailable.'));
    $this->app->instance(DocumentGenerationService::class, $documents);
    $this->app->instance(WorkplanService::class, $workplans);

    $this->actingAs($admin)->post(route('admin.requests.bulk'), [
        'request_ids' => [$item->getKey()],
        'decision' => 'approve',
    ])->assertRedirect()
        ->assertSessionHas('success', '1 request(s) approved.')
        ->assertSessionHas('bulk_warnings', fn ($warnings) => str_contains($warnings[0], 'Calendar database is unavailable.'));

    $this->assertDatabaseHas('approvals', [
        'request' => $item->getKey(),
        'is_rejected' => false,
    ]);
});

it('uses one sidebar link for the unified workflows without duplicate form links', function () {
    $form = unifiedRequestForm('project-request');
    $this->actingAs(recordsUser(2));
    $items = collect(MenuHelper::getMenuGroups())->flatMap(fn (array $group) => $group['items']);
    $paths = collect($items)->pluck('path');

    expect($paths)->toContain('/admin/requests')
        ->not->toContain('/admin/workplan-requests', '/admin/form-requests/'.$form->getKey());
});

it('keeps the center restricted to system administrators', function () {
    $this->get(route('admin.requests.index'))->assertRedirect();
    $this->actingAs(recordsUser(3))->get(route('admin.requests.index'))->assertForbidden();
    $this->actingAs(recordsUser(1))->get(route('admin.requests.index'))->assertOk();
    $this->actingAs(recordsUser(3))->post(route('admin.requests.bulk'), [
        'request_ids' => [1],
        'decision' => 'approve',
    ])->assertForbidden();
});

it('shows an empty queue instead of another workflow when the selected form is not configured', function () {
    $admin = recordsUser(2);
    $requester = recordsUser(3);
    unifiedActionRequest(unifiedRequestForm('project-request'), $requester);

    $this->actingAs($admin)
        ->get(route('admin.requests.index', ['type' => 'workplans']))
        ->assertOk()
        ->assertSee('No requests match these filters.')
        ->assertDontSee('Project Requests</span>', false);
});
