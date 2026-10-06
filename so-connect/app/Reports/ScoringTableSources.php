<?php

namespace App\Reports;

use App\Enums\OrganizationType;
use App\Models\OrganizationScore;
use App\Models\Semester;
use App\Services\Scoring\ScoreCalculator;
use App\Services\Scoring\ScoringCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Exposes organization scoring sheets to report tokens as three read-only
 * virtual tables, matching what the Organization Scoring page shows (a saved,
 * verified score when there is one, otherwise the live tally of the scoring
 * rules):
 *
 *  - `scoring_results`:           one row per scored organization per semester
 *                                 (totals, rank within the semester, verified);
 *  - `scoring_result_categories`: one row per category of each result;
 *  - `scoring_result_criteria`:   one row per criterion of each category
 *                                 (instances × points per instance).
 *
 * Every organization except University-Sanctioned ones is scored, like on the
 * scoring page. Sheets are computed in PHP and served through a JSON_TABLE
 * over a single bound JSON document, one semester at a time and only for the
 * semesters a query can reach: row ids encode their semester
 * (result_id = semester × 10⁶ + organization; category row = result × 10³ +
 * category id; criterion row = result × 10⁵ + criterion id), so filters on
 * ids or `semester_id` narrow the work. Unfiltered queries cover every
 * semester that has started.
 */
final class ScoringTableSources
{
    public const RESULTS = 'scoring_results';

    public const CATEGORIES = 'scoring_result_categories';

    public const CRITERIA = 'scoring_result_criteria';

    private const RESULT_FACTOR = 1000000;

    private const CATEGORY_FACTOR = 1000;

    private const CRITERION_FACTOR = 100000;

    /** @var array<string, array{0: string, 1: string}> table => [primary, label] */
    private const TABLES = [
        self::RESULTS => ['result_id', 'Scoring Results (verified score, or live tally, per organization and semester)'],
        self::CATEGORIES => ['category_row_id', 'Scoring Result Categories (each category of a scoring result)'],
        self::CRITERIA => ['criterion_row_id', 'Scoring Result Criteria (each criterion of a scoring result category)'],
    ];

    /** @var array<string, array<string, array{0: string, 1: string}>> table => column => [catalog type, JSON_TABLE type] */
    private const COLUMNS = [
        self::RESULTS => [
            'result_id' => ['number', 'BIGINT'],
            'organization_id' => ['number', 'BIGINT'],
            'semester_id' => ['number', 'BIGINT'],
            'organization_name' => ['text', 'VARCHAR(255)'],
            'organization_type' => ['number', 'INT'],
            'total_score' => ['number', 'DECIMAL(10,2)'],
            'max_score' => ['number', 'DECIMAL(10,2)'],
            'rank' => ['number', 'INT'],
            'verified' => ['boolean', 'TINYINT'],
            'scored_at' => ['datetime', 'DATETIME'],
        ],
        self::CATEGORIES => [
            'category_row_id' => ['number', 'BIGINT'],
            'result_id' => ['number', 'BIGINT'],
            'organization_id' => ['number', 'BIGINT'],
            'semester_id' => ['number', 'BIGINT'],
            'category_key' => ['text', 'VARCHAR(64)'],
            'category_label' => ['text', 'VARCHAR(255)'],
            'cap' => ['number', 'DECIMAL(10,2)'],
            'points' => ['number', 'DECIMAL(10,2)'],
            'score' => ['number', 'DECIMAL(10,2)'],
            'sort_order' => ['number', 'INT'],
        ],
        self::CRITERIA => [
            'criterion_row_id' => ['number', 'BIGINT'],
            'category_row_id' => ['number', 'BIGINT'],
            'result_id' => ['number', 'BIGINT'],
            'organization_id' => ['number', 'BIGINT'],
            'semester_id' => ['number', 'BIGINT'],
            'category_key' => ['text', 'VARCHAR(64)'],
            'category_label' => ['text', 'VARCHAR(255)'],
            'criterion_key' => ['text', 'VARCHAR(64)'],
            'criterion_label' => ['text', 'VARCHAR(255)'],
            'points_per_instance' => ['number', 'DECIMAL(10,2)'],
            'instances' => ['number', 'INT'],
            'points' => ['number', 'DECIMAL(10,2)'],
            'sort_order' => ['number', 'INT'],
        ],
    ];

    /** @var array<int, array<string, array<int, array<string, mixed>>>> semester id => table => rows */
    private array $computed = [];

    /** @var array<int, Semester>|null */
    private ?array $semesters = null;

    public function has(string $name): bool
    {
        return isset(self::TABLES[$name]);
    }

    /**
     * Catalog entries for the three tables, plus the relations between them
     * and to the real tables. Only has_many relations point at virtual tables,
     * since field paths join real tables directly.
     *
     * @param  array<string, array<string, mixed>>  $tables  the introspected catalog
     * @return array<string, array<string, mixed>>
     */
    public function merge(array $tables): array
    {
        foreach (self::TABLES as $name => [$primary, $label]) {
            if (isset($tables[$name])) {
                return $tables;
            }
        }

        foreach (self::TABLES as $name => [$primary, $label]) {
            $table = ['name' => $name, 'label' => $label, 'primary' => $primary, 'columns' => [], 'relations' => [], 'virtual' => true];
            foreach (self::COLUMNS[$name] as $column => [$type]) {
                $table['columns'][$column] = ['name' => $column, 'label' => Str::headline($column), 'type' => $type, 'enum' => false];
            }
            $tables[$name] = $table;
        }
        $tables[self::RESULTS]['columns']['organization_type']['enum'] = true;

        $link = function (string $child, string $relation, string $parent, string $local, string $foreign, bool $belongsTo) use (&$tables): void {
            if (! isset($tables[$parent]['columns'][$foreign])) {
                return;
            }
            if ($belongsTo) {
                $tables[$child]['relations'][$relation] = [
                    'name' => $relation,
                    'label' => Str::headline($relation).' ('.Str::headline($parent).')',
                    'type' => 'belongs_to',
                    'table' => $parent,
                    'local' => $local,
                    'foreign' => $foreign,
                ];
            }
            $tables[$parent]['relations'][$child] = [
                'name' => $child,
                'label' => $tables[$child]['label'],
                'type' => 'has_many',
                'table' => $child,
                'local' => $foreign,
                'foreign' => $local,
            ];
            ksort($tables[$parent]['relations']);
        };

        foreach ([self::RESULTS, self::CATEGORIES, self::CRITERIA] as $name) {
            $link($name, 'organization', 'organizations', 'organization_id', 'organization_id', true);
            $link($name, 'semester', 'semesters', 'semester_id', 'semester_id', true);
        }
        $link(self::CATEGORIES, 'result', self::RESULTS, 'result_id', 'result_id', false);
        $link(self::CRITERIA, 'result', self::RESULTS, 'result_id', 'result_id', false);
        $link(self::CRITERIA, 'category', self::CATEGORIES, 'category_row_id', 'category_row_id', false);

        foreach (array_keys(self::TABLES) as $name) {
            ksort($tables[$name]['relations']);
        }
        ksort($tables);

        return $tables;
    }

    /**
     * The derived table's SQL and its single binding (the rows as JSON).
     *
     * @param  array<string, array<int, mixed>>  $filters  column => values the caller will also filter by
     * @return array{0: string, 1: array<int, mixed>}|null
     */
    public function sql(string $name, array $filters = []): ?array
    {
        if (! $this->has($name)) {
            return null;
        }

        $rows = [];
        foreach ($this->semestersFor($filters) as $semester) {
            array_push($rows, ...$this->rows($semester)[$name]);
        }

        $columns = [];
        foreach (self::COLUMNS[$name] as $column => [, $sqlType]) {
            $columns[] = '`'.$column.'` '.$sqlType.' PATH \'$."'.$column.'"\' NULL ON EMPTY NULL ON ERROR';
        }

        return [
            'SELECT jt.* FROM JSON_TABLE(CAST(? AS JSON), \'$[*]\' COLUMNS ('.implode(', ', $columns).')) jt',
            [json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)],
        ];
    }

    /**
     * The semesters a query can reach: those named by `semester_id` or encoded
     * in id filters, else every semester that has started.
     *
     * @param  array<string, array<int, mixed>>  $filters
     * @return array<int, Semester>
     */
    private function semestersFor(array $filters): array
    {
        $all = $this->allSemesters();

        $ids = null;
        foreach ([
            'semester_id' => 1,
            'result_id' => self::RESULT_FACTOR,
            'category_row_id' => self::RESULT_FACTOR * self::CATEGORY_FACTOR,
            'criterion_row_id' => self::RESULT_FACTOR * self::CRITERION_FACTOR,
        ] as $column => $divisor) {
            if (! array_key_exists($column, $filters)) {
                continue;
            }
            $found = [];
            foreach ($filters[$column] as $value) {
                if (is_numeric($value) && (int) $value > 0) {
                    $found[intdiv((int) $value, $divisor)] = true;
                }
            }
            $ids = $ids === null ? $found : array_intersect_key($ids, $found);
        }

        if ($ids === null) {
            $today = Carbon::today();

            return array_values(array_filter($all, fn (Semester $s) => $s->starts_at !== null && $s->starts_at->lte($today)));
        }

        return array_values(array_intersect_key($all, $ids));
    }

    /** @return array<int, Semester> */
    private function allSemesters(): array
    {
        return $this->semesters ??= Semester::query()->orderBy('starts_at')->get()->keyBy('semester_id')->all();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>> table => rows
     */
    private function rows(Semester $semester): array
    {
        $semesterId = (int) $semester->getKey();
        if (isset($this->computed[$semesterId])) {
            return $this->computed[$semesterId];
        }

        $organizations = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('o.organization_type', '!=', OrganizationType::UNIVERSITY_SANCTIONED)
            ->orderBy('o.organization_id')
            ->get(['o.organization_id', 'o.organization_type', DB::raw("COALESCE(od.name, 'Unknown Organization') as name")]);

        $saved = OrganizationScore::query()->where('semester_id', $semesterId)->get()->keyBy('organization_id');
        $categories = ScoringCatalog::categories();
        $criteria = ScoringCatalog::criteria();
        $categoryIds = DB::table('scoring_categories')->pluck('scoring_category_id', 'key')->all();
        $criterionIds = DB::table('scoring_criteria')->pluck('scoring_criterion_id', 'key')->all();
        $maxScore = (float) array_sum(array_column($categories, 'cap'));
        $calculator = app(ScoreCalculator::class);

        $out = [self::RESULTS => [], self::CATEGORIES => [], self::CRITERIA => []];
        foreach ($organizations as $organization) {
            $organizationId = (int) $organization->organization_id;
            $sheet = $calculator->sheet($organizationId, $semester, $saved->get($organizationId));
            $resultId = $semesterId * self::RESULT_FACTOR + $organizationId;
            $common = ['result_id' => $resultId, 'organization_id' => $organizationId, 'semester_id' => $semesterId];

            $out[self::RESULTS][] = $common + [
                'organization_name' => (string) $organization->name,
                'organization_type' => (int) $organization->organization_type,
                'total_score' => round($sheet['total'], 2),
                'max_score' => $maxScore,
                'rank' => null,
                'verified' => $sheet['verified'] ? 1 : 0,
                'scored_at' => $sheet['scored_at'],
            ];

            $categoryOrder = 0;
            foreach ($categories as $categoryKey => $category) {
                $categoryOrder++;
                $categoryRowId = $resultId * self::CATEGORY_FACTOR + (int) ($categoryIds[$categoryKey] ?? $categoryOrder);
                $points = 0;
                $criterionOrder = 0;
                foreach ($criteria as $criterionKey => $criterion) {
                    $criterionOrder++;
                    if ($criterion['category'] !== $categoryKey) {
                        continue;
                    }
                    $instances = (int) ($sheet['instances'][$criterionKey] ?? 0);
                    $points += $instances * $criterion['weight'];
                    $out[self::CRITERIA][] = $common + [
                        'criterion_row_id' => $resultId * self::CRITERION_FACTOR + (int) ($criterionIds[$criterionKey] ?? $criterionOrder),
                        'category_row_id' => $categoryRowId,
                        'category_key' => $categoryKey,
                        'category_label' => $category['label'],
                        'criterion_key' => $criterionKey,
                        'criterion_label' => $criterion['label'],
                        'points_per_instance' => (float) $criterion['weight'],
                        'instances' => $instances,
                        'points' => (float) ($instances * $criterion['weight']),
                        'sort_order' => $criterionOrder,
                    ];
                }

                $out[self::CATEGORIES][] = $common + [
                    'category_row_id' => $categoryRowId,
                    'category_key' => $categoryKey,
                    'category_label' => $category['label'],
                    'cap' => (float) $category['cap'],
                    'points' => (float) $points,
                    'score' => (float) ($sheet['categories'][$categoryKey] ?? min($category['cap'], $points)),
                    'sort_order' => $categoryOrder,
                ];
            }
        }

        // Competition ranking within the semester: ties share a rank.
        $totals = array_column($out[self::RESULTS], 'total_score');
        foreach ($out[self::RESULTS] as $i => $result) {
            $out[self::RESULTS][$i]['rank'] = 1 + count(array_filter($totals, fn ($t) => $t > $result['total_score']));
        }

        return $this->computed[$semesterId] = $out;
    }
}
