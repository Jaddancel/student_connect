@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Application for Recognition/Renewal" />

    <div class="space-y-6">

        <x-admin.wizard-progress :step="1" :total="3" :labels="[1 => 'Workplan', 2 => 'Signature', 3 => 'Review & Submit']" />

        {{-- Header --}}
        <div class="rounded-2xl border border-gray-200 bg-palette-lime-pale p-5 shadow-[inset_0_4px_0_var(--color-palette-lime)] dark:border-gray-800 dark:bg-white/[0.03] dark:shadow-[inset_0_4px_0_rgb(165_255_91_/_0.3)] lg:p-6">
            <div class="text-center">
                <h2 class="text-xl font-bold uppercase tracking-widest text-gray-900 dark:text-white">
                    Step 1 — Workplan Activities Review
                </h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Review your finalized workplan activities. These will be included in your accreditation application.
                </p>
            </div>
        </div>

        @if($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                <ul class="list-disc pl-4 space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        @if($finalisedWorkplans->isEmpty())
            <div class="rounded-2xl border border-warning-200 bg-warning-50 p-8 dark:border-warning-500/30 dark:bg-warning-500/10 text-center">
                <p class="text-sm font-semibold text-warning-700 dark:text-warning-400">No finalized workplans found for the current semester.</p>
                <p class="mt-1 text-xs text-warning-600 dark:text-warning-400">Please ensure your organization has a finalized workplan before submitting an accreditation application.</p>
            </div>
        @else

            <form action="{{ route('accreditation.wizard.step1.save') }}" method="POST" class="space-y-6">
                @csrf

                <div x-data="{ selectedWorkplan: {{ $defaultWorkplanId ?? 'null' }} }" class="space-y-4">

                    @if($finalisedWorkplans->count() > 1)
                        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                            <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Select Workplan</h3>
                            <div class="space-y-2">
                                @foreach($finalisedWorkplans as $wp)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 p-3 transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-white/[0.02]">
                                        <input type="radio" name="workplan_id" value="{{ $wp->workplan_id }}"
                                               x-model.number="selectedWorkplan"
                                               {{ $wp->workplan_id == $defaultWorkplanId ? 'checked' : '' }}
                                               class="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-400" />
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ $wp->semester?->name ?? 'Workplan #'.$wp->workplan_id }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <input type="hidden" name="workplan_id" value="{{ $finalisedWorkplans->first()->workplan_id }}" />
                    @endif

                    {{-- Activities table --}}
                    @foreach($finalisedWorkplans as $wp)
                        <div x-show="selectedWorkplan === {{ $wp->workplan_id }}" class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] overflow-hidden">
                            <div class="border-b border-gray-100 dark:border-gray-800 px-5 py-4">
                                <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">
                                    Approved Activities — {{ $wp->semester?->name ?? 'Semester' }}
                                </h3>
                            </div>
                            @php $activities = $workplanActivities[$wp->workplan_id] ?? []; @endphp
                            @if(empty($activities))
                                <p class="px-5 py-6 text-sm text-gray-400 dark:text-gray-500 italic">No approved activities in this workplan.</p>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-sm">
                                        <thead>
                                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">#</th>
                                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Activity</th>
                                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Date</th>
                                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Resources</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                            @foreach($activities as $i => $act)
                                                <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                                    <td class="px-5 py-3 text-gray-400 dark:text-gray-500">{{ $i + 1 }}</td>
                                                    <td class="px-5 py-3 text-gray-800 dark:text-white/90 font-medium">{{ $act['title'] }}</td>
                                                    <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $act['date'] }}</td>
                                                    <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $act['resources'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="flex justify-end">
                            <button type="submit"
                                    class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                                Continue: Signature →
                            </button>
                        </div>
                    </div>

                </div>
            </form>

        @endif
    </div>
@endsection
