<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\Workplan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SemesterController extends Controller
{
    public function index()
    {
        $semesters = Semester::query()
            ->orderByDesc('starts_at')
            ->get()
            ->map(function (Semester $s) {
                return [
                    'model' => $s,
                    'active_start' => $s->activePeriodStart(),
                    'active_end' => $s->activePeriodEnd(),
                    'is_active' => $s->isCurrentlyActive(),
                    'has_finalized_workplan' => Workplan::query()
                        ->where('semester_id', $s->semester_id)
                        ->where('status', 'finalized')
                        ->exists(),
                ];
            });

        $semesterWarning = null;
        $activeSemester = Semester::currentlyActive();
        if ($activeSemester) {
            $daysLeft = (int) Carbon::today()->diffInDays($activeSemester->activePeriodEnd(), false);
            if ($daysLeft >= 0 && $daysLeft <= 30) {
                $semesterWarning = [
                    'semester' => $activeSemester,
                    'days_left' => $daysLeft,
                ];
            }
        }

        return view('pages.admin.semesters.index', [
            'title' => 'Semester Management',
            'semesters' => $semesters,
            'semesterWarning' => $semesterWarning,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'starts_at' => ['required', 'date'],
            'vacation_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        Semester::query()->create([
            'name' => $validated['name'],
            'starts_at' => $validated['starts_at'],
            'vacation_days' => (int) $validated['vacation_days'],
            'created_by' => (int) $request->user()->getKey(),
        ]);

        return redirect()->route('admin.semesters.index')->with('success', 'Semester created successfully.');
    }

    public function edit(Semester $semester)
    {
        return view('pages.admin.semesters.edit', [
            'title' => 'Edit Semester',
            'semester' => $semester,
            'has_finalized_workplan' => Workplan::query()
                ->where('semester_id', $semester->semester_id)
                ->where('status', 'finalized')
                ->exists(),
        ]);
    }

    public function update(Request $request, Semester $semester): RedirectResponse
    {
        $hasFinalized = Workplan::query()
            ->where('semester_id', $semester->semester_id)
            ->where('status', 'finalized')
            ->exists();

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'vacation_days' => ['required', 'integer', 'min:1', 'max:365'],
        ];

        if (! $hasFinalized) {
            $rules['starts_at'] = ['required', 'date'];
        }

        $validated = $request->validate($rules);

        $data = [
            'name' => $validated['name'],
            'vacation_days' => (int) $validated['vacation_days'],
        ];

        if (! $hasFinalized && isset($validated['starts_at'])) {
            $data['starts_at'] = $validated['starts_at'];
        }

        $semester->update($data);

        return redirect()->route('admin.semesters.index')->with('success', 'Semester updated successfully.');
    }
}
