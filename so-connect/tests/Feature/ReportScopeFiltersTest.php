<?php

use App\Enums\OrganizationType;
use App\Reports\ReportQueryEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function organizationScopeDefinition(): array
{
    return [
        'parameters' => [],
        'tokens' => [[
            'id' => 'students',
            'kind' => 'group',
            'name' => 'students',
            'entity' => 'organizations',
            'where' => [],
            'order' => [['column' => 'organization_id', 'dir' => 'asc']],
            'limit' => null,
            'children' => [[
                'id' => 'student_name',
                'kind' => 'value',
                'name' => 'name',
                'mode' => 'field',
                'path' => ['organization_id'],
                'format' => ['type' => 'text', 'pattern' => null, 'fallback' => ''],
            ]],
        ]],
    ];
}

it('filters report rows by organization and exposes organization status', function () {
    $selected = recordsOrganization('Selected Organization', 'SO');
    recordsOrganization('Other Organization', 'OO');

    $engine = app(ReportQueryEngine::class);
    $result = $engine->run(organizationScopeDefinition(), scope: [
        'organization' => $selected->getKey(),
    ]);

    expect($result['data']['students'])->toHaveCount(1)
        ->and((int) $result['data']['students'][0]['name'])->toBe((int) $selected->getKey())
        ->and($engine->scopeAvailability(organizationScopeDefinition()))
        ->toContain('organization', 'organization_status');
});

it('provides the required organization type choices', function () {
    expect(array_values(OrganizationType::options()))->toBe([
        'Supreme Student Council',
        'College Student Council',
        'Academic',
        'Fraternities/Sororities',
        'Religious',
        'Socio-Civic',
        'Special Interest',
        'University Sanctioned',
    ]);
});

it('allows select all to bypass temporal defaults and required validation', function () {
    $definition = organizationScopeDefinition();
    $definition['parameters'][] = [
        'name' => 'school_year', 'label' => 'School Year', 'type' => 'entity',
        'entity' => 'school_years', 'display' => ['label'], 'required' => true,
        'default' => 'current_school_year',
    ];

    $result = app(ReportQueryEngine::class)->run($definition, ['school_year' => '__all__']);

    expect($result['params']['school_year'])->toBeNull();
});

it('uses the global organization scope for active organization report parameters', function () {
    $selected = recordsOrganization('Global Filter Organization', 'GFO');
    $definition = organizationScopeDefinition();
    $definition['parameters'][] = [
        'name' => 'organization', 'label' => 'Organization', 'type' => 'entity',
        'entity' => 'organizations', 'display' => ['detail', 'name'], 'required' => true,
        'context' => 'active_organization',
    ];
    $definition['tokens'][0]['where'] = [[
        'column' => 'organization_id', 'op' => '=', 'param' => 'organization',
    ]];

    $result = app(ReportQueryEngine::class)->run($definition, scope: [
        'organization' => $selected->getKey(),
    ]);

    expect((int) $result['params']['organization'])->toBe((int) $selected->getKey())
        ->and($result['data']['students'])->toHaveCount(1);
});
