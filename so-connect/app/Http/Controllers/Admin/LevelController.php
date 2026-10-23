<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LevelHistory;
use App\Models\LevelThreshold;
use App\Models\Semester;
use App\Services\ActionLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LevelController extends Controller
{
    public function index(Request $request)
    {
        $semesters = Semester::query()->orderByDesc('starts_at')->get();
        $semesterId = $request->integer('semester_id') ?: Semester::current()?->getKey();
        $requestedScope = (string) $request->query('scope', '');
        $scope = in_array($requestedScope, [LevelThreshold::ORGANIZATION, LevelThreshold::STUDENT], true)
            ? $requestedScope
            : null;

        $histories = collect();
        if ($semesterId) {
            $query = LevelHistory::query()->where('semester_id', $semesterId)->orderBy('scope')->orderByDesc('normalized_score');
            if ($scope) {
                $query->where('scope', $scope);
            }
            $histories = $query->get();

            $organizationNames = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->whereIn('o.organization_id', $histories->where('scope', LevelThreshold::ORGANIZATION)->pluck('subject_id'))
                ->pluck('od.name', 'o.organization_id');
            $studentNames = DB::table('users as u')
                ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
                ->whereIn('u.user_id', $histories->where('scope', LevelThreshold::STUDENT)->pluck('subject_id'))
                ->select('u.user_id', 'u.user_email', 'p.first_name', 'p.last_name')
                ->get()->mapWithKeys(fn ($row) => [
                    $row->user_id => trim(($row->first_name ?? '').' '.($row->last_name ?? '')) ?: $row->user_email,
                ]);

            $histories->each(function (LevelHistory $history) use ($organizationNames, $studentNames): void {
                $history->subject_name = $history->scope === LevelThreshold::ORGANIZATION
                    ? ($organizationNames[$history->subject_id] ?? 'Unknown Organization')
                    : ($studentNames[$history->subject_id] ?? 'Unknown Student');
            });
        }

        return view('pages.admin.levels.index', [
            'title' => 'Organization & Student Levels',
            'thresholds' => LevelThreshold::query()->orderBy('scope')->orderByDesc('level_number')->get()->groupBy('scope'),
            'histories' => $histories,
            'semesters' => $semesters,
            'selectedSemesterId' => $semesterId,
            'scopeFilter' => $scope,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'thresholds' => ['required', 'array'],
            'thresholds.*.label' => ['required', 'string', 'max:100'],
            'thresholds.*.minimum_score' => ['required', 'numeric', 'between:0,100'],
        ]);

        $thresholds = LevelThreshold::query()->orderByDesc('level_number')->get();
        $before = $thresholds->map->only(['id', 'scope', 'level_number', 'label', 'minimum_score'])->values()->all();

        foreach ([LevelThreshold::ORGANIZATION, LevelThreshold::STUDENT] as $scope) {
            $scopeRows = $thresholds->where('scope', $scope)->sortByDesc('level_number')->values();
            $minimums = $scopeRows->map(fn (LevelThreshold $row) => (float) data_get($validated, 'thresholds.'.$row->getKey().'.minimum_score'));
            if ($scopeRows->count() !== 4 || $minimums->count() !== 4
                || ! ($minimums[0] > $minimums[1] && $minimums[1] > $minimums[2] && $minimums[2] > $minimums[3])
                || (float) $minimums[3] !== 0.0) {
                throw ValidationException::withMessages([
                    'thresholds' => ucfirst($scope).' thresholds must descend from Level 4 to Level 1, and Level 1 must start at 0.',
                ]);
            }
        }

        DB::transaction(function () use ($thresholds, $validated): void {
            foreach ($thresholds as $threshold) {
                $values = $validated['thresholds'][$threshold->getKey()];
                $threshold->update([
                    'label' => trim($values['label']),
                    'minimum_score' => $values['minimum_score'],
                ]);
            }
        });

        ActionLogger::log(
            ActionLogger::CATEGORY_SCORING_CONFIG,
            'level_thresholds_updated',
            'Updated organization and student level thresholds',
            [
                'before' => $before,
                'after' => LevelThreshold::query()->orderBy('scope')->orderByDesc('level_number')
                    ->get()->map->only(['id', 'scope', 'level_number', 'label', 'minimum_score'])->values()->all(),
            ],
        );

        return back()->with('success', 'Level thresholds updated. Rankings will use the new configuration.');
    }
}
