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
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Filters</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Select a semester and optional category.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="GET" action="{{ route('admin.scoring.index') }}" class="flex flex-wrap items-center gap-2">
                        <select name="semester_id" onchange="this.form.submit()"
                            class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                            <option value="">Select semester</option>
                            @foreach ($semesters as $semester)
                                <option value="{{ $semester->semester_id }}" @selected($selectedSemester?->semester_id === $semester->semester_id)>
                                    {{ $semester->name }}
                                </option>
                            @endforeach
                        </select>

                        @if ($selectedSemester)
                            <select name="category" onchange="this.form.submit()"
                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                <option value="">All categories</option>
                                @foreach ($categoryLabels as $typeId => $label)
                                    <option value="{{ $typeId }}" @selected($categoryFilter === $typeId)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="semester_id" value="{{ $selectedSemester->semester_id }}" />
                        @endif
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
        @elseif ($groupedRows->isEmpty())
            <div
                class="rounded-2xl border border-gray-200 bg-white p-10 text-center dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-400 dark:text-gray-500">No organizations found.</p>
            </div>
        @else
            @foreach ($groupedRows as $typeId => $categoryRows)
                @php $categoryName = $categoryLabels[$typeId] ?? 'Other'; @endphp
                <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                        <div>
                            <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $categoryName }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $selectedSemester->name }}</p>
                        </div>
                        <span class="text-xs text-gray-400 dark:text-gray-500">
                            {{ count($categoryRows) }} {{ Str::plural('organization', count($categoryRows)) }}
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Organization</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Partial Score</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Score Status</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Scored At</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Verify Score</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($categoryRows as $row)
                                    @php
                                        $score = $row['score'];
                                        $verified = $row['verified'];
                                    @endphp
                                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                        <td class="px-6 py-4 font-medium text-gray-800 dark:text-white/90">
                                            {{ $row['organization_name'] }}
                                        </td>
                                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                                            <span class="font-semibold text-gray-800 dark:text-white/90">{{ number_format((float) $row['partial_score'], 0) }}</span>
                                            <span class="text-xs text-gray-400 dark:text-gray-500">/ 550</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            @if ($verified)
                                                <span class="inline-flex items-center gap-1 rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">
                                                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                                    Verified
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-700/40 dark:text-gray-400">
                                                    Pending
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                            {{ $score?->scored_at?->format('M d, Y') ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4">
                                            @if ($verified)
                                                <a href="{{ route('admin.scoring.edit', $score->organization_score_id) }}"
                                                    class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                                                    Review Score
                                                </a>
                                            @else
                                                <a href="{{ route('admin.scoring.create', ['organization_id' => $row['organization_id'], 'semester_id' => $selectedSemester->semester_id]) }}"
                                                    class="rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-600 whitespace-nowrap">
                                                    Verify Score
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
@endsection
