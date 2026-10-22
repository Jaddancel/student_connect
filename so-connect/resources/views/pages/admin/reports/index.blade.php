@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Reports" />

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

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-500 dark:text-gray-400">Reports are generated from the active report templates.</p>
            @if ((int) auth()->user()->user_type === 2)
                <a href="{{ route('admin.report-templates.index') }}" class="text-sm font-medium text-brand-500 hover:text-brand-600">Manage report templates →</a>
            @endif
        </div>

        @if ($reports->isEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white px-6 py-16 text-center text-sm text-gray-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-400">
                No reports are available to you yet.
            </div>
        @else
            @php
                $inputClass = 'dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90';
            @endphp

            {{-- Filter panel: opens above the cards once a report is selected,
                 with that report's "ask at generation" parameters. --}}
            <div x-show="hasSelection" x-collapse x-cloak>
                @foreach ($reports as $entry)
                    @continue (! $entry['valid'])
                    @php
                        $report = $entry['model'];
                        // Organization type narrows the organization search (see ReportController::groupOrganizationsByType).
                        $orgTypeScope = collect($entry['parameters'])->contains(fn ($p) => ($p['grouped_by'] ?? null) === 'organization_type');
                        $orgTypeSelected = collect($entry['parameters'])->firstWhere('name', 'organization_type')['selected'] ?? '';
                    @endphp
                    <form method="GET" action="{{ route('reports.generate', $report) }}" data-report="{{ $report->name }}"
                        data-report-filter="{{ $report->getKey() }}" x-show="isSelected({{ $report->getKey() }})"
                        @submit.prevent="generate($event)"
                        @if ($orgTypeScope) x-data="adminReportOrgScope({ orgType: @js($orgTypeSelected) })" @endif
                        class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Filter</h3>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $report->name }}</p>
                            </div>
                            <button type="button" @click="clearSelection()" aria-label="Close filter"
                                class="flex h-8 w-8 flex-none items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/[0.06]">✕</button>
                        </div>

                        @if ($entry['notice'])
                            <p class="mt-4 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:bg-white/[0.03] dark:text-gray-400">{{ $entry['notice'] }}</p>
                        @endif

                        @if ($entry['parameters'] !== [])
                            <div class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 xl:grid-cols-4">
                                @foreach ($entry['parameters'] as $parameter)
                                    @php $fieldId = 'report-'.$report->getKey().'-'.$parameter['name']; @endphp
                                    <div>
                                        <label for="{{ $fieldId }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                            {{ $parameter['label'] }}@if ($parameter['required']) <span class="text-error-500">*</span>@endif
                                        </label>
                                        @if ($parameter['type'] === 'entity' && $parameter['searchable'])
                                            <div x-data="searchSelectField({ options: {{ \App\Support\JsLiteral::from($parameter['options'] ?? []) }}, selected: @js($parameter['selected']) })"
                                                @if (($parameter['grouped_by'] ?? null) === 'organization_type' && $orgTypeScope) x-effect="setGroup(orgType)" @endif
                                                class="relative">
                                                <input type="hidden" name="params[{{ $parameter['name'] }}]" :value="selected" />
                                                <div class="relative">
                                                    <input id="{{ $fieldId }}" type="text" x-model="query" @focus="open = true" @click="open = true"
                                                        @input="onInput()" @keydown.escape="open = false"
                                                        placeholder="{{ $parameter['required'] ? 'Search…' : 'All — type to search…' }}" autocomplete="off"
                                                        class="{{ $inputClass }} pr-8" />
                                                    <button type="button" x-show="selected" x-cloak @click="clear()" aria-label="Clear {{ $parameter['label'] }}"
                                                        class="absolute inset-y-0 right-2 my-auto h-5 text-gray-400 hover:text-gray-600">✕</button>
                                                </div>
                                                <div x-show="open" x-cloak @click.outside="open = false"
                                                    class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900">
                                                    <template x-if="!filtered.length">
                                                        <p class="px-3 py-2 text-xs text-gray-400">No matches.</p>
                                                    </template>
                                                    <template x-for="opt in filtered" :key="opt.value">
                                                        <button type="button" @click="choose(opt)"
                                                            class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-brand-50 dark:text-gray-200 dark:hover:bg-white/[0.06]"
                                                            x-text="opt.label"></button>
                                                    </template>
                                                </div>
                                            </div>
                                        @elseif ($parameter['type'] === 'entity')
                                            <select id="{{ $fieldId }}" name="params[{{ $parameter['name'] }}]" @required($parameter['required'])
                                                @if ($orgTypeScope && $parameter['name'] === 'organization_type') x-model="orgType" @endif
                                                class="{{ $inputClass }}">
                                                <option value="">{{ $parameter['required'] ? 'Choose…' : 'All' }}</option>
                                                @foreach ($parameter['options'] ?? [] as $option)
                                                    <option value="{{ $option['value'] }}" @selected($option['value'] === $parameter['selected'])>{{ $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input id="{{ $fieldId }}" name="params[{{ $parameter['name'] }}]" value="{{ $parameter['selected'] }}" @required($parameter['required'])
                                                type="{{ $parameter['type'] === 'number' ? 'number' : ($parameter['type'] === 'date' ? 'date' : 'text') }}"
                                                class="{{ $inputClass }}" />
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @elseif (! $entry['notice'])
                            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">This report has no filters.</p>
                        @endif

                        <div class="mt-5 flex flex-wrap gap-2">
                            <button type="submit" name="format" value="pdf"
                                class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                                Download PDF
                            </button>
                            <button type="submit" name="format" value="docx"
                                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                                Download Word
                            </button>
                        </div>
                    </form>
                @endforeach
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($reports as $entry)
                    @php $report = $entry['model']; @endphp
                    @if ($entry['valid'])
                        <button type="button" data-report-card="{{ $report->getKey() }}"
                            @click="select({{ $report->getKey() }})" :aria-pressed="isSelected({{ $report->getKey() }})"
                            :class="isSelected({{ $report->getKey() }}) ? 'border-brand-500 ring-2 ring-brand-500/20 dark:border-brand-500' : 'border-gray-200 hover:border-brand-300 dark:border-gray-800 dark:hover:border-brand-500/50'"
                            class="flex w-full items-start gap-3 rounded-2xl border bg-white p-6 text-left transition dark:bg-white/[0.03]">
                    @else
                        <div class="flex items-start gap-3 rounded-2xl border border-gray-200 bg-white p-6 opacity-75 dark:border-gray-800 dark:bg-white/[0.03]">
                    @endif
                            <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 [&_svg]:h-5 [&_svg]:w-5">
                                {!! \App\Helpers\MenuHelper::getIconSvg($report->icon ?: 'pages') !!}
                            </span>
                            <span class="block min-w-0">
                                <span class="block text-base font-semibold text-gray-800 dark:text-white/90">{{ $report->name }}</span>
                                @if ($report->description)
                                    <span class="mt-1 block text-sm text-gray-500 dark:text-gray-400">{{ $report->description }}</span>
                                @endif
                                @unless ($entry['valid'])
                                    <span class="mt-3 block rounded-lg bg-error-50 px-3 py-2 text-xs font-medium text-error-600 dark:bg-error-500/10 dark:text-error-400">
                                        This template refers to data that no longer exists. Edit it to fix its tokens.
                                    </span>
                                @endunless
                            </span>
                    @if ($entry['valid'])
                        </button>
                    @else
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
@endsection
