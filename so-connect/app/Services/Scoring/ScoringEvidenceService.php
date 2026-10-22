<?php

namespace App\Services\Scoring;

use App\Models\OrganizationScore;
use App\Models\ScoringEvidenceAward;
use App\Models\Semester;
use Illuminate\Support\Facades\DB;

class ScoringEvidenceService
{
    /** @return array{instances:array<string,int>,evidence:array<int,array<string,mixed>>} */
    public function evaluate(int $organizationId, Semester $semester): array
    {
        $evaluation = app(ScoringRuleEngine::class)->evaluationFor($organizationId, $semester);
        $categoryScores = app(ScoreCalculator::class)->scores($evaluation['instances']);
        $evaluation['evidence'] = $this->allocateCategoryCaps($evaluation['evidence'], $categoryScores);

        return $evaluation;
    }

    public function snapshotForScore(OrganizationScore $score, Semester $semester): int
    {
        $evaluation = $this->evaluate((int) $score->organization_id, $semester);

        return DB::transaction(function () use ($score, $evaluation): int {
            $score->evidenceAwards()->delete();

            foreach ($evaluation['evidence'] as $item) {
                $score->evidenceAwards()->create([
                    'criterion_key' => $item['criterion_key'],
                    'criterion_label' => $item['criterion_label'],
                    'instances' => $item['instances'],
                    'rule_points' => $item['rule_points'] ?? $item['awarded_points'],
                    'awarded_points' => $item['awarded_points'],
                    'source_type' => $item['source_type'] ?? 'record',
                    'source_id' => $item['source_id'] ?? null,
                    'evidence_label' => $item['evidence_label'] ?? 'Supporting record',
                    'evidence_url' => $item['evidence_url'] ?? null,
                    'attachment_paths' => $item['attachment_paths'] ?? [],
                    'quality_score' => $item['quality_score'] ?? null,
                    'quality_status' => $item['quality_status'] ?? 'not_applicable',
                    'quality_notes' => $item['quality_notes'] ?? [],
                    'review_status' => 'pending',
                ]);
            }

            return count($evaluation['evidence']);
        });
    }

    /**
     * Allocate each category's capped score across its matched records in
     * deterministic rule/record order. This makes the sum of evidence awards
     * equal the score actually granted rather than an uncapped theoretical sum.
     *
     * @param array<int,array<string,mixed>> $evidence
     * @param array<string,int|float> $categoryScores
     * @return array<int,array<string,mixed>>
     */
    private function allocateCategoryCaps(array $evidence, array $categoryScores): array
    {
        $criteria = ScoringCatalog::criteria();
        $remaining = $categoryScores;

        foreach ($evidence as &$item) {
            $category = $criteria[$item['criterion_key']]['category'] ?? null;
            $rulePoints = (float) ($item['rule_points'] ?? $item['awarded_points'] ?? 0);
            $available = $category !== null ? max(0, (float) ($remaining[$category] ?? 0)) : $rulePoints;
            $item['rule_points'] = $rulePoints;
            $item['awarded_points'] = min($rulePoints, $available);
            if ($category !== null) {
                $remaining[$category] = max(0, $available - (float) $item['awarded_points']);
            }
        }
        unset($item);

        return $evidence;
    }

    public function recalculateScore(OrganizationScore $score): OrganizationScore
    {
        $raw = (array) ($score->raw_scores ?? []);
        $base = (float) ($raw['total'] ?? $score->total_weighted_score ?? 0);
        $adjustment = $score->evidenceAwards()
            ->whereNotNull('override_points')
            ->get()
            ->sum(fn (ScoringEvidenceAward $evidence) =>
                (float) $evidence->override_points - (float) $evidence->awarded_points
            );

        $raw['manual_adjustment'] = round($adjustment, 2);
        $score->forceFill([
            'raw_scores' => $raw,
            'total_weighted_score' => min(999.99, max(0, round($base + $adjustment, 2))),
        ])->save();

        return $score->refresh();
    }
}
