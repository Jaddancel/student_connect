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

    expect($groups->keys()->all())->toContain('Financial Forms', 'Organization Forms')
        ->and(collect($groups['Financial Forms']['items'])->pluck('path'))->toContain('/forms/budget-request')
        ->and(collect($groups['Organization Forms']['items'])->pluck('path'))->toContain('/forms/general-organization-form');
});
