@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Organization Scoring" />

    <div class="space-y-6">
        @if (session('success'))
            <div
                class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div
                class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Semester</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Select a semester to score organizations.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="GET" action="{{ route('admin.scoring.index') }}">
                        <select name="semester_id" onchange="this.form.submit()"
                            class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                            <option value="">Select semester</option>
                            @foreach ($semesters as $semester)
                                <option value="{{ $semester->semester_id }}" @selected($selectedSemester?->semester_id === $semester->semester_id)>
                                    {{ $semester->name }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                    @if ($selectedSemester)
                        <a href="{{ route('admin.scoring.rankings', ['semester_id' => $selectedSemester->semester_id]) }}"
                            class="rounded-lg border border-brand-300 px-3 py-1.5 text-xs font-medium text-brand-600 transition hover:bg-brand-50 dark:border-brand-700 dark:text-brand-400 dark:hover:bg-brand-900/20">
                            View Rankings
                        </a>
                    @endif
                </div>
            </div>
        </div>

        @if (!$selectedSemester)
            <div
                class="rounded-2xl border border-gray-200 bg-white p-10 text-center dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-400 dark:text-gray-500">Select a semester first to begin scoring.</p>
            </div>
        @else
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div
                    class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <div>
                        <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Organizations</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $selectedSemester->name }}</p>
                    </div>
                    <span class="text-xs text-gray-400 dark:text-gray-500">
                        {{ $rows->count() }} {{ Str::plural('organization', $rows->count()) }}
                    </span>
                </div>

                @if ($rows->isEmpty())
                    <div class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                        No organizations found.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                        Organization</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Score</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Scored At
                                    </th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($rows as $row)
                                    @php $score = $row['score']; @endphp
                                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                        <td class="px-6 py-4 font-medium text-gray-800 dark:text-white/90">
                                            {{ $row['organization_name'] }}
                                        </td>
                                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                                            @if ($score)
                                                {{ number_format((float) $score->total_weighted_score, 2) }} / 100
                                            @else
                                                <span class="text-gray-400">Not scored</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                            {{ $score?->scored_at?->format('M d, Y') ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4">
                                            @if ($score)
                                                <a href="{{ route('admin.scoring.edit', $score->organization_score_id) }}"
                                                    class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                                                    Edit Score
                                                </a>
                                            @else
                                                <a href="{{ route('admin.scoring.create', ['organization_id' => $row['organization_id'], 'semester_id' => $selectedSemester->semester_id]) }}"
                                                    class="rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-600">
                                                    Score Organization
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection
