<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\Workplan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
                    'is_current' => $s->isCurrentPeriod(),
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
            'canStartNewYear' => ! Semester::hasActiveOrUpcoming(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (Semester::hasActiveOrUpcoming()) {
            return redirect()->route('admin.semesters.index')
                ->with('error', 'A school year is already in progress. You can only start a new school year after both semesters have concluded.');
        }

        $validated = $request->validate([
            'starts_at'        => ['required', 'date'],
            'second_starts_at' => ['required', 'date', 'after:starts_at'],
            'vacation_days'    => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $year      = (int) Carbon::parse($validated['starts_at'])->format('Y');
        $nextYear  = $year + 1;
        $schoolYear = "{$year}–{$nextYear}";
        $createdBy = (int) $request->user()->getKey();
        $vacDays   = (int) $validated['vacation_days'];

        Semester::query()->create([
            'name'            => "First Semester {$schoolYear}",
            'semester_number' => 1,
            'starts_at'       => $validated['starts_at'],
            'vacation_days'   => $vacDays,
            'created_by'      => $createdBy,
        ]);

        Semester::query()->create([
            'name'            => "Second Semester {$schoolYear}",
            'semester_number' => 2,
            'starts_at'       => $validated['second_starts_at'],
            'vacation_days'   => $vacDays,
            'created_by'      => $createdBy,
        ]);

        return redirect()->route('admin.semesters.index')
            ->with('success', "School year {$schoolYear} started. Both semesters have been created.");
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
            'vacation_days' => ['required', 'integer', 'min:1', 'max:365'],
        ];

        if (! $hasFinalized) {
            $rules['starts_at'] = ['required', 'date'];
        }

        $validated = $request->validate($rules);

        $data = [
            'vacation_days' => (int) $validated['vacation_days'],
        ];

        if (! $hasFinalized && isset($validated['starts_at'])) {
            $data['starts_at'] = $validated['starts_at'];
        }

        $semester->update($data);

        return redirect()->route('admin.semesters.index')->with('success', 'Semester updated successfully.');
    }
}
