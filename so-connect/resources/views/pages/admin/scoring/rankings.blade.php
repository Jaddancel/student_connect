@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Organization Rankings" />

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Semester Rankings</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Select a semester and optional organization type to view the leaderboard.</p>
                </div>
                <form method="GET" action="{{ route('admin.scoring.rankings') }}" class="flex flex-wrap items-center gap-2">
                    <select name="semester_id" data-autosubmit
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                        <option value="">Select semester</option>
                        @foreach ($semesters as $semester)
                            <option value="{{ $semester->semester_id }}" @selected($selectedSemester?->semester_id === $semester->semester_id)>
                                {{ $semester->name }}
                            </option>
                        @endforeach
                    </select>
                    <select name="organization_type" data-autosubmit
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                        <option value="">All organization types</option>
                        @foreach ($typeLabels as $typeId => $label)
                            <option value="{{ $typeId }}" @selected($typeFilter === $typeId)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        @if (!$selectedSemester)
            <div
                class="rounded-2xl border border-gray-200 bg-white p-10 text-center dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-400 dark:text-gray-500">Select a semester first to view rankings.</p>
            </div>
        @elseif ($rows->isEmpty())
            <div
                class="rounded-2xl border border-gray-200 bg-white p-10 text-center dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-400 dark:text-gray-500">
                    @if ($typeFilter)
                        No {{ $typeLabels[$typeFilter] ?? 'matching' }} organizations have been scored for this semester.
                    @else
                        No organizations have been scored for this semester.
                    @endif
                </p>
            </div>
        @else
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <div>
                        <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $selectedSemester->name }}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Ranked by total weighted score</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Rank</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Organization
                                </th>
                                @foreach ($categories as $category)
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                        {{ $category['label'] }} / 100</th>
                                @endforeach
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Total /
                                    {{ collect($categories)->sum('cap') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($rows as $row)
                                @php
                                    $score = $row['score'];
                                    $categoryScores = $row['categories'] ?? [];
                                    $rank = $loop->iteration;
                                    $rowClass = match ($rank) {
                                        1 => 'bg-amber-50/70 dark:bg-amber-500/10',
                                        2 => 'bg-slate-50/80 dark:bg-slate-500/10',
                                        3 => 'bg-orange-50/70 dark:bg-orange-500/10',
                                        default => '',
                                    };
                                @endphp
                                <tr class="{{ $rowClass }} transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-4 font-semibold text-gray-800 dark:text-white/90">{{ $rank }}
                                    </td>
                                    <td class="px-6 py-4 font-medium text-gray-800 dark:text-white/90">
                                        {{ $row['org_name'] }}</td>
                                    @foreach ($categories as $categoryKey => $category)
                                        @php
                                            $categoryScore = (float) ($categoryScores[$categoryKey] ?? 0);
                                            $categoryCap = (float) $category['cap'];
                                            $normalized = $categoryCap > 0 ? $categoryScore / $categoryCap * 100 : 0;
                                        @endphp
                                        <td class="whitespace-nowrap px-6 py-4 text-gray-600 dark:text-gray-400"
                                            title="{{ $categoryScore + 0 }} / {{ $categoryCap + 0 }} raw points">
                                            {{ number_format($normalized, 2) }}</td>
                                    @endforeach
                                    <td class="px-6 py-4 font-semibold text-gray-800 dark:text-white/90">
                                        {{ number_format((float) $score->total_weighted_score, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
    <script @nonce>
        if (!window.__csAutoSubmitBound) { window.__csAutoSubmitBound = true; document.addEventListener('change', (e) => { const t = e.target; if (t instanceof HTMLElement && t.dataset.autosubmit !== undefined && t.form) { t.form.submit(); } }); }
    </script>
@endsection
