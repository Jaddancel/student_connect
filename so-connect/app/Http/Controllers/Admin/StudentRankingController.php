<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\Officer;
use App\Models\Semester;
use App\Services\ActionLogger;
use App\Services\LevelingService;
use App\Services\StudentRankingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class StudentRankingController extends Controller
{
    public function index(Request $request, StudentRankingService $rankings)
    {
        $semesters = Semester::query()->orderByDesc('starts_at')->get();
        $selectedSemesterId = $request->integer('semester_id') ?: Semester::current()?->getKey();
        $selectedSemester = $selectedSemesterId
            ? $semesters->firstWhere('semester_id', $selectedSemesterId)
            : null;

        $rows = $selectedSemester ? $rankings->rankings($selectedSemester) : collect();
        if ($selectedSemester) {
            $levels = app(LevelingService::class);
            $rows->each(fn (array $row) => $levels->recordStudent($selectedSemester, $row));
        }

        return view('pages.admin.scoring.student-rankings', [
            'title' => 'Student Rankings',
            'semesters' => $semesters,
            'selectedSemester' => $selectedSemester,
            'selectedSemesterId' => $selectedSemesterId,
            'rows' => $rows,
            'levelThresholds' => app(LevelingService::class)->thresholds(\App\Models\LevelThreshold::STUDENT),
            'students' => $this->studentMemberships(),
            'canEvaluate' => $selectedSemester !== null && $selectedSemester->starts_at->lte(today()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'semester_id' => ['required', 'integer', Rule::exists('semesters', 'semester_id')],
            'org_officer_id' => ['required', 'integer', Rule::exists('organization_officers', 'org_officer_id')],
            'score' => ['required', 'numeric', 'between:0,100'],
            'description' => ['required', 'string', 'max:2000'],
            'evaluated_at' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $semester = Semester::query()->findOrFail((int) $validated['semester_id']);
        $evaluatedAt = Carbon::parse($validated['evaluated_at'])->startOfDay();
        $semesterEnd = $semester->endsAt()?->endOfDay();
        if ($evaluatedAt->lt($semester->starts_at->copy()->startOfDay())
            || ($semesterEnd !== null && $evaluatedAt->gt($semesterEnd))) {
            throw ValidationException::withMessages([
                'evaluated_at' => 'The evaluation date must fall within the selected semester.',
            ]);
        }

        $membership = Officer::query()->with('user.profile')->findOrFail((int) $validated['org_officer_id']);
        if ($membership->user === null) {
            throw ValidationException::withMessages([
                'org_officer_id' => 'The selected membership is not linked to a student account.',
            ]);
        }

        $evaluation = Evaluation::query()->create([
            'author' => (int) $membership->getKey(),
            'description' => trim($validated['description']),
            'score' => number_format((float) $validated['score'], 2, '.', ''),
            'evaluated_at' => $evaluatedAt,
        ]);

        ActionLogger::log(
            ActionLogger::CATEGORY_SCORING,
            'student_evaluation_saved',
            'Saved an individual student evaluation',
            [
                'student_user_id' => (int) $membership->getAttribute('user'),
                'organization_officer_id' => (int) $membership->getKey(),
                'semester_id' => (int) $semester->getKey(),
                'score' => (float) $validated['score'],
            ],
            $evaluation,
        );

        $row = app(StudentRankingService::class)->rankings($semester)
            ->firstWhere('user_id', (int) $membership->getAttribute('user'));
        if ($row) {
            app(LevelingService::class)->recordStudent($semester, $row);
        }

        return redirect()->route('admin.student-rankings.index', ['semester_id' => $semester->getKey()])
            ->with('success', 'Student evaluation saved and rankings updated.');
    }

    private function studentMemberships(): \Illuminate\Support\Collection
    {
        return DB::table('organization_officers as oo')
            ->join('users as u', 'u.user_id', '=', 'oo.user')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->leftJoin('organizations as o', 'o.organization_id', '=', 'oo.organization')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->orderBy('p.last_name')
            ->orderBy('p.first_name')
            ->orderBy('od.name')
            ->get([
                'oo.org_officer_id', 'oo.role', 'oo.position', 'u.user_email',
                'p.first_name', 'p.middle_name', 'p.last_name', 'p.student_id',
                'od.name as organization_name',
            ])
            ->map(function (object $membership): object {
                $name = collect([$membership->first_name, $membership->middle_name, $membership->last_name])
                    ->filter(fn ($part) => trim((string) $part) !== '')
                    ->implode(' ');
                $membership->student_name = $name !== '' ? $name : $membership->user_email;

                return $membership;
            });
    }
}
