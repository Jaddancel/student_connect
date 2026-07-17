<?php

use App\Http\Controllers\Admin\OrganizationReportController;
use App\Services\RecordQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('builds report rows with organization type labels and officer counts', function () {
    $detailA = DB::table('organization_details')->insertGetId([
        'name' => 'Alpha Organization',
        'detail_text' => 'Alpha details',
        'initials' => 'ALP',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $detailB = DB::table('organization_details')->insertGetId([
        'name' => 'Beta Organization',
        'detail_text' => 'Beta details',
        'initials' => 'BET',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $orgA = DB::table('organizations')->insertGetId([
        'organization_type' => 1,
        'detail' => $detailA,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $orgB = DB::table('organizations')->insertGetId([
        'organization_type' => 2,
        'detail' => $detailB,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('organization_officers')->insert([
        [
            'role' => 'officer',
            'organization' => $orgA,
            'registered_at' => now(),
        ],
        [
            'role' => 'president',
            'organization' => $orgA,
            'registered_at' => now(),
        ],
        [
            'role' => 'member',
            'organization' => $orgA,
            'registered_at' => now(),
        ],
        [
            'role' => 'officer',
            'organization' => $orgB,
            'registered_at' => now(),
        ],
    ]);

    $controller = new OrganizationReportController(app(RecordQueryService::class));
    $method = new ReflectionMethod(OrganizationReportController::class, 'organizationRows');
    $method->setAccessible(true);

    $rows = collect($method->invoke($controller))->keyBy('organization_id');

    expect((int) $rows[$orgA]['officer_count'])->toBe(2)
        ->and((int) $rows[$orgB]['officer_count'])->toBe(1)
        ->and($rows[$orgA]['type'])->toBe('University Sanctioned Organization')
        ->and($rows[$orgB]['type'])->toBe('College-Based Organization');
});
