@extends('layouts.app')

@php
    $pill = 'rounded-lg border border-brand-300 bg-brand-50 px-2 py-1 font-mono text-xs text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-brand-500/40 dark:bg-brand-500/10 dark:text-brand-300';
    $kw = 'font-mono text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $input = 'h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90';
@endphp

@section('content')
    <x-common.page-breadcrumb :pageTitle="$report ? 'Edit Report Template' : 'New Report Template'" />

    <style>
        .rt-preview { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 10px; }
        .rt-preview th, .rt-preview td { border: 1px solid rgb(229 231 235); padding: 4px 6px; text-align: left; vertical-align: top; }
        .rt-preview th { background: rgb(249 250 251); font-family: ui-monospace, monospace; font-weight: 600; }
        .dark .rt-preview th, .dark .rt-preview td { border-color: rgb(55 65 81); }
        .dark .rt-preview th { background: rgba(255, 255, 255, 0.04); }
        .rt-group-title { font-family: ui-monospace, monospace; font-size: 12px; font-weight: 600; margin: 8px 0 4px; }
        .rt-group-title span, .rt-empty { color: rgb(156 163 175); font-weight: 400; font-style: italic; }
    </style>

    <div
        x-data="reportTemplateBuilder({
            data: {{ Js::from($editorData) }},
            reportId: {{ $report?->getKey() ?? 'null' }},
            schemaUrl: '{{ route('admin.report-templates.schema') }}',
            previewUrl: '{{ route('admin.report-templates.preview') }}',
            parameterOptionsUrl: '{{ route('admin.report-templates.parameter-options') }}',
            draftSyncUrl: '{{ route('admin.report-templates.draft.sync') }}',
            storeUrl: '{{ route('admin.report-templates.store') }}',
            updateUrl: '{{ $report ? route('admin.report-templates.update', $report) : '' }}',
            printEnabled: {{ app(\App\Services\OnlyOfficeService::class)->enabled() ? 'true' : 'false' }},
            csrf: '{{ csrf_token() }}',
        })"
        @keydown.window.ctrl.s.prevent="save()"
        class="space-y-5">

        {{-- Top bar --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-palette-surface p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.report-templates.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400">← Back</a>
                <span x-show="message" x-text="message" class="text-sm font-medium text-success-600"></span>
                <span x-show="error" x-text="error" class="text-sm font-medium text-error-600"></span>
            </div>
            <button type="button" @click="save()" :disabled="saving"
                class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:opacity-60">
                <span x-show="!saving">Save template</span>
                <span x-show="saving">Saving…</span>
            </button>
        </div>

        <x-common.wizard-steps :steps="['Data tokens', 'Printed template', 'Details']" />

        {{-- ========================= STEP 1: DATA TOKENS ========================= --}}
        <div x-show="step === 1" class="grid grid-cols-12 gap-5">
            {{-- Token list --}}
            <div class="col-span-12 space-y-4 lg:col-span-4">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Token List</span>
                    <button type="button" @click="addValue()" title="Add a value token"
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 text-lg leading-none text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">+</button>
                    <button type="button" @click="addGroup()"
                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">New Group</button>
                </div>

                <div class="min-h-[18rem] space-y-2 rounded-2xl border border-gray-200 bg-palette-surface p-3 dark:border-gray-800 dark:bg-white/[0.03]">
                    <template x-for="entry in flatTokens" :key="entry.token.id">
                        <div class="flex items-stretch gap-2" :style="`padding-left: ${entry.depth * 1.25}rem`">
                            <span x-show="entry.depth > 0" class="w-0.5 flex-none rounded bg-brand-300 dark:bg-brand-500/40"></span>
                            <button type="button" @click="select(entry.token)"
                                class="flex min-w-0 flex-1 items-center gap-2 rounded-xl border px-3 py-2 text-left transition"
                                :class="[
                                    entry.token.kind === 'group'
                                        ? 'border-blue-light-200 bg-blue-light-50 dark:border-blue-light-500/30 dark:bg-blue-light-500/10'
                                        : 'border-error-100 bg-error-25 dark:border-error-500/20 dark:bg-error-500/5',
                                    selectedId === entry.token.id ? 'ring-2 ring-brand-400' : ''
                                ]">
                                <span class="flex-none font-mono text-[10px] font-semibold uppercase text-gray-500"
                                    x-text="entry.token.kind === 'group' ? 'Group' : (entry.token.mode === 'aggregate' ? entry.token.fn : (entry.token.mode === 'compute' ? 'Compute' : 'Value'))"></span>
                                <span class="min-w-0 flex-1 truncate font-mono text-sm text-gray-800 dark:text-white/90" x-text="entry.token.name || '(unnamed)'"></span>
                                <span x-show="entry.token.kind === 'group'" class="flex-none text-[10px] text-gray-400" x-text="(entry.token.children || []).length + ' items'"></span>
                            </button>
                            <div class="flex flex-none flex-col">
                                <button type="button" @click="move(entry.token, -1)" class="px-1 text-[10px] text-gray-400 hover:text-gray-700" title="Move up">▲</button>
                                <button type="button" @click="move(entry.token, 1)" class="px-1 text-[10px] text-gray-400 hover:text-gray-700" title="Move down">▼</button>
                            </div>
                        </div>
                    </template>
                    <p x-show="!flatTokens.length" class="px-2 py-8 text-center text-xs text-gray-400">
                        Use <strong>+</strong> for a single value, or <strong>New Group</strong> to repeat for every row of a table.
                    </p>
                </div>

                {{-- Parameters ("ask at generation") --}}
                <div class="space-y-2 rounded-2xl border border-gray-200 bg-palette-surface p-3 dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Parameters</span>
                        <button type="button" @click="addParameter()" class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">+ Parameter</button>
                    </div>
                    <p class="text-[11px] text-gray-400">Asked on the Reports page when generating, and usable in WHERE conditions.</p>
                    <template x-for="param in definition.parameters" :key="param.name + definition.parameters.indexOf(param)">
                        <button type="button" @click="selectParam(param)"
                            class="flex w-full items-center gap-2 rounded-xl border border-warning-200 bg-warning-25 px-3 py-2 text-left dark:border-warning-500/30 dark:bg-warning-500/5"
                            :class="selectedParam === param ? 'ring-2 ring-brand-400' : ''">
                            <span class="font-mono text-[10px] font-semibold uppercase text-gray-500">Ask</span>
                            <span class="truncate font-mono text-sm text-gray-800 dark:text-white/90" x-text="param.name"></span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="col-span-12 space-y-5 lg:col-span-8">
                {{-- ===================== Data Box ===================== --}}
                <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Data Box</h3>
                        <button type="button" x-show="selected" @click="removeSelected()" class="text-xs font-medium text-error-600 hover:text-error-700">Delete token</button>
                        <button type="button" x-show="selectedParam" @click="removeParam(selectedParam)" class="text-xs font-medium text-error-600 hover:text-error-700">Delete parameter</button>
                    </div>

                    <p x-show="!selected && !selectedParam" class="text-sm text-gray-400">Select a token or parameter to define its data.</p>
                    <p x-show="!schemaLoaded" class="text-sm text-gray-400">Loading the database catalog…</p>

                    {{-- ---------- GROUP ---------- --}}
                    <template x-if="selected && selected.kind === 'group'">
                        <div class="space-y-3 leading-9" x-data="{ get t() { return selected; } }">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="{{ $kw }}">Group</span>
                                <input type="text" x-model="t.name" @change="normalizeName(t)" class="{{ $pill }} w-36" />
                                <span class="{{ $kw }}">For every</span>
                                <template x-if="!parentOf(t)">
                                    <select x-model="t.entity" class="{{ $pill }}">
                                        <option value="">table…</option>
                                        <template x-for="tbl in schema.tables" :key="tbl.name">
                                            <option :value="tbl.name" x-text="tbl.name" :selected="tbl.name === t.entity"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="parentOf(t)">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <select x-model="t.relation" class="{{ $pill }}">
                                            <option value="">related rows…</option>
                                            <template x-for="rel in relations(contextTable(t), 'has_many')" :key="rel.name">
                                                <option :value="rel.name" x-text="rel.name" :selected="rel.name === t.relation"></option>
                                            </template>
                                        </select>
                                        <span class="{{ $kw }}">of</span>
                                        <span class="font-mono text-xs text-gray-600 dark:text-gray-300" x-text="tokenPath(parentOf(t))"></span>
                                    </span>
                                </template>
                            </div>

                            @include('pages.admin.report-templates.partials.where', ['owner' => 't'])

                            <div class="flex flex-wrap items-center gap-2">
                                <span class="{{ $kw }}">Order by</span>
                                <template x-for="(o, oi) in t.order" :key="oi">
                                    <span class="flex items-center gap-1">
                                        <select x-model="o.column" class="{{ $pill }}">
                                            <option value="">column…</option>
                                            <template x-for="col in columns(groupTable(t))" :key="col.name">
                                                <option :value="col.name" x-text="col.name" :selected="col.name === o.column"></option>
                                            </template>
                                        </select>
                                        <select x-model="o.dir" class="{{ $pill }}">
                                            <option value="asc">ASC</option>
                                            <option value="desc">DESC</option>
                                        </select>
                                        <button type="button" @click="t.order.splice(oi, 1)" class="text-xs text-gray-400 hover:text-error-500">✕</button>
                                    </span>
                                </template>
                                <button type="button" @click="addOrder(t)" class="text-xs font-medium text-brand-500">+ order</button>
                                <span class="{{ $kw }} ml-3">Limit</span>
                                <input type="number" min="1" x-model.number="t.limit" placeholder="all" class="{{ $pill }} w-20" />
                            </div>

                            <div class="rounded-lg bg-gray-50 px-3 py-1 text-xs text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                                <template x-for="child in t.children" :key="child.id">
                                    <button type="button" @click="select(child)" class="mr-2 font-mono hover:text-brand-600" x-text="(child.kind === 'group' ? 'GROUP ' : 'RETURN ') + child.name"></button>
                                </template>
                                <span x-show="!t.children.length">No items yet — select this group and use + or New Group to add rows' values.</span>
                            </div>
                            <div class="{{ $kw }}">End for</div>

                            @include('pages.admin.report-templates.partials.parent', ['owner' => 't'])
                        </div>
                    </template>

                    {{-- ---------- VALUE ---------- --}}
                    <template x-if="selected && selected.kind === 'value'">
                        <div class="space-y-3 leading-9" x-data="{ get t() { return selected; } }">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="{{ $kw }}">Return</span>
                                <select x-model="t.mode" class="{{ $pill }}">
                                    <option value="field">value</option>
                                    <option value="aggregate">aggregate</option>
                                    <option value="compute">compute</option>
                                </select>

                                {{-- Compute: arithmetic over the sibling value tokens --}}
                                <template x-if="t.mode === 'compute'">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <input type="text" x-model="t.expression" maxlength="200" spellcheck="false"
                                            placeholder="e.g. total_members * 100 / capacity" class="{{ $pill }} w-72" />
                                    </span>
                                </template>

                                {{-- Field path pills --}}
                                <template x-if="t.mode === 'field'">
                                    <span class="flex flex-wrap items-center gap-1">
                                        <template x-for="pill in pathPills(t)" :key="pill.index + pill.table">
                                            <span class="flex items-center gap-1">
                                                <span x-show="pill.index > 0" class="text-gray-400">.</span>
                                                <select class="{{ $pill }}" @change="setPathSegment(t, pill.index, $event.target.value)">
                                                    <option value="">choose…</option>
                                                    <optgroup label="Columns">
                                                        <template x-for="col in columns(pill.table)" :key="col.name">
                                                            <option :value="col.name" x-text="col.name" :selected="col.name === pill.value"></option>
                                                        </template>
                                                    </optgroup>
                                                    <optgroup label="Related record ▸">
                                                        <template x-for="rel in relations(pill.table, 'belongs_to')" :key="rel.name">
                                                            <option :value="rel.name" x-text="rel.name + ' ▸'" :selected="rel.name === pill.value"></option>
                                                        </template>
                                                    </optgroup>
                                                </select>
                                            </span>
                                        </template>
                                    </span>
                                </template>

                                {{-- Aggregate pills --}}
                                <template x-if="t.mode === 'aggregate'">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <select x-model="t.fn" class="{{ $pill }}">
                                            <template x-for="fn in schema.aggregates" :key="fn">
                                                <option :value="fn" x-text="fn.toUpperCase()" :selected="fn === t.fn"></option>
                                            </template>
                                        </select>
                                        <span class="{{ $kw }}">of</span>
                                        <template x-if="parentOf(t)">
                                            <select x-model="t.relation" class="{{ $pill }}">
                                                <option value="">related rows…</option>
                                                <template x-for="rel in relations(contextTable(t), 'has_many')" :key="rel.name">
                                                    <option :value="rel.name" x-text="rel.name" :selected="rel.name === t.relation"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <span x-show="!parentOf(t)" class="font-mono text-xs text-gray-500">rows</span>
                                        <template x-if="conditionTable(t)">
                                            <span class="flex items-center gap-1">
                                                <span class="text-gray-400">.</span>
                                                <select x-model="t.column" class="{{ $pill }}">
                                                    <option value="" x-text="t.fn === 'count' ? '*' : 'column…'"></option>
                                                    <template x-for="col in columns(conditionTable(t))" :key="col.name">
                                                        <option :value="col.name" x-text="col.name" :selected="col.name === t.column"></option>
                                                    </template>
                                                </select>
                                            </span>
                                        </template>
                                    </span>
                                </template>

                                <span class="{{ $kw }}" x-show="t.mode !== 'compute'">From</span>
                                <template x-if="!parentOf(t) && t.mode !== 'compute'">
                                    <select x-model="t.from" @change="t.path = []" class="{{ $pill }}">
                                        <option value="">table…</option>
                                        <template x-for="tbl in schema.tables" :key="tbl.name">
                                            <option :value="tbl.name" x-text="tbl.name" :selected="tbl.name === t.from"></option>
                                        </template>
                                    </select>
                                </template>
                                <span x-show="parentOf(t) && t.mode !== 'compute'" class="font-mono text-xs text-gray-600 dark:text-gray-300" x-text="contextTable(t) + ' (each ' + (parentOf(t)?.name || '') + ')'"></span>

                                <span class="{{ $kw }}">As</span>
                                <input type="text" x-model="t.name" @change="normalizeName(t)" class="{{ $pill }} w-36" />
                            </div>

                            <template x-if="t.mode !== 'compute' && (!parentOf(t) || t.mode === 'aggregate')">
                                <div>
                                    @include('pages.admin.report-templates.partials.where', ['owner' => 't'])
                                </div>
                            </template>

                            <template x-if="t.mode === 'compute'">
                                <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                    <span>Use</span>
                                    <template x-for="name in computeNames(t)" :key="name">
                                        <button type="button" @click="addToExpression(t, name)"
                                            class="rounded-md border border-gray-300 px-2 py-0.5 font-mono text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300" x-text="name"></button>
                                    </template>
                                    <template x-for="op in ['+', '-', '*', '/', '%', '(', ')']" :key="op">
                                        <button type="button" @click="addToExpression(t, op)"
                                            class="w-7 rounded-md border border-gray-300 py-0.5 font-mono text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300" x-text="op"></button>
                                    </template>
                                    <span x-show="!computeNames(t).length" class="text-gray-400">
                                        Add other value tokens beside this one to calculate with them.
                                    </span>
                                    <span class="basis-full text-gray-400">
                                        Numbers, the names above, <span class="font-mono">+ - * / %</span> and parentheses. Blank values count as 0; dividing by 0 prints the Else text.
                                    </span>
                                </div>
                            </template>

                            <div class="flex flex-wrap items-center gap-2">
                                <span class="{{ $kw }}">Format as</span>
                                <select x-model="t.format.type" class="{{ $pill }}">
                                    <template x-for="f in schema.formats" :key="f">
                                        <option :value="f" x-text="f" :selected="f === t.format.type"></option>
                                    </template>
                                </select>
                                <input type="text" x-show="['date','datetime','time','number'].includes(t.format.type)" x-model="t.format.pattern"
                                    :placeholder="t.format.type === 'number' ? 'decimals' : 'e.g. F j, Y'" class="{{ $pill }} w-28" />
                                <span class="{{ $kw }}">Else</span>
                                <input type="text" x-model="t.format.fallback" placeholder="(blank)" class="{{ $pill }} w-28" />
                            </div>

                            @include('pages.admin.report-templates.partials.parent', ['owner' => 't'])
                        </div>
                    </template>

                    {{-- ---------- PARAMETER ---------- --}}
                    <template x-if="selectedParam">
                        <div class="space-y-3 leading-9" x-data="{ get p() { return selectedParam; } }">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="{{ $kw }}">Ask</span>
                                <input type="text" x-model="p.name" @change="normalizeName(p)" class="{{ $pill }} w-32" />
                                <span class="{{ $kw }}">Label</span>
                                <input type="text" x-model="p.label" placeholder="shown on Reports page" class="{{ $pill }} w-48" />
                                <span class="{{ $kw }}">As</span>
                                <select x-model="p.type" @change="loadParamOptions(p)" class="{{ $pill }}">
                                    <template x-for="type in schema.paramTypes" :key="type">
                                        <option :value="type" x-text="type" :selected="type === p.type"></option>
                                    </template>
                                </select>
                                <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300">
                                    <input type="checkbox" x-model="p.required" /> required
                                </label>
                            </div>
                            <template x-if="p.type === 'entity'">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="{{ $kw }}">Pick from</span>
                                    <select x-model="p.entity" @change="p.display = []; loadParamOptions(p)" class="{{ $pill }}">
                                        <option value="">table…</option>
                                        <template x-for="tbl in schema.tables" :key="tbl.name">
                                            <option :value="tbl.name" x-text="tbl.name" :selected="tbl.name === p.entity"></option>
                                        </template>
                                    </select>
                                    <span class="{{ $kw }}">Showing</span>
                                    <template x-for="pill in paramDisplayPills(p)" :key="pill.index + pill.table">
                                        <span class="flex items-center gap-1">
                                            <span x-show="pill.index > 0" class="text-gray-400">.</span>
                                            <select class="{{ $pill }}" @change="setDisplaySegment(p, pill.index, $event.target.value)">
                                                <option value="">choose…</option>
                                                <optgroup label="Columns">
                                                    <template x-for="col in columns(pill.table)" :key="col.name">
                                                        <option :value="col.name" x-text="col.name" :selected="col.name === pill.value"></option>
                                                    </template>
                                                </optgroup>
                                                <optgroup label="Related record ▸">
                                                    <template x-for="rel in relations(pill.table, 'belongs_to')" :key="rel.name">
                                                        <option :value="rel.name" x-text="rel.name + ' ▸'" :selected="rel.name === pill.value"></option>
                                                    </template>
                                                </optgroup>
                                            </select>
                                        </span>
                                    </template>
                                </div>
                            </template>
                            <p class="text-xs text-gray-400">Use it in a WHERE condition by choosing <span class="font-mono">ask: <span x-text="p.name"></span></span> as the value. Blank optional parameters skip their condition.</p>
                        </div>
                    </template>
                </div>

                {{-- ===================== Data Preview ===================== --}}
                <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Data Preview</h3>
                        <div class="flex items-center gap-2">
                            <span x-show="preview.loading" class="text-xs text-gray-400">Running…</span>
                            <span x-show="preview.truncated" class="text-xs text-warning-600">Showing the first {{ (int) config('reports.preview_rows', 10) }} rows per group.</span>
                            <button type="button" @click="runPreview()" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Refresh</button>
                        </div>
                    </div>

                    <div x-show="definition.parameters.length" class="mb-3 flex flex-wrap gap-3">
                        <template x-for="param in definition.parameters" :key="'pv-' + param.name">
                            <label class="text-xs text-gray-500">
                                <span x-text="param.label || param.name"></span>
                                <template x-if="param.type === 'entity'">
                                    <select x-model="preview.params[param.name]" @change="runPreview()" class="ml-1 h-8 rounded-lg border border-gray-300 bg-transparent px-2 text-xs dark:border-gray-700">
                                        <option value="">(any)</option>
                                        <template x-for="opt in (paramOptions[param.name] || [])" :key="opt.value">
                                            <option :value="opt.value" x-text="opt.label"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="param.type !== 'entity'">
                                    <input :type="param.type === 'date' ? 'date' : (param.type === 'number' ? 'number' : 'text')" x-model="preview.params[param.name]" @change="runPreview()"
                                        class="ml-1 h-8 rounded-lg border border-gray-300 bg-transparent px-2 text-xs dark:border-gray-700" />
                                </template>
                            </label>
                        </template>
                    </div>

                    <template x-if="preview.errors.length">
                        <ul class="mb-3 list-disc space-y-1 rounded-lg bg-error-50 py-2 pl-6 pr-3 text-xs text-error-700 dark:bg-error-500/10 dark:text-error-400">
                            <template x-for="(e, ei) in preview.errors" :key="ei"><li x-text="e"></li></template>
                        </ul>
                    </template>
                    <div class="max-h-[28rem] overflow-auto text-gray-700 dark:text-gray-300" x-html="preview.html"></div>
                    <p x-show="!preview.html && !preview.errors.length" class="text-sm text-gray-400">Define a token to preview its data.</p>
                </div>
            </div>
        </div>

        {{-- ===================== STEP 2: PRINTED TEMPLATE ===================== --}}
        <div x-show="step === 2" x-cloak>
            <div class="mb-3 rounded-xl border border-gray-200 bg-palette-surface px-4 py-3 text-xs text-gray-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-400">
                Insert tokens from the editor's token panel. Each repeating group offers two buttons.
                <strong>New repeating block</strong> inserts
                <span class="font-mono">@{{#group}} … @{{/group}}</span> — everything between repeats for every row;
                inside it, <span class="font-mono">@{{group.value}}</span> prints that row's value.
                <strong>New table</strong> inserts a table whose row repeats per row (<span class="font-mono">@{{group.value#}}</span>).
            </div>
            <x-form-builder.onlyoffice-template
                :printEnabled="app(\App\Services\OnlyOfficeService::class)->enabled()" />
        </div>

        {{-- ========================= STEP 3: DETAILS ========================= --}}
        <div x-show="step === 3" x-cloak class="grid grid-cols-12 gap-5">
            <div class="col-span-12 lg:col-span-8 lg:col-start-3">
                <div class="space-y-4 rounded-2xl border border-gray-200 bg-palette-surface p-6 dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Report details</h3>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Report name</label>
                        <input type="text" x-model="name" class="{{ $input }}" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Description</label>
                        <textarea x-model="description" rows="3" placeholder="What does this report show?"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Icon</label>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="icon = ''" title="Default"
                                class="flex h-10 w-10 items-center justify-center rounded-lg border transition hover:border-brand-400 [&_svg]:h-5 [&_svg]:w-5"
                                :class="icon === '' ? 'border-brand-500 text-brand-600 ring-1 ring-brand-300' : 'border-gray-200 text-gray-500 dark:border-gray-700'">
                                {!! \App\Helpers\MenuHelper::getIconSvg('pages') !!}
                            </button>
                            @foreach ($iconChoices as $iconName => $svg)
                                <button type="button" @click="icon = @js($iconName)" title="{{ ucfirst(str_replace('-', ' ', $iconName)) }}"
                                    class="flex h-10 w-10 items-center justify-center rounded-lg border transition hover:border-brand-400 [&_svg]:h-5 [&_svg]:w-5"
                                    :class="icon === @js($iconName) ? 'border-brand-500 text-brand-600 ring-1 ring-brand-300' : 'border-gray-200 text-gray-500 dark:border-gray-700'">
                                    {!! $svg !!}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Available to:</label>
                        <select x-model="audience" class="{{ $input }}">
                            @foreach ($audiences as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Who can generate this report from the Reports page. Officers can pick from, and see data of, every organization the report covers.</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" x-model="is_active" /> Active — listed on the Reports page
                    </label>
                </div>
            </div>
        </div>

        {{-- Wizard footer --}}
        <div class="flex items-center justify-between rounded-2xl border border-gray-200 bg-palette-surface p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <button type="button" @click="prevStep()" x-show="step > 1"
                class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">← Back</button>
            <span x-show="step === 1"></span>
            <div class="flex items-center gap-2">
                <button type="button" @click="nextStep()" x-show="step < 3"
                    class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">Next →</button>
                <button type="button" @click="save()" x-show="step === 3" :disabled="saving"
                    class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:opacity-60">
                    <span x-show="!saving">Finish</span>
                    <span x-show="saving">Saving…</span>
                </button>
            </div>
        </div>
    </div>
@endsection
