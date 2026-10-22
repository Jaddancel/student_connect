<?php

use App\Models\ContentCategory;
use App\Models\ReportTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function categorizedReportDefinition(): array
{
    return [
        'root' => 'organizations',
        'filters' => [],
        'parameters' => [],
        'tokens' => [],
    ];
}

function scopedReportDefinition(): array
{
    return [
        'parameters' => [],
        'tokens' => [[
            'id' => 'organizations', 'kind' => 'group', 'name' => 'organizations', 'entity' => 'organizations',
            'where' => [], 'order' => [['column' => 'organization_id', 'dir' => 'asc']], 'limit' => null,
            'children' => [[
                'id' => 'organization_id', 'kind' => 'value', 'name' => 'id', 'mode' => 'field',
                'path' => ['organization_id'], 'format' => ['type' => 'text', 'pattern' => null, 'fallback' => ''],
            ]],
        ]],
    ];
}

it('groups report templates by purpose category on the generation page', function () {
    $admin = recordsUser(2);
    $membership = ContentCategory::query()->updateOrCreate(['slug' => 'membership'], [
        'name' => 'Membership', 'description' => 'Member records',
        'icon' => 'user-profile', 'color' => 'blue', 'is_active' => true, 'display_order' => 1,
    ]);
    $financial = ContentCategory::query()->updateOrCreate(['slug' => 'financial'], [
        'name' => 'Financial', 'description' => 'Financial records',
        'icon' => 'charts', 'color' => 'warning', 'is_active' => true, 'display_order' => 2,
    ]);

    ReportTemplate::query()->create([
        'name' => 'Member Directory', 'category_id' => $membership->getKey(), 'audience' => 'admins',
        'definition' => categorizedReportDefinition(), 'is_active' => true,
    ]);
    ReportTemplate::query()->create([
        'name' => 'Budget Summary', 'category_id' => $financial->getKey(), 'audience' => 'admins',
        'definition' => categorizedReportDefinition(), 'is_active' => true,
    ]);

    $this->actingAs($admin)->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Generate reports by purpose')
        ->assertSee('Set these once')
        ->assertSee('All organizations')
        ->assertSee('All school years')
        ->assertSee('All semesters')
        ->assertSee('All statuses')
        ->assertSee('Membership')
        ->assertSee('Member Directory')
        ->assertSee('Accreditation')
        ->assertSee('Activity')
        ->assertSee('Financial')
        ->assertSee('Budget Summary')
        ->assertSee('Accomplishment')
        ->assertSee('Recognition');

    $this->actingAs($admin)->get(route('reports.index', ['category' => $membership->getKey()]))
        ->assertOk()
        ->assertSee('Member Directory')
        ->assertDontSee('Budget Summary');
});

it('shows searchable report scope filters when the report data supports them', function () {
    $admin = recordsUser(2);
    recordsOrganization('Filterable Organization', 'FO');

    ReportTemplate::query()->create([
        'name' => 'Student Scope Report', 'audience' => 'admins',
        'definition' => scopedReportDefinition(), 'is_active' => true,
    ]);

    $this->actingAs($admin)->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Report filters')
        ->assertSee('Organization')
        ->assertSee('Organization status')
        ->assertSee('All organizations')
        ->assertSee('New Organization')
        ->assertSee('Accredited Organization')
        ->assertDontSee('All colleges')
        ->assertDontSee('All courses');
});
