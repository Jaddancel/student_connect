<?php

namespace App\Services\Scoring;

use App\Models\ScoringCategory;
use App\Models\ScoringCriterion;
use App\Models\ScoringRubricVersion;
use Illuminate\Support\Facades\DB;

class ScoringRubricVersionService
{
    public function current(?int $userId = null): ScoringRubricVersion
    {
        $snapshot = $this->snapshot();
        $hash = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_SLASHES));

        $existing = ScoringRubricVersion::query()->where('content_hash', $hash)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($snapshot, $hash, $userId) {
            $existing = ScoringRubricVersion::query()->where('content_hash', $hash)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            return ScoringRubricVersion::query()->create([
                'version' => ((int) ScoringRubricVersion::query()->max('version')) + 1,
                'content_hash' => $hash,
                'snapshot' => $snapshot,
                'created_by' => $userId,
                'published_at' => now(),
            ]);
        });
    }

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        return [
            'categories' => ScoringCategory::query()->orderBy('sort_order')->get()
                ->map(fn ($category) => [
                    'key' => $category->key,
                    'label' => $category->label,
                    'cap' => (int) $category->cap,
                    'sort_order' => (int) $category->sort_order,
                ])->all(),
            'criteria' => ScoringCriterion::query()->with('rule')->orderBy('sort_order')->orderBy('scoring_criterion_id')->get()
                ->map(fn (ScoringCriterion $criterion) => [
                    'key' => $criterion->key,
                    'category_key' => $criterion->category_key,
                    'label' => $criterion->label,
                    'weight' => (int) $criterion->weight,
                    'sort_order' => (int) $criterion->sort_order,
                    'is_active' => (bool) $criterion->is_active,
                    'rule' => $criterion->rule ? [
                        'enabled' => (bool) $criterion->rule->enabled,
                        'trigger' => $criterion->rule->trigger,
                    ] : null,
                ])->all(),
        ];
    }
}
