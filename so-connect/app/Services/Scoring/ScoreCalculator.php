<?php

namespace App\Services\Scoring;

use App\Models\OrganizationScore;
use App\Models\Semester;

/**
 * The scoring math shared by the Organization Scoring pages and the report
 * catalog's scoring tables ({@see \App\Reports\ScoringTableSources}).
 */
class ScoreCalculator
{
    public function __construct(private readonly ScoringRuleEngine $engine) {}

    /**
     * Auto-tallied instances per criterion, sourced entirely from admin-authored
     * block triggers (Scoring Rules editor). A criterion with no enabled rule
     * tallies 0: the legacy hardcoded "built-in behavior" was retired when
     * scoring moved to the block-programming feature, so every tally is now
     * defined by the blocks or not at all.
     *
     * @return array<string,int>
     */
    public function autoInstances(int $organizationId, Semester $semester): array
    {
        $auto = array_fill_keys(ScoringCatalog::keys(), 0);

        foreach ($this->engine->instancesFor($organizationId, $semester) as $key => $value) {
            if (array_key_exists($key, $auto)) {
                $auto[$key] = (int) $value;
            }
        }

        return $auto;
    }

    /**
     * Weighted sums per category, clamped by each category's cap, plus `total`.
     *
     * @param  array<string,mixed>  $payload  criterion key => instances
     * @return array<string,int|float>
     */
    public function scores(array $payload): array
    {
        $get = fn (string $key): int => max(0, (int) ($payload[$key] ?? 0));

        // The catalog seeds the exact weights/caps this used to hardcode, so
        // existing scores recompute identically.
        $categories = ScoringCatalog::categories();
        $sums = array_fill_keys(array_keys($categories), 0);

        foreach (ScoringCatalog::criteria() as $key => $meta) {
            $category = $meta['category'];
            if (array_key_exists($category, $sums)) {
                $sums[$category] += $get($key) * $meta['weight'];
            }
        }

        $scores = [];
        $total = 0;
        foreach ($categories as $categoryKey => $meta) {
            $scores[$categoryKey] = min($meta['cap'], $sums[$categoryKey]);
            $total += $scores[$categoryKey];
        }
        $scores['total'] = $total;

        return $scores;
    }

    /**
     * An organization's scoring sheet for a semester, as the Organization
     * Scoring page shows it: a saved (verified) score is locked to its stored
     * instances and totals; otherwise the live tally from the scoring rules.
     *
     * @return array{verified: bool, scored_at: ?string, instances: array<string,int>, categories: array<string,int|float>, total: float}
     */
    public function sheet(int $organizationId, Semester $semester, ?OrganizationScore $saved = null): array
    {
        if ($saved !== null) {
            $payload = (array) ($saved->payload ?? []);
            $instances = [];
            foreach (ScoringCatalog::keys() as $key) {
                $instances[$key] = max(0, (int) ($payload[$key] ?? 0));
            }
            $stored = (array) ($saved->raw_scores ?? []);
            $computed = $this->scores($instances);
            $categories = [];
            foreach (array_keys(ScoringCatalog::categories()) as $key) {
                $categories[$key] = $stored[$key] ?? $computed[$key];
            }

            return [
                'verified' => true,
                'scored_at' => $saved->scored_at?->toDateTimeString(),
                'instances' => $instances,
                'categories' => $categories,
                'total' => (float) $saved->total_weighted_score,
            ];
        }

        $instances = $this->autoInstances($organizationId, $semester);
        $scores = $this->scores($instances);
        $total = (float) $scores['total'];
        unset($scores['total']);

        return [
            'verified' => false,
            'scored_at' => null,
            'instances' => $instances,
            'categories' => $scores,
            'total' => $total,
        ];
    }
}
