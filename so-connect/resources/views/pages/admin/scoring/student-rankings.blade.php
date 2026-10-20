@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Student Rankings" />

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="space-y-6">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Individual Student Leaderboard</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Students are ranked by average evaluation score for the selected semester.</p>
                        </div>
                        <form method="GET" action="{{ route('admin.student-rankings.index') }}">
                            <select name="semester_id" data-autosubmit
                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                <option value="">Select semester</option>
                                @foreach ($semesters as $semester)
                                    <option value="{{ $semester->semester_id }}" @selected($selectedSemester?->semester_id === $semester->semester_id)>
                                        {{ $semester->name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                        <div class="rounded-lg bg-success-50 px-3 py-2 text-success-700 dark:bg-success-500/10 dark:text-success-400"><strong>Level 4</strong><br>90–100 Outstanding</div>
                        <div class="rounded-lg bg-brand-50 px-3 py-2 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400"><strong>Level 3</strong><br>80–89.99 Advanced</div>
                        <div class="rounded-lg bg-warning-50 px-3 py-2 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400"><strong>Level 2</strong><br>70–79.99 Proficient</div>
                        <div class="rounded-lg bg-gray-100 px-3 py-2 text-gray-700 dark:bg-gray-800 dark:text-gray-300"><strong>Level 1</strong><br>Below 70 Developing</div>
                    </div>
                </div>

                @if (!$selectedSemester)
                    <div class="rounded-2xl border border-gray-200 bg-white p-10 text-center dark:border-gray-800 dark:bg-white/[0.03]">
                        <p class="text-sm text-gray-400 dark:text-gray-500">Select a semester first to view student rankings.</p>
                    </div>
                @elseif ($rows->isEmpty())
                    <div class="rounded-2xl border border-gray-200 bg-white p-10 text-center dark:border-gray-800 dark:bg-white/[0.03]">
                        <p class="text-sm text-gray-400 dark:text-gray-500">No student evaluations have been recorded for this semester.</p>
                    </div>
                @else
                    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                            <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $selectedSemester->name }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $rows->count() }} evaluated {{ Str::plural('student', $rows->count()) }}</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <th class="px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Rank</th>
                                        <th class="px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Student</th>
                                        <th class="px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Organization / Role</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400">Evaluations</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400">Average</th>
                                        <th class="px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Level</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($rows as $row)
                                        @php
                                            $rankClass = match ($row['rank']) {
                                                1 => 'bg-amber-50/70 dark:bg-amber-500/10',
                                                2 => 'bg-slate-50/80 dark:bg-slate-500/10',
                                                3 => 'bg-orange-50/70 dark:bg-orange-500/10',
                                                default => '',
                                            };
                                            $levelClass = match ($row['level']['color']) {
                                                'success' => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400',
                                                'brand' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400',
                                                'warning' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400',
                                                default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                                            };
                                        @endphp
                                        <tr class="{{ $rankClass }} transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                            <td class="px-4 py-4 text-lg font-bold text-gray-800 dark:text-white/90">{{ $row['rank'] }}</td>
                                            <td class="px-4 py-4">
                                                <p class="font-medium text-gray-800 dark:text-white/90">{{ $row['name'] }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $row['student_id'] ?: 'No Student ID' }}
                                                    @if ($row['course'] || $row['year_section'])
                                                        · {{ trim($row['course'].' '.$row['year_section']) }}
                                                    @endif
                                                </p>
                                            </td>
                                            <td class="px-4 py-4 text-gray-600 dark:text-gray-400">
                                                <p>{{ $row['organizations'] ?: '—' }}</p>
                                                <p class="text-xs">{{ $row['roles'] ?: '—' }}</p>
                                            </td>
                                            <td class="px-4 py-4 text-center text-gray-600 dark:text-gray-400">{{ $row['evaluation_count'] }}</td>
                                            <td class="px-4 py-4 text-center text-base font-semibold text-gray-800 dark:text-white/90">{{ number_format($row['average_score'], 2) }}</td>
                                            <td class="px-4 py-4">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $levelClass }}">
                                                    Level {{ $row['level']['number'] }} · {{ $row['level']['label'] }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            <aside class="h-fit rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Record Student Evaluation</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">A student’s semester average automatically updates after saving.</p>

                @if (!$selectedSemester)
                    <p class="mt-5 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">Select a semester before recording an evaluation.</p>
                @elseif (!$canEvaluate)
                    <p class="mt-5 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">Evaluations cannot be recorded before this semester starts.</p>
                @elseif ($students->isEmpty())
                    <p class="mt-5 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">No organization members are available.</p>
                @else
                    @php
                        $semesterEnd = $selectedSemester->endsAt();
                        $evaluationMax = $semesterEnd && $semesterEnd->lt(today()) ? $semesterEnd : today();
                        $evaluationDefault = today()->between($selectedSemester->starts_at, $semesterEnd ?? today())
                            ? today()
                            : $evaluationMax;
                    @endphp
                    <form method="POST" action="{{ route('admin.student-rankings.store') }}" class="mt-5 space-y-4">
                        @csrf
                        <input type="hidden" name="semester_id" value="{{ $selectedSemester->semester_id }}">

                        <div>
                            <label for="org_officer_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Student <span class="text-error-500">*</span></label>
                            <select id="org_officer_id" name="org_officer_id" required
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                                <option value="">Choose a student…</option>
                                @foreach ($students as $student)
                                    <option value="{{ $student->org_officer_id }}" @selected((string) old('org_officer_id') === (string) $student->org_officer_id)>
                                        {{ $student->student_name }} — {{ $student->organization_name ?: 'Unknown Organization' }} ({{ Str::headline($student->role) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="score" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Score <span class="text-error-500">*</span></label>
                                <input id="score" name="score" type="number" min="0" max="100" step="0.01" required value="{{ old('score') }}"
                                    class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                            </div>
                            <div>
                                <label for="evaluated_at" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Date <span class="text-error-500">*</span></label>
                                <input id="evaluated_at" name="evaluated_at" type="date" required
                                    min="{{ $selectedSemester->starts_at->toDateString() }}"
                                    max="{{ $evaluationMax->toDateString() }}"
                                    value="{{ old('evaluated_at', $evaluationDefault->toDateString()) }}"
                                    class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                            </div>
                        </div>

                        <div>
                            <label for="description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Evaluation Description <span class="text-error-500">*</span></label>
                            <textarea id="description" name="description" rows="4" maxlength="2000" required
                                placeholder="Describe the assessed participation, leadership, service, or achievement."
                                class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">{{ old('description') }}</textarea>
                        </div>

                        <button type="submit" class="w-full rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                            Save Evaluation
                        </button>
                    </form>
                @endif
            </aside>
        </div>
    </div>
    <script @nonce>
        if (!window.__csAutoSubmitBound) { window.__csAutoSubmitBound = true; document.addEventListener('change', (e) => { const t = e.target; if (t instanceof HTMLElement && t.dataset.autosubmit !== undefined && t.form) { t.form.submit(); } }); }
    </script>
@endsection
