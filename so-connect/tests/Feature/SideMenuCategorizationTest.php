<?php

use App\Helpers\MenuHelper;
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
        'Overview', 'Forms & Submissions', 'Organization Management', 'Help & Support'
    );

    $studentForms = collect($groups['Forms & Submissions']['items']);
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

    $this->actingAs($officer);
    $groups = collect(MenuHelper::getMenuGroups());

    expect($groups->pluck('title')->all())->toBe([
        'Overview', 'Forms & Submissions', 'Organization Management', 'Help & Support',
    ]);

    expect(collect($groups->firstWhere('title', 'Overview')['items'])->pluck('path')->all())
        ->toBe(['/dashboard', '/calendar']);

    expect(collect($groups->firstWhere('title', 'Forms & Submissions')['items'])->pluck('path'))
        ->toContain('/forms', '/drafts', '/documents', '/attachment-library');

    expect(collect($groups->firstWhere('title', 'Organization Management')['items'])->pluck('path'))
        ->toContain('/event-plans', '/after-event-reports', '/reports', '/best-organizations');
});
