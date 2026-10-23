@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Organization & Student Levels" />

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-xl bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:bg-success-500/10 dark:text-success-300">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:bg-error-500/10 dark:text-error-300">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.levels.update') }}" class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            @csrf @method('PUT')
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-800 dark:text-white/90">Configurable thresholds</h2>
                    <p class="mt-1 max-w-3xl text-xs text-gray-500">Thresholds use a 0–100 percentage. Organization totals are automatically normalized against the current scoring-category maximum.</p>
                </div>
                <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600">Save thresholds</button>
            </div>

            <div class="mt-5 grid gap-5 lg:grid-cols-2">
                @foreach ([\App\Models\LevelThreshold::ORGANIZATION => 'Organization Levels', \App\Models\LevelThreshold::STUDENT => 'Student Levels'] as $scope => $heading)
                    <section class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                        <h3 class="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">{{ $heading }}</h3>
                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($thresholds->get($scope, collect()) as $threshold)
                                <div class="grid grid-cols-[80px_1fr_120px] items-end gap-3 p-4">
                                    <div class="pb-2 text-sm font-bold text-gray-700 dark:text-gray-200">Level {{ $threshold->level_number }}</div>
                                    <div>
                                        <label class="mb-1 block text-[11px] font-medium text-gray-500">Label</label>
                                        <input name="thresholds[{{ $threshold->getKey() }}][label]" required maxlength="100" value="{{ old('thresholds.'.$threshold->getKey().'.label', $threshold->label) }}"
                                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-[11px] font-medium text-gray-500">Minimum %</label>
                                        <input name="thresholds[{{ $threshold->getKey() }}][minimum_score]" type="number" min="0" max="100" step="0.01" required value="{{ old('thresholds.'.$threshold->getKey().'.minimum_score', $threshold->minimum_score) }}"
                                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </form>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <div>
                    <h2 class="text-base font-semibold text-gray-800 dark:text-white/90">Semester level history</h2>
                    <p class="text-xs text-gray-500">Snapshots are refreshed whenever scores or student averages are calculated.</p>
                </div>
                <form method="GET" action="{{ route('admin.levels.index') }}" class="flex flex-wrap gap-2">
                    <x-common.predictive-select name="semester_id" :selected="$selectedSemesterId" placeholder="Select semester" search-placeholder="Search semester…" :auto-submit="true" :options="$semesters->map(fn($semester) => ['value'=>$semester->semester_id,'label'=>$semester->name])->all()" class="w-60" />
                    <x-common.predictive-select name="scope" :selected="$scopeFilter" placeholder="All subjects" search-placeholder="Search subject type…" :auto-submit="true" :options="[['value'=>'organization','label'=>'Organizations'],['value'=>'student','label'=>'Students']]" class="w-48" />
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-white/[0.03]"><tr><th class="px-5 py-3">Type</th><th class="px-5 py-3">Name</th><th class="px-5 py-3">Raw score</th><th class="px-5 py-3">Normalized</th><th class="px-5 py-3">Level</th><th class="px-5 py-3">Calculated</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($histories as $history)
                            <tr>
                                <td class="px-5 py-4 text-xs font-semibold uppercase text-gray-500">{{ $history->scope }}</td>
                                <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-200">{{ $history->subject_name }}</td>
                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ number_format((float) $history->raw_score, 2) }}</td>
                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ number_format((float) $history->normalized_score, 2) }}%</td>
                                <td class="px-5 py-4"><span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">Level {{ $history->level_number }} · {{ $history->level_label }}</span><p class="mt-1 text-[10px] text-gray-400">threshold {{ number_format((float) $history->threshold_snapshot, 2) }}%</p></td>
                                <td class="px-5 py-4 text-xs text-gray-500">{{ $history->calculated_at?->format('M j, Y g:i A') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-gray-500">No level history yet. Open the organization or student rankings for this semester to calculate it.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
