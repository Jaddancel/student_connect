@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Organization Rankings" />

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Semester Rankings</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Select a semester to view the leaderboard.</p>
                </div>
                <form method="GET" action="{{ route('admin.scoring.rankings') }}">
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
                <p class="text-sm text-gray-400 dark:text-gray-500">No organizations have been scored for this semester.</p>
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
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Sole</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Active</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Awards</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Ext</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Tangible</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Admin</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Total / 100
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($rows as $row)
                                @php
                                    $score = $row['score'];
                                    $raw = $row['raw'] ?? [];
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
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $raw['sole'] ?? 0 }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $raw['active'] ?? 0 }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $raw['awards'] ?? 0 }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $raw['extension'] ?? 0 }}
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $raw['tangible'] ?? 0 }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $raw['admin'] ?? 0 }}</td>
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
@endsection
