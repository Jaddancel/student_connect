@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Report Generation" />

    <div class="space-y-6"
        x-data="adminReportGenerator">

        {{-- Generation progress / error modal --}}
        <div x-show="busy" x-cloak x-transition.opacity
            class="fixed inset-0 z-[100000] flex items-center justify-center bg-gray-900/50 p-4 backdrop-blur-sm"
            role="dialog" aria-modal="true" aria-live="polite" @keydown.escape.window="dismissOnEscape()">
            <div class="w-full max-w-sm rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-xl dark:border-gray-800 dark:bg-gray-900">
                <template x-if="!error">
                    <div>
                        <div class="mx-auto h-14 w-14 animate-spin rounded-full border-4 border-brand-200 border-t-brand-500" aria-hidden="true"></div>
                        <h3 class="mt-5 text-base font-semibold text-gray-800 dark:text-white/90">Generating report…</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="label"></p>
                        <p class="mt-3 text-xs text-gray-400">This can take a few moments. Your download will start automatically.</p>
                    </div>
                </template>
                <template x-if="error">
                    <div>
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-error-50 text-error-600 dark:bg-error-500/10">
                            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                        </div>
                        <h3 class="mt-5 text-base font-semibold text-gray-800 dark:text-white/90">Report could not be generated</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400" x-text="error"></p>
                        <button type="button" @click="closeError()"
                            class="mt-5 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">Close</button>
                    </div>
                </template>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <section class="relative z-30 overflow-visible rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-wrap items-center justify-between gap-5 px-5 py-6 sm:px-7">
                <div class="max-w-2xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-500">Document center</p>
                    <h1 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">Generate reports by purpose</h1>
                    <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">Choose a category, configure only the required information, then download the report as PDF or Word.</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-gray-50 px-4 py-3 text-center dark:bg-white/[0.04]">
                        <span class="block text-2xl font-semibold text-gray-900 dark:text-white">{{ $totalReportCount }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">available reports</span>
                    </div>
                    @if ((int) auth()->user()->user_type === 2)
                        <a href="{{ route('admin.report-templates.index') }}" class="rounded-xl border border-gray-300 px-4 py-3 text-sm font-semibold text-gray-700 transition hover:border-brand-300 hover:text-brand-600 dark:border-gray-700 dark:text-gray-300">Manage templates</a>
                    @endif
                </div>
            </div>

            <nav class="border-t border-gray-100 px-4 py-3 dark:border-gray-800" aria-label="Report categories">
                <div class="flex gap-2 overflow-x-auto pb-1">
                    <a href="{{ route('reports.index') }}" @class([
                        'inline-flex shrink-0 items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold transition',
                        'bg-brand-500 text-white shadow-sm shadow-brand-500/20' => ! request()->filled('category'),
                        'bg-gray-50 text-gray-600 hover:bg-gray-100 dark:bg-white/[0.04] dark:text-gray-300' => request()->filled('category'),
                    ])>
                        All <span class="rounded-full bg-white/15 px-2 py-0.5 text-[11px] dark:bg-black/10">{{ $totalReportCount }}</span>
                    </a>
                    @foreach ($categories as $category)
                        @php $count = (int) ($categoryCounts[(string) $category->getKey()] ?? 0); @endphp
                        <a href="{{ route('reports.index', ['category' => $category->getKey()]) }}" @class([
                            'inline-flex shrink-0 items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold transition',
                            'bg-brand-500 text-white' => (int) request('category') === (int) $category->getKey(),
                            'bg-gray-50 text-gray-600 hover:bg-gray-100 dark:bg-white/[0.04] dark:text-gray-300 dark:hover:bg-white/[0.08]' => (int) request('category') !== (int) $category->getKey(),
                        ])>
                            <span class="h-2 w-2 rounded-full bg-current opacity-60"></span>
                            {{ $category->name }}
                            <span class="rounded-full bg-black/5 px-2 py-0.5 text-[11px] dark:bg-white/10">{{ $count }}</span>
                        </a>
                    @endforeach
                </div>
            </nav>

            <div class="relative z-40 rounded-b-2xl border-t border-gray-100 bg-brand-25/50 px-5 py-5 dark:border-gray-800 dark:bg-brand-500/[0.03] sm:px-7">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Report filters</h2>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Set these once. The selected values are applied automatically to every compatible report below.</p>
                    </div>
                    <button type="button" @click="resetFilters"
                        class="text-xs font-semibold text-brand-600 transition hover:text-brand-700 dark:text-brand-400">Reset filters</button>
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Organization</label>
                        <x-common.predictive-select x-model="filters.organization" :name="null" selected="" :required="false" placeholder="All organizations" search-placeholder="Search organization…" :options="$scopeOptions['organization']" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">School year</label>
                        <x-common.predictive-select x-model="filters.schoolYear" :name="null" selected="__all__" clear-value="__all__" :required="false" placeholder="All school years" search-placeholder="Search school year…" :options="$scopeOptions['school_year']" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Semester</label>
                        <x-common.predictive-select x-model="filters.semester" :name="null" selected="__all__" clear-value="__all__" :required="false" placeholder="All semesters" search-placeholder="Search semester…" :options="$scopeOptions['semester']" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Organization status</label>
                        <x-common.predictive-select x-model="filters.organizationStatus" :name="null" selected="" :required="false" placeholder="All statuses" search-placeholder="Search status…" :options="$scopeOptions['organization_status']" />
                    </div>
                </div>
            </div>
        </section>

        @if ($reports->isEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white px-6 py-16 text-center text-sm text-gray-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-400">
                @if (request()->filled('category'))
                    No report template has been assigned to this category yet.
                    <a href="{{ route('reports.index') }}" class="ml-1 font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">View all reports</a>
                @else
                    No reports are available to you yet.
                @endif
            </div>
        @else
            <div class="space-y-9">
                @foreach ($reportGroups as $group)
                    @php $tone = ['icon' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300', 'line' => 'bg-brand-500']; @endphp
                    <section id="{{ $group['key'] }}" class="scroll-mt-24">
                        <div class="mb-4 flex items-start gap-3">
                            <span class="{{ $tone['icon'] }} flex h-11 w-11 shrink-0 items-center justify-center rounded-xl [&_svg]:h-5 [&_svg]:w-5">{!! \App\Helpers\MenuHelper::getIconSvg($group['icon']) !!}</span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $group['name'] }}</h2>
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-400">{{ $group['reports']->count() }} {{ \Illuminate\Support\Str::plural('report', $group['reports']->count()) }}</span>
                                </div>
                                @if ($group['description'])<p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $group['description'] }}</p>@endif
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            @foreach ($group['reports'] as $entry)
                                @php
                                    $report = $entry['model'];
                                    $hasLocalParameters = collect($entry['parameters'])->contains(fn ($parameter) =>
                                        $parameter['type'] !== 'entity'
                                        || ! in_array($parameter['entity'] ?? null, ['organizations', 'school_years', 'semesters'], true)
                                    );
                                @endphp
                                <article class="relative overflow-visible rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:border-brand-200 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-brand-500/30">
                                    <span class="{{ $tone['line'] }} absolute inset-y-3 left-0 w-1 rounded-r-full"></span>
                                    <div class="p-5 pl-6">
                                        <div class="flex items-start gap-3">
                                            <span class="{{ $tone['icon'] }} flex h-10 w-10 flex-none items-center justify-center rounded-xl [&_svg]:h-5 [&_svg]:w-5">{!! \App\Helpers\MenuHelper::getIconSvg($report->icon ?: $group['icon']) !!}</span>
                                            <div class="min-w-0 flex-1">
                                                <h3 class="text-base font-semibold text-gray-900 dark:text-white/90">{{ $report->name }}</h3>
                                                @if ($report->description)<p class="mt-1 text-sm leading-5 text-gray-500 dark:text-gray-400">{{ $report->description }}</p>@endif
                                            </div>
                                        </div>

                                        @if (! $entry['valid'])
                                            <p class="mt-4 rounded-xl bg-error-50 px-3 py-2 text-xs font-medium text-error-600 dark:bg-error-500/10 dark:text-error-400">This template refers to data that no longer exists. Edit it to fix its tokens.</p>
                                        @else
                                            <details class="group mt-4 border-t border-gray-100 pt-4 dark:border-gray-800" @if ($group['reports']->count() === 1) open @endif>
                                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-brand-600 marker:content-none dark:text-brand-400">
                                                    <span>{{ $hasLocalParameters ? 'Configure report' : 'Download report' }}</span>
                                                    <svg class="h-4 w-4 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" /></svg>
                                                </summary>
                                                <form method="GET" action="{{ route('reports.generate', $report) }}" data-report="{{ $report->name }}" @submit.prevent="generate($event)" class="mt-4 space-y-3">
                                                    @if ($entry['notice'])<p class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:bg-white/[0.03] dark:text-gray-400">{{ $entry['notice'] }}</p>@endif
                                                    @if (in_array('organization', $entry['scopes'], true))
                                                        <input type="hidden" name="scope[organization]" :value="filters.organization">
                                                    @endif
                                                    @if (in_array('organization_status', $entry['scopes'], true))
                                                        <input type="hidden" name="scope[organization_status]" :value="filters.organizationStatus">
                                                    @endif
                                                    @foreach ($entry['parameters'] as $parameter)
                                                        @php
                                                            $globalFilter = $parameter['type'] === 'entity'
                                                                ? match ($parameter['entity'] ?? null) {
                                                                    'organizations' => 'organization',
                                                                    'school_years' => 'schoolYear',
                                                                    'semesters' => 'semester',
                                                                    default => null,
                                                                }
                                                                : null;
                                                        @endphp
                                                        @if ($globalFilter)
                                                            <input type="hidden" name="params[{{ $parameter['name'] }}]" :value="filters.{{ $globalFilter }}">
                                                        @else
                                                            <div>
                                                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">{{ $parameter['label'] }}@if ($parameter['required']) <span class="text-error-500">*</span>@endif</label>
                                                                @if ($parameter['type'] === 'entity')
                                                                    <x-common.predictive-select :name="'params['.$parameter['name'].']'" :selected="$parameter['selected']" :required="$parameter['required']" :placeholder="$parameter['required'] ? 'Choose…' : 'Select all'" search-placeholder="Type to search…" :options="$parameter['options'] ?? []" />
                                                                @else
                                                                    <input name="params[{{ $parameter['name'] }}]" @required($parameter['required']) type="{{ $parameter['type'] === 'number' ? 'number' : ($parameter['type'] === 'date' ? 'date' : 'text') }}" class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90" />
                                                                @endif
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                    <div class="flex flex-wrap gap-2 pt-1">
                                                        <button type="submit" name="format" value="pdf" class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">Download PDF</button>
                                                        <button type="submit" name="format" value="docx" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Download Word</button>
                                                    </div>
                                                </form>
                                            </details>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
@endsection
