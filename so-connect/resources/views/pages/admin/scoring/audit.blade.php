@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Scoring Audit Log" />

    <div class="space-y-5">

        {{-- Filter + Export bar --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <form method="GET" action="{{ route('admin.scoring.audit') }}" class="flex flex-wrap items-center gap-3">
                <select name="semester_id"
                        class="h-9 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 focus:border-brand-400 focus:ring-1 focus:ring-brand-400 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="">All Semesters</option>
                    @foreach ($semesters as $sem)
                        <option value="{{ $sem->semester_id }}" @selected($semesterFilter == $sem->semester_id)>
                            {{ $sem->name }}
                        </option>
                    @endforeach
                </select>
                <button type="submit"
                        class="h-9 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                    Filter
                </button>
                <a href="{{ route('admin.scoring.audit.print', $semesterFilter ? ['semester_id' => $semesterFilter] : []) }}"
                   target="_blank"
                   class="ml-auto h-9 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-1.5 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                    Export PDF
                </a>
                <a href="{{ route('admin.scoring.audit.xlsx', $semesterFilter ? ['semester_id' => $semesterFilter] : []) }}"
                   class="h-9 rounded-lg bg-green-500 px-4 text-sm font-medium text-white hover:bg-green-600 flex items-center gap-1.5">
                    Download Excel
                </a>
            </form>
        </div>

        {{-- Table --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Score Records</h3>
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    {{ $rows->count() }} record(s)
                    @if ($semesterFilter) &bull; filtered by semester @endif
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[700px] text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800 text-xs text-gray-500 dark:text-gray-400">
                            <th class="px-6 py-3 text-left font-semibold">Organization</th>
                            <th class="px-4 py-3 text-left font-semibold">Semester</th>
                            <th class="px-4 py-3 text-center font-semibold w-24">Score</th>
                            <th class="px-4 py-3 text-left font-semibold">Scored By</th>
                            <th class="px-4 py-3 text-left font-semibold">Scored At</th>
                            <th class="px-4 py-3 text-left font-semibold">Manual Fields Used</th>
                            <th class="px-4 py-3 text-right font-semibold w-24">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-800/60">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                <td class="px-6 py-4 font-medium text-gray-800 dark:text-white/90">
                                    {{ $row['org_name'] }}
                                </td>
                                <td class="px-4 py-4 text-gray-600 dark:text-gray-400">
                                    {{ $row['semester'] }}
                                </td>
                                <td class="px-4 py-4 text-center font-semibold text-gray-800 dark:text-white/90">
                                    {{ number_format((float) $row['total'], 0) }}
                                    <span class="text-xs font-normal text-gray-400">/ 550</span>
                                </td>
                                <td class="px-4 py-4 text-gray-600 dark:text-gray-400">
                                    {{ $row['scorer_name'] }}
                                </td>
                                <td class="px-4 py-4 text-gray-500 dark:text-gray-400">
                                    {{ $row['scored_at'] ? \Carbon\Carbon::parse($row['scored_at'])->format('M d, Y') : '—' }}
                                </td>
                                <td class="px-4 py-4">
                                    @if (count($row['manual_used']) > 0)
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($row['manual_used'] as $mf)
                                                <span class="inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                                                    {{ $mf }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">None (auto-computed)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <a href="{{ route('admin.scoring.edit', $row['id']) }}"
                                       class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                                        View / Edit
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                                    No scores recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection
