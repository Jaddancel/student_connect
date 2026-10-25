@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Submitted Forms" />

    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-[#173f31] via-[#205640] to-[#2d6a4f] px-6 py-7 text-white shadow-sm sm:px-8">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-lime-200">Submission history</p>
            <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">All submitted forms in one place</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-emerald-50/85">Track event applications, membership requests, accreditation documents, and other forms you have submitted.</p>
                </div>
                <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm backdrop-blur-sm">
                    <span class="block text-xs uppercase tracking-wide text-emerald-100">Total submissions</span>
                    <strong class="mt-1 block text-2xl">{{ $counts['all'] }}</strong>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-5">
            <div class="flex flex-wrap gap-2" aria-label="Filter submitted forms by type">
                @foreach ($types as $key => $label)
                    <a href="{{ route('submitted-forms.index', $key === 'all' ? [] : ['type' => $key]) }}"
                        @class([
                            'inline-flex items-center gap-2 rounded-full border px-3.5 py-2 text-sm font-medium transition',
                            'border-[#2d6a4f] bg-[#2d6a4f] text-white shadow-sm' => $selectedType === $key,
                            'border-gray-200 bg-white text-gray-600 hover:border-[#2d6a4f] hover:text-[#2d6a4f] dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300' => $selectedType !== $key,
                        ])>
                        {{ $label }}
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs',
                            'bg-white/20 text-white' => $selectedType === $key,
                            'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => $selectedType !== $key,
                        ])>{{ $counts[$key] }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:px-6">
                <h2 class="font-semibold text-gray-900 dark:text-white">{{ $types[$selectedType] }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Event submissions show their related event. Non-event submissions show the process they belong to.</p>
            </div>

            @forelse ($submissions as $submission)
                @php
                    $statusClasses = match ($submission['status']['key']) {
                        'approved' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
                        'rejected' => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
                        'pending' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
                        default => 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-400',
                    };
                @endphp
                <article class="grid gap-4 border-b border-gray-100 px-5 py-5 last:border-b-0 dark:border-gray-800 sm:px-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1.25fr)_minmax(0,1fr)_auto] lg:items-center">
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $submission['form_name'] }}</p>
                        <p class="mt-1 text-xs text-gray-400">Submission #{{ $submission['id'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Type</p>
                        <p class="mt-1 text-sm font-medium text-gray-700 dark:text-gray-300">{{ $submission['type_label'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Event / Process</p>
                        <p class="mt-1 text-sm font-medium text-gray-800 dark:text-white/90">{{ $submission['context'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Organization</p>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $submission['organization'] }}</p>
                        <p class="mt-1 text-xs text-gray-400">{{ $submission['submitted_at']?->timezone(config('app.display_timezone'))->format('M d, Y · h:i A') }}</p>
                    </div>
                    <div class="flex flex-col items-start gap-2 lg:items-end">
                        <span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses }}">{{ $submission['status']['label'] }}</span>
                        @if ($submission['document_count'] > 0)
                            <a href="{{ route('documents.index') }}" class="text-xs font-semibold text-brand-500 hover:text-brand-600">View documents →</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="px-6 py-14 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-xl dark:bg-gray-800">□</div>
                    <h3 class="mt-4 font-semibold text-gray-900 dark:text-white">No submitted forms found</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Your submitted forms will appear here after you complete an application.</p>
                </div>
            @endforelse
        </section>

        @if ($submissions->hasPages())
            <div>{{ $submissions->links() }}</div>
        @endif

        <section aria-labelledby="workplan-heading">
            <div class="mb-3 px-1">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-600 dark:text-brand-400">Semester planning</p>
                <h2 id="workplan-heading" class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Organization workplans</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Review approved activities and finalize the semester plan when it is complete.</p>
            </div>
            <x-workplan-section
                :workplans="$workplans ?? []"
                :activeSemester="$activeSemester ?? null"
                :orgNames="$orgNames ?? []"
                :personNames="$personNames ?? []"
            />
        </section>
    </div>
@endsection
