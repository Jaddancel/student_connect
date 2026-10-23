<?php

namespace App\Services;

use App\Models\LevelHistory;
use App\Models\LevelThreshold;
use App\Models\OrganizationScore;
use App\Models\Semester;
use App\Services\Scoring\ScoringCatalog;
use Illuminate\Support\Collection;

class LevelingService
{
    /** @return Collection<int,LevelThreshold> */
    public function thresholds(string $scope): Collection
    {
        return LevelThreshold::query()->where('scope', $scope)->orderByDesc('minimum_score')->get();
    }

    /** @return array{number:int,label:string,color:string,minimum_score:float,normalized_score:float} */
    public function level(string $scope, float $normalizedScore): array
    {
        $score = min(100, max(0, round($normalizedScore, 2)));
        $thresholds = $this->thresholds($scope);
        $threshold = $thresholds->first(fn (LevelThreshold $item) => $score >= (float) $item->minimum_score)
            ?? $thresholds->last();

        return [
            'number' => (int) ($threshold?->level_number ?? 1),
            'label' => (string) ($threshold?->label ?? 'Unrated'),
            'color' => (string) ($threshold?->color ?? 'gray'),
            'minimum_score' => (float) ($threshold?->minimum_score ?? 0),
            'normalized_score' => $score,
        ];
    }

    /** @return array{number:int,label:string,color:string,minimum_score:float,normalized_score:float} */
    public function organizationLevel(float $rawScore): array
    {
        $maximum = max(1, (float) collect(ScoringCatalog::categories())->sum('cap'));

        return $this->level(LevelThreshold::ORGANIZATION, ($rawScore / $maximum) * 100);
    }

    /** @return array{number:int,label:string,color:string,minimum_score:float,normalized_score:float} */
    public function studentLevel(float $average): array
    {
        return $this->level(LevelThreshold::STUDENT, $average);
    }

    public function recordOrganization(OrganizationScore $score, ?Semester $semester = null): LevelHistory
    {
        $semester ??= $score->semester()->firstOrFail();
        $level = $this->organizationLevel((float) $score->total_weighted_score);

        return $this->record(
            LevelThreshold::ORGANIZATION,
            (int) $semester->getKey(),
            (int) $score->organization_id,
            (float) $score->total_weighted_score,
            $level,
        );
    }

    /** @param array<string,mixed> $row */
    public function recordStudent(Semester $semester, array $row): LevelHistory
    {
        $level = $this->studentLevel((float) $row['average_score']);

        return $this->record(
            LevelThreshold::STUDENT,
            (int) $semester->getKey(),
            (int) $row['user_id'],
            (float) $row['average_score'],
            $level,
        );
    }

    /** @param array{number:int,label:string,color:string,minimum_score:float,normalized_score:float} $level */
    private function record(string $scope, int $semesterId, int $subjectId, float $rawScore, array $level): LevelHistory
    {
        return LevelHistory::query()->updateOrCreate(
            ['scope' => $scope, 'semester_id' => $semesterId, 'subject_id' => $subjectId],
            [
                'raw_score' => $rawScore,
                'normalized_score' => $level['normalized_score'],
                'level_number' => $level['number'],
                'level_label' => $level['label'],
                'threshold_snapshot' => $level['minimum_score'],
                'calculated_at' => now(),
            ],
        );
    }
}
