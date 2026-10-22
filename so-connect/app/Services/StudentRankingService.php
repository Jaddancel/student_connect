<?php

namespace App\Services;

use App\Models\Semester;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds an individual-student leaderboard from the evaluations recorded for
 * organization memberships. Students are ranked once even when they belong to
 * several organizations; every numeric evaluation in the selected semester
 * contributes to their average and total.
 */
final class StudentRankingService
{
    public function __construct(private readonly LevelingService $levels) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rankings(Semester $semester): Collection
    {
        $end = $semester->endsAt()?->endOfDay() ?? now();

        $evaluations = DB::table('evaluations as e')
            ->join('organization_officers as oo', 'oo.org_officer_id', '=', 'e.author')
            ->join('users as u', 'u.user_id', '=', 'oo.user')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->leftJoin('organizations as o', 'o.organization_id', '=', 'oo.organization')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->whereBetween('e.evaluated_at', [$semester->starts_at->copy()->startOfDay(), $end])
            ->orderBy('e.evaluated_at')
            ->get([
                'e.evaluation_id', 'e.score', 'e.evaluated_at',
                'u.user_id', 'u.user_email',
                'p.first_name', 'p.middle_name', 'p.last_name', 'p.student_id', 'p.course', 'p.year_section',
                'oo.role', 'oo.position', 'od.name as organization_name',
            ])
            ->filter(fn (object $row) => is_numeric($row->score));

        $rows = $evaluations
            ->groupBy('user_id')
            ->map(function (Collection $studentEvaluations): array {
                $student = $studentEvaluations->first();
                $scores = $studentEvaluations->pluck('score')->map(fn ($score) => (float) $score);
                $average = round((float) $scores->average(), 2);

                return [
                    'user_id' => (int) $student->user_id,
                    'name' => $this->studentName($student),
                    'student_id' => (string) ($student->student_id ?? ''),
                    'email' => (string) ($student->user_email ?? ''),
                    'course' => (string) ($student->course ?? ''),
                    'year_section' => (string) ($student->year_section ?? ''),
                    'organizations' => $studentEvaluations->pluck('organization_name')->filter()->unique()->values()->implode(', '),
                    'roles' => $studentEvaluations->pluck('role')->filter()->map(fn ($role) => str($role)->headline()->toString())->unique()->values()->implode(', '),
                    'evaluation_count' => $scores->count(),
                    'total_score' => round((float) $scores->sum(), 2),
                    'average_score' => $average,
                    'level' => $this->level($average),
                    'last_evaluated_at' => $studentEvaluations->max('evaluated_at'),
                ];
            })
            ->sort(function (array $left, array $right): int {
                return $right['average_score'] <=> $left['average_score']
                    ?: $right['total_score'] <=> $left['total_score']
                    ?: $right['evaluation_count'] <=> $left['evaluation_count']
                    ?: strcasecmp($left['name'], $right['name']);
            })
            ->values();

        $lastScore = null;
        $rank = 0;

        return $rows->map(function (array $row, int $index) use (&$lastScore, &$rank): array {
            $scoreKey = number_format($row['average_score'], 2, '.', '').'|'.number_format($row['total_score'], 2, '.', '');
            if ($scoreKey !== $lastScore) {
                $rank = $index + 1;
                $lastScore = $scoreKey;
            }
            $row['rank'] = $rank;

            return $row;
        });
    }

    /** @return array{number:int,label:string,color:string} */
    public function level(float $average): array
    {
        return $this->levels->studentLevel($average);
    }

    private function studentName(object $student): string
    {
        $name = collect([$student->first_name, $student->middle_name, $student->last_name])
            ->filter(fn ($part) => trim((string) $part) !== '')
            ->implode(' ');

        return $name !== '' ? $name : (string) ($student->user_email ?? 'Unknown Student');
    }
}
