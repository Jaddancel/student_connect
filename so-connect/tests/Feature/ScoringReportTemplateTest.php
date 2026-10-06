<?php

use App\Models\OrganizationScore;
use App\Models\Semester;
use App\Models\Template;
use App\Reports\ReportDefinitionValidator;
use App\Reports\ReportDocxRenderer;
use App\Reports\ReportQueryEngine;
use App\Reports\SchemaCatalog;
use App\Services\Scoring\ScoreCalculator;
use App\Services\Scoring\ScoringCatalog;
use App\Services\Scoring\ScoringRuleEngine;
use Database\Seeders\ReportTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    ScoringCatalog::flush();
    app(SchemaCatalog::class)->forget();

    $this->previous = Semester::create(['name' => 'Previous Sem', 'semester_number' => 1, 'starts_at' => Carbon::today()->subDays(240), 'vacation_days' => 0]);
    $this->current = Semester::create(['name' => 'Current Sem', 'semester_number' => 2, 'starts_at' => Carbon::today()->subDays(60), 'vacation_days' => 0]);

    // Live tallies: Beta has two college seminars in the current semester.
    app()->instance(ScoringRuleEngine::class, new class extends ScoringRuleEngine
    {
        public array $tallies = [];

        public function instancesFor(int $organizationId, Semester $semester): array
        {
            return $this->tallies[$organizationId][(int) $semester->getKey()] ?? [];
        }
    });
});

/** Alpha (verified score), Beta (live tally), Gamma (nothing), plus a University-Sanctioned org that is never scored. */
function srFixture(): array
{
    $alpha = recordsOrganization('Alpha Organization', 'ALP');
    $beta = recordsOrganization('Beta Organization', 'BET');
    $beta->forceFill(['organization_type' => 2])->save();
    $gamma = recordsOrganization('Gamma Organization', 'GAM');
    $sanctioned = recordsOrganization('Sanctioned Organization', 'SAN');
    $sanctioned->forceFill(['organization_type' => 5])->save();

    $payload = ['cat1_seminar_univ' => 2, 'cat3_group_intl' => 4];
    $scores = app(ScoreCalculator::class)->scores($payload);
    OrganizationScore::query()->create([
        'organization_id' => $alpha->getKey(),
        'semester_id' => test()->current->getKey(),
        'payload' => $payload,
        'raw_scores' => $scores,
        'total_weighted_score' => $scores['total'],
        'scored_at' => now(),
    ]);

    app(ScoringRuleEngine::class)->tallies[(int) $beta->getKey()][(int) test()->current->getKey()] = ['cat1_seminar_college' => 2];

    return [$alpha, $beta, $gamma];
}

function srRun(array $params = []): array
{
    return app(ReportQueryEngine::class)->run(ReportTemplateSeeder::definitions()['Organization Scoring Report']['definition'], $params);
}

it('exposes the scoring sheets as report tables', function () {
    $catalog = app(SchemaCatalog::class);

    expect($catalog->has('scoring_results'))->toBeTrue()
        ->and($catalog->hasColumn('scoring_result_criteria', 'points_per_instance'))->toBeTrue()
        ->and($catalog->relation('organizations', 'scoring_results')['type'])->toBe('has_many')
        ->and($catalog->relation('scoring_results', 'scoring_result_categories')['type'])->toBe('has_many')
        ->and($catalog->relation('scoring_result_categories', 'scoring_result_criteria')['type'])->toBe('has_many')
        ->and($catalog->relation('scoring_results', 'organization')['table'])->toBe('organizations');
});

it('prints each organization\'s categories and criteria, defaulting to the current semester', function () {
    [$alpha, $beta] = srFixture();

    $result = srRun();
    $orgs = collect($result['data']['orgs'])->keyBy('name');

    expect($result['params']['semester'])->toBe((int) $this->current->getKey())
        ->and($result['data']['semester_name'])->toBe('Current Sem')
        ->and($result['data']['organization_count'])->toBe('3')
        ->and($orgs->keys()->all())->toBe(['Alpha Organization', 'Gamma Organization', 'Beta Organization']);

    // Verified: 2 × 15 in Programs; 4 × 20 Awards capped at 50.
    $alphaSheet = $orgs['Alpha Organization'];
    $categories = collect($alphaSheet['categories'])->keyBy('label');
    $awards = $categories['III. Awards & Recognition'];
    $seminars = collect($categories['I. Programs & Activities']['criteria'])->keyBy('label')['Seminars - University Level (>=30 members)'];
    expect($alphaSheet)->toMatchArray(['type' => 'Socio-Civic', 'total' => '80', 'max_score' => '550', 'rank' => '1', 'verified' => 'Yes'])
        ->and($categories)->toHaveCount(6)
        ->and($categories['I. Programs & Activities']['score'])->toBe('30')
        ->and($seminars)->toBe(['label' => 'Seminars - University Level (>=30 members)', 'points_per_instance' => '15', 'instances' => '2', 'points' => '30'])
        ->and($awards)->toMatchArray(['cap' => '50', 'points' => '80', 'score' => '50'])
        ->and(collect($awards['criteria'])->pluck('label')->first())->toBe('Group Award - International');

    // Live tally: 2 × 10.
    expect($orgs['Beta Organization'])->toMatchArray(['type' => 'Religious', 'total' => '20', 'rank' => '2', 'verified' => 'No']);

    expect(collect($result['data']['ranking'])->map(fn ($row) => [$row['rank'], $row['name'], $row['type'], $row['total']])->all())->toBe([
        ['1', 'Alpha Organization', 'Socio-Civic', '80'],
        ['2', 'Beta Organization', 'Religious', '20'],
        ['3', 'Gamma Organization', 'Socio-Civic', '0'],
    ]);
});

it('reports on the picked semester', function () {
    [, $beta] = srFixture();
    app(ScoringRuleEngine::class)->tallies[(int) $beta->getKey()][(int) $this->previous->getKey()] = ['cat5_tangible_projects' => 1];

    $result = srRun(['semester' => (int) $this->previous->getKey()]);

    expect($result['data']['semester_name'])->toBe('Previous Sem')
        ->and(collect($result['data']['ranking'])->map(fn ($row) => [$row['rank'], $row['name'], $row['total']])->all())->toBe([
            ['1', 'Beta Organization', '100'],
            ['2', 'Alpha Organization', '0'],
            ['2', 'Gamma Organization', '0'],
        ]);
});

it('only lets a semesters parameter default to the current semester', function () {
    $definition = ['parameters' => [[
        'name' => 'org', 'type' => 'entity', 'entity' => 'organizations', 'display' => [], 'default' => 'current_semester',
    ]], 'tokens' => []];

    expect(fn () => app(ReportDefinitionValidator::class)->validate($definition))
        ->toThrow(ValidationException::class, 'only a semesters parameter');
});

it('prints the scoring report with zeros and the ranking', function () {
    srFixture();
    Storage::disk('public')->put('report-templates/scoring.docx', ReportTemplateSeeder::document(ReportTemplateSeeder::definitions()['Organization Scoring Report']['layout']));
    $slot = new Template(['template_name' => 'Organization Scoring Report', 'docx_path' => 'report-templates/scoring.docx']);

    $path = app(ReportDocxRenderer::class)->render($slot, srRun()['data'], recordsUser(2));
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    File::deleteDirectory(dirname($path));
    preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/', $xml, $m);
    $text = html_entity_decode(implode(' | ', array_filter($m[1], fn ($t) => trim($t) !== '')), ENT_QUOTES | ENT_XML1);

    expect($text)->toContain('Alpha Organization | Socio-Civic  ·  Total Score: 80 / 550  ·  Rank: 1  ·  Verified: Yes')
        ->and($text)->toContain('III. Awards & Recognition  —  Score: 50 / 50')
        ->and($text)->toContain('Seminars - University Level (>=30 members) | 15 | 2 | 30')
        ->and($text)->toContain('Seminars - College Level (>=15 members) | 10 | 0 | 0')
        ->and($text)->toContain('Category subtotal (capped at 50) | 80 | 50')
        ->and($text)->toContain('Overall Ranking')
        ->and($text)->toContain('1 | Alpha Organization | Socio-Civic | 80 | 2 | Beta Organization | Religious | 20 | 3 | Gamma Organization | Socio-Civic | 0')
        ->and($text)->not->toContain('Sanctioned')
        ->and($text)->not->toContain('{{');
});
