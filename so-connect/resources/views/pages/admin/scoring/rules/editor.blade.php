@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Scoring Trigger" />

    <div class="space-y-6"
        x-data="tallyEditor({
            saveUrl: '{{ route('admin.scoring.rules.update', $criterion) }}',
            csrf: '{{ csrf_token() }}',
            variables: {{ Js::from($variables) }},
            trigger: {{ Js::from($trigger) }},
            enabled: {{ ($rule?->enabled ?? true) ? 'true' : 'false' }},
        })">

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $criterion->label }}</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $categories[$criterion->category_key]['label'] ?? $criterion->category_key }}
                        · {{ $criterion->weight }} point(s) per instance
                        · Describe when this criterion earns an instance.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input type="checkbox" x-model="enabled" class="h-4 w-4 rounded border-gray-300 text-brand-500" />
                        Trigger enabled
                    </label>
                    <a href="{{ route('admin.scoring.rules.index') }}"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                        Back
                    </a>
                    <button type="button" @click="save()" :disabled="saving"
                        class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:opacity-60">
                        <span x-show="!saving">Save trigger</span>
                        <span x-show="saving" x-cloak>Saving…</span>
                    </button>
                </div>
            </div>
            <p x-show="error" x-cloak x-text="error"
                class="mt-3 rounded-lg bg-error-50 px-3 py-2 text-xs font-medium text-error-600 dark:bg-error-500/10"></p>
        </div>

        {{-- WHEN --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex flex-wrap items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <span class="font-semibold">When</span>
                <select x-model="source" class="h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="form_submission">a submission of</option>
                    <option value="event_plan">an event plan</option>
                </select>
                <select x-show="source === 'form_submission'" x-model="formId"
                    class="h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="">— choose a form —</option>
                    <template x-for="f in variables.forms" :key="f.id">
                        <option :value="f.id" x-text="f.name"></option>
                    </template>
                </select>
                <span>is <strong>approved</strong>,</span>
            </div>
        </div>

        {{-- IF (condition groups) --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <p class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">If</p>

            {{-- root group --}}
            <div class="space-y-3 rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                <div class="flex items-center gap-2 text-xs text-gray-500">
                    Match
                    <select x-model="root.op" class="h-8 rounded-lg border border-gray-300 bg-transparent px-2 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="and">ALL</option>
                        <option value="or">ANY</option>
                    </select>
                    of these (leave empty to always tally):
                </div>

                <template x-for="(child, ci) in root.children" :key="ci">
                    <div>
                        {{-- a condition row --}}
                        <template x-if="child.kind === 'row'">
                            <div class="flex flex-wrap items-center gap-2">
                                <label class="flex items-center gap-1 text-xs text-gray-500">
                                    <input type="checkbox" x-model="child.not" class="h-3.5 w-3.5 rounded border-gray-300 text-brand-500" /> not
                                </label>
                                <select x-model="child.var" class="h-9 min-w-[10rem] rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                    <option value="">— variable —</option>
                                    <template x-for="g in variableGroups" :key="g.label">
                                        <optgroup :label="g.label">
                                            <template x-for="it in g.items" :key="it.value">
                                                <option :value="it.value" x-text="it.label"></option>
                                            </template>
                                        </optgroup>
                                    </template>
                                </select>
                                <select x-model="child.op" class="h-9 rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                    <option value="=">is</option>
                                    <option value="!=">is not</option>
                                    <option value=">">&gt;</option>
                                    <option value=">=">&ge;</option>
                                    <option value="<">&lt;</option>
                                    <option value="<=">&le;</option>
                                    <option value="contains">contains</option>
                                    <option value="not_empty">is filled in</option>
                                </select>
                                <template x-if="needsValue(child)">
                                    <span>
                                        <template x-if="optionsFor(child)">
                                            <select x-model="child.value" class="h-9 rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                                <option value="">— value —</option>
                                                <template x-for="opt in optionsFor(child)" :key="opt.value">
                                                    <option :value="opt.value" x-text="opt.label"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="!optionsFor(child)">
                                            <input type="text" x-model="child.value" placeholder="value"
                                                class="h-9 rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                        </template>
                                    </span>
                                </template>
                                <button type="button" @click="removeChild(root, ci)" class="px-1 text-error-400">✕</button>
                            </div>
                        </template>

                        {{-- a nested group (one level) --}}
                        <template x-if="child.kind === 'group'">
                            <div class="space-y-2 rounded-lg border border-dashed border-gray-300 p-2 dark:border-gray-700">
                                <div class="flex items-center gap-2 text-xs text-gray-500">
                                    <label class="flex items-center gap-1"><input type="checkbox" x-model="child.negated" class="h-3.5 w-3.5 rounded border-gray-300 text-brand-500" /> not</label>
                                    Match
                                    <select x-model="child.op" class="h-7 rounded-lg border border-gray-300 bg-transparent px-2 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                        <option value="and">ALL</option>
                                        <option value="or">ANY</option>
                                    </select>
                                    <button type="button" @click="removeChild(root, ci)" class="ml-auto px-1 text-error-400">remove group</button>
                                </div>
                                <template x-for="(gc, gci) in child.children" :key="gci">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <label class="flex items-center gap-1 text-xs text-gray-500"><input type="checkbox" x-model="gc.not" class="h-3.5 w-3.5 rounded border-gray-300 text-brand-500" /> not</label>
                                        <select x-model="gc.var" class="h-9 min-w-[9rem] rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            <option value="">— variable —</option>
                                            <template x-for="g in variableGroups" :key="g.label">
                                                <optgroup :label="g.label">
                                                    <template x-for="it in g.items" :key="it.value">
                                                        <option :value="it.value" x-text="it.label"></option>
                                                    </template>
                                                </optgroup>
                                            </template>
                                        </select>
                                        <select x-model="gc.op" class="h-9 rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            <option value="=">is</option><option value="!=">is not</option>
                                            <option value=">">&gt;</option><option value=">=">&ge;</option>
                                            <option value="<">&lt;</option><option value="<=">&le;</option>
                                            <option value="contains">contains</option><option value="not_empty">is filled in</option>
                                        </select>
                                        <template x-if="needsValue(gc)">
                                            <span>
                                                <template x-if="optionsFor(gc)">
                                                    <select x-model="gc.value" class="h-9 rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                                        <option value="">— value —</option>
                                                        <template x-for="opt in optionsFor(gc)" :key="opt.value"><option :value="opt.value" x-text="opt.label"></option></template>
                                                    </select>
                                                </template>
                                                <template x-if="!optionsFor(gc)">
                                                    <input type="text" x-model="gc.value" placeholder="value" class="h-9 rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                                </template>
                                            </span>
                                        </template>
                                        <button type="button" @click="removeChild(child, gci)" class="px-1 text-error-400">✕</button>
                                    </div>
                                </template>
                                <button type="button" @click="addRow(child)" class="text-xs text-brand-500">+ condition</button>
                            </div>
                        </template>
                    </div>
                </template>

                <div class="flex gap-3">
                    <button type="button" @click="addRow(root)" class="text-xs font-medium text-brand-500 hover:text-brand-600">+ condition</button>
                    <button type="button" @click="addGroup(root)" class="text-xs font-medium text-brand-500 hover:text-brand-600">+ group</button>
                </div>
            </div>
        </div>

        {{-- THEN --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex flex-wrap items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <span class="font-semibold">Then add</span>
                <select x-model="add.kind" class="h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="const">a fixed number of</option>
                    <option value="floor_div">one instance per amount of</option>
                    <option value="count_list">one instance per row of</option>
                </select>
                <template x-if="add.kind === 'const'">
                    <input type="number" min="1" max="1000" x-model.number="add.value"
                        class="h-9 w-24 rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                </template>
                <template x-if="add.kind !== 'const'">
                    <select x-model="add.var" class="h-9 min-w-[10rem] rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="">— variable —</option>
                        <template x-for="g in variableGroups" :key="g.label">
                            <optgroup :label="g.label">
                                <template x-for="it in g.items" :key="it.value"><option :value="it.value" x-text="it.label"></option></template>
                            </optgroup>
                        </template>
                    </select>
                </template>
                <template x-if="add.kind === 'floor_div'">
                    <span class="flex items-center gap-2">÷
                        <input type="number" min="1" x-model.number="add.divisor"
                            class="h-9 w-20 rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                    </span>
                </template>
                <span>instance(s).</span>
            </div>
        </div>

        <p class="text-xs text-gray-400 dark:text-gray-500">
            Only <strong>approved</strong> records in the scored semester are tallied — a rejected request
            sends the submitter back to the form and never counts.
        </p>
    </div>
@endsection
