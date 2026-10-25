<?php

use App\Forms\SystemFunction;
use App\Helpers\MenuHelper;
use App\Models\AppSetting;
use App\Models\ContentCategory;
use App\Models\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('organizes administrator links by workflow instead of one mixed admin bucket', function () {
    $this->actingAs(recordsUser(2));
    $groups = collect(MenuHelper::getMenuGroups())->keyBy('title');

    expect($groups->keys()->all())->toContain(
        'Content & Templates', 'Requests', 'Scoring & Evaluation',
        'Records & Reports', 'System Administration',
    );

    expect(collect($groups['Content & Templates']['items'])->pluck('path'))
        ->toContain('/admin/form-builder', '/admin/template-gallery', '/admin/report-templates', '/admin/categories')
        ->not->toContain('/admin/semesters');
    expect(collect($groups['Scoring & Evaluation']['items'])->pluck('path'))
        ->toContain('/admin/scoring', '/admin/scoring/rankings', '/admin/scoring/student-rankings', '/admin/levels');
    expect(collect($groups['System Administration']['items'])->pluck('path'))
        ->toContain('/admin/semesters', '/admin/support-tickets');
});

it('categorizes request queues and officer forms by their configured purpose', function () {
    $financial = ContentCategory::query()->where('slug', 'financial')->firstOrFail();
    $categorized = Form::query()->create([
        'name' => 'Budget Request', 'route_name' => 'budget-request', 'category_id' => $financial->getKey(),
        'is_active' => true, 'is_published' => true, 'show_in_sidebar' => true,
    ]);
    Form::query()->create([
        'name' => 'General Organization Form', 'route_name' => 'general-organization-form',
        'is_active' => true, 'is_published' => true, 'show_in_sidebar' => true,
    ]);

    $this->actingAs(recordsUser(2));
    $requests = collect(MenuHelper::getMenuGroups())->firstWhere('title', 'Requests');
    $categorizedRequest = collect($requests['items'])->firstWhere('path', '/admin/form-requests/'.$categorized->getKey());
    expect($categorizedRequest['section'])->toBe('Financial');

    $officer = recordsUser(3);
    $organization = recordsOrganization('Categorized Menu Organization');
    DB::table('organization_officers')->insert([
        'role' => 'officer', 'organization' => $organization->getKey(), 'user' => $officer->getKey(),
        'member_since' => now(), 'registered_at' => now(), 'reassigned_at' => now(),
    ]);
    $this->actingAs($officer);
    $groups = collect(MenuHelper::getMenuGroups())->keyBy('title');

    expect($groups->keys()->all())->toContain(
        'Overview', 'Activities & Events', 'Reports & Records', 'Forms & Drafts', 'Help & Support'
    );

    $studentForms = collect($groups['Forms & Drafts']['items']);
    expect($studentForms->firstWhere('path', '/forms/budget-request')['section'])->toBe('Financial')
        ->and($studentForms->firstWhere('path', '/forms/general-organization-form')['section'])->toBe('General Forms');
});

it('orders the student sidebar by task and keeps files out of the overview group', function () {
    $officer = recordsUser(3);
    $organization = recordsOrganization('Student Navigation Organization');
    DB::table('organization_officers')->insert([
        'role' => 'officer', 'organization' => $organization->getKey(), 'user' => $officer->getKey(),
        'member_since' => now(), 'registered_at' => now(), 'reassigned_at' => now(),
    ]);
    $eventApplication = Form::query()->create([
        'name' => 'Event Application',
        'route_name' => 'new-event',
        'system_function' => SystemFunction::NEW_EVENT,
        'is_active' => true,
        'is_published' => true,
        'show_in_sidebar' => true,
    ]);

    $this->actingAs($officer);
    $groups = collect(MenuHelper::getMenuGroups());

    expect($groups->pluck('title')->all())->toBe([
        'Overview', 'Activities & Events', 'Reports & Records', 'Forms & Drafts', 'Help & Support',
    ]);

    expect(collect($groups->firstWhere('title', 'Overview')['items'])->pluck('path')->all())
        ->toBe(['/dashboard', '/calendar']);

    expect(collect($groups->firstWhere('title', 'Forms & Drafts')['items'])->pluck('path'))
        ->toContain('/forms', '/drafts')
        ->not->toContain('/documents', '/attachment-library');

    $activityItems = collect($groups->firstWhere('title', 'Activities & Events')['items']);

    expect($activityItems->pluck('path'))
        ->toContain('/event-plans', '/after-event-reports');

    expect($activityItems->firstWhere('path', '/forms/'.$eventApplication->route_name))
        ->toMatchArray([
            'section' => 'Application',
            'name' => 'Apply for a New Event',
        ])
        ->and($activityItems->firstWhere('path', '/event-plans')['section'])->toBe('Tracking');

    expect(collect($groups->firstWhere('title', 'Reports & Records')['items'])->pluck('path'))
        ->toContain('/reports', '/documents', '/attachment-library', '/best-organizations');
});

it('provides one event application entry and keeps the calendar view only', function () {
    $officer = recordsUser(3);
    $organization = recordsOrganization('Event Application Organization');
    DB::table('organization_officers')->insert([
        'role' => 'officer', 'organization' => $organization->getKey(), 'user' => $officer->getKey(),
        'member_since' => now(), 'registered_at' => now(), 'reassigned_at' => now(),
    ]);
    $eventApplication = Form::query()->create([
        'name' => 'Event Application',
        'route_name' => 'event-application-test',
        'system_function' => SystemFunction::NEW_EVENT,
        'is_active' => true,
        'is_published' => true,
        'show_in_sidebar' => true,
    ]);

    $this->actingAs($officer)
        ->get(route('event-plans'))
        ->assertOk()
        ->assertSee('Apply for a New Event')
        ->assertSee(route('forms.render', $eventApplication->route_name));

    $this->get(route('calendar'))
        ->assertOk()
        ->assertDontSee('Create Event Plan')
        ->assertDontSee('open-event-plan-btn');
});

it('groups every administrator-selected accreditation requirement without duplicating it', function () {
    $officer = recordsUser(3);
    $organization = recordsOrganization('Accreditation Navigation Organization');
    DB::table('organization_officers')->insert([
        'role' => 'officer', 'organization' => $organization->getKey(), 'user' => $officer->getKey(),
        'member_since' => now(), 'registered_at' => now(), 'reassigned_at' => now(),
    ]);

    $application = Form::query()->create([
        'name' => 'Organization Accreditation', 'route_name' => 'organization-accreditation',
        'system_function' => SystemFunction::ORG_ACCREDITATION,
        'is_active' => true, 'is_published' => true, 'show_in_sidebar' => true,
    ]);
    $requirements = collect(['Officer Information Sheet', 'Financial Accountability Report'])
        ->map(fn (string $name, int $index) => Form::query()->create([
            'name' => $name,
            'route_name' => 'accreditation-requirement-'.($index + 1),
            'is_active' => true,
            'is_published' => true,
            'show_in_sidebar' => false,
        ]));
    AppSetting::put('accreditation.conditions', [
        'required_forms' => $requirements->pluck('id')->all(),
    ]);

    $this->actingAs($officer);
    $groups = collect(MenuHelper::getMenuGroups());
    $accreditationItems = collect($groups->firstWhere('title', 'Accreditation')['items']);

    expect($accreditationItems->firstWhere('path', '/forms/'.$application->route_name)['section'])
        ->toBe('Application');

    foreach ($requirements as $requirement) {
        expect($accreditationItems->firstWhere('path', '/forms/'.$requirement->route_name)['section'])
            ->toBe('Requirements');
        expect($groups->flatMap(fn (array $group) => $group['items'])->where('path', '/forms/'.$requirement->route_name))
            ->toHaveCount(1);
    }
});
