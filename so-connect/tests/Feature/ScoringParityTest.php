<?php

use App\Http\Controllers\Admin\OrganizationScoringController;
use App\Services\Scoring\ScoringCatalog;
use Database\Seeders\ScoringConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Guards the score-parity contract: moving the category caps and criterion
 * weights out of hardcoded PHP into the seeded scoring catalog must never
 * change a computed score. This formula is an independent copy of the
 * pre-catalog computeScores() math — if the catalog, seeder, or the
 * catalog-driven computeScores() drift, this test fails.
 */
function legacyComputeScores(array $payload): array
{
    $get = fn (string $key): int => max(0, (int) ($payload[$key] ?? 0));

    $cat1 = min(100,
        $get('cat1_seminar_college') * 10 +
        $get('cat1_seminar_univ') * 15 +
        $get('cat1_activities_related') * 10 +
        $get('cat1_activities_not_related') * 7 +
        $get('cat1_donation_cash') * 2 +
        $get('cat1_donation_kinds') * 10 +
        $get('cat1_cosponsor_pts') +
        $get('cat1_income')
    );

    $cat2 = min(100,
        $get('cat2_other_orgs') * 10 +
        $get('cat2_rep_local') * 2 +
        $get('cat2_rep_provincial') * 4 +
        $get('cat2_rep_regional') * 6 +
        $get('cat2_rep_national') * 8 +
        $get('cat2_rep_international') * 10 +
        $get('cat2_ssc_osa_activities') * 10 +
        $get('cat2_ssc_seminars') * 10 +
        $get('cat2_other_seminars') * 7 +
        $get('cat2_osa_seminars') * 5 +
        $get('cat2_ssc_meeting_rep') * 2 +
        $get('cat2_ssc_meeting_proxy') +
        $get('cat2_help_ssc_osa') * 5 +
        $get('cat2_help_others') * 3
    );

    $cat3 = min(50,
        $get('cat3_group_intl') * 20 +
        $get('cat3_group_national') * 10 +
        $get('cat3_group_regional') * 7 +
        $get('cat3_group_provincial') * 5 +
        $get('cat3_group_local') * 3 +
        $get('cat3_individual_intl') * 10 +
        $get('cat3_individual_national') * 7 +
        $get('cat3_individual_regional') * 5 +
        $get('cat3_individual_provincial') * 2 +
        $get('cat3_individual_local')
    );

    $cat4 = min(100, $get('cat4_extension_groups') * 10);

    $cat5 = min(100, $get('cat5_tangible_projects') * 100);

    $cat6 = min(100,
        $get('cat6_documents') * 50 +
        $get('cat6_meetings') * 25 +
        $get('cat6_leadership') * 15 +
        $get('cat6_transparency') * 10
    );

    $total = $cat1 + $cat2 + $cat3 + $cat4 + $cat5 + $cat6;

    return compact('cat1', 'cat2', 'cat3', 'cat4', 'cat5', 'cat6', 'total');
}

function catalogComputeScores(array $payload): array
{
    $reflection = new ReflectionClass(OrganizationScoringController::class);
    $method = $reflection->getMethod('computeScores');

    return $method->invoke($reflection->newInstanceWithoutConstructor(), $payload);
}

function randomSystemPayloads(int $count): iterable
{
    mt_srand(20260712);
    $keys = array_keys(ScoringCatalog::SYSTEM_CRITERIA);

    for ($i = 0; $i < $count; $i++) {
        $payload = [];
        foreach ($keys as $key) {
            $payload[$key] = mt_rand(0, 12);
        }
        yield $payload;
    }
}

it('computes identical scores from the seeded catalog', function () {
    $this->seed(ScoringConfigSeeder::class);
    ScoringCatalog::flush();

    foreach (randomSystemPayloads(200) as $payload) {
        expect(catalogComputeScores($payload))->toBe(legacyComputeScores($payload));
    }
});

it('computes identical scores from the code fallback when the catalog tables are empty', function () {
    ScoringCatalog::flush();

    foreach (randomSystemPayloads(200) as $payload) {
        expect(catalogComputeScores($payload))->toBe(legacyComputeScores($payload));
    }
});

it('seeds exactly the six system categories and thirty-eight system criteria', function () {
    $this->seed(ScoringConfigSeeder::class);

    expect(\App\Models\ScoringCategory::query()->count())->toBe(6)
        ->and(\App\Models\ScoringCriterion::query()->where('is_system', true)->count())->toBe(38);
});
