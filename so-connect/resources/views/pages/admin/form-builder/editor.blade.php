@extends('layouts.app')

@php
    // Universal-field options for the "Autofill" mapping. Grouped so the
    // <select> can render <optgroup>s that mirror App\Support\UniversalField.
    // The `system` source (current date/time/year, school year) is excluded
    // here — those options are type-dependent, so they're rendered separately
    // by Alpine's systemAutofillOptions() instead of this static list.
    $universalGroups = \App\Support\UniversalField::groupedBySource('profile')
        + \App\Support\UniversalField::groupedBySource('org');
    $universalGroupLabels = [
        'name' => 'Name', 'contact' => 'Contact', 'personal' => 'Personal',
        'academic' => 'Academic', 'id' => 'Identity', 'organization' => 'Organization',
    ];
    // Registered dynamic option sources for the select/search "From registered
    // entries" picker, as a flat list Alpine's x-for can iterate.
    $optionSources = collect(\App\Forms\OptionSource::catalog())
        ->map(fn ($meta, $key) => ['key' => $key, 'label' => $meta['label'], 'searchable' => (bool) $meta['searchable']])
        ->values()
        ->all();
@endphp

@section('content')
    <x-common.page-breadcrumb :pageTitle="$form ? 'Edit Form' : 'New Form'" />

    <div
        x-data="formBuilder({
            catalog: {{ Js::from($fieldCatalog) }},
            optionSources: {{ Js::from($optionSources) }},
            data: {{ Js::from($editorData) }},
            isEdit: {{ $form ? 'true' : 'false' }},
            storeUrl: '{{ route('admin.form-builder.store') }}',
            updateUrl: '{{ $form ? route('admin.form-builder.update', $form) : '' }}',
            uploadUrl: '{{ route('admin.form-builder.upload-asset') }}',
            csrf: '{{ csrf_token() }}',
        })"
        @keydown.window.ctrl.s.prevent="save()"
        class="space-y-5">

        {{-- Top bar --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-palette-surface p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.form-builder.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400">← Back</a>
                <span x-show="message" x-text="message" class="text-sm font-medium text-success-600"></span>
                <span x-show="error" x-text="error" class="text-sm font-medium text-error-600"></span>
            </div>
            <div class="flex items-center gap-2">
                @if ($form)
                    <a href="{{ route('admin.form-builder.preview', $form) }}" target="_blank"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Preview</a>
                @endif
                <button type="button" @click="save()" :disabled="saving"
                    class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:opacity-60">
                    <span x-show="!saving">Save form</span>
                    <span x-show="saving">Saving…</span>
                </button>
            </div>
        </div>

        {{-- Stepper --}}
        <x-common.wizard-steps :steps="['Online form', 'Printed template', 'Details']" />

        {{-- ========================= STEP 1: ONLINE FORM ========================= --}}
        <div x-show="step === 1" class="grid grid-cols-12 gap-5 lg:items-start">
            {{-- LEFT: palette + header designer (floats as the canvas scrolls) --}}
            <div class="col-span-12 space-y-5 lg:col-span-3">
                <div class="rounded-2xl border border-gray-200 bg-palette-surface p-4 dark:border-gray-800 dark:bg-white/[0.03] lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto">
                    <h3 class="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Field palette</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <template x-for="(meta, type) in fieldPalette" :key="type">
                            <button type="button" @click="addField(type)"
                                class="rounded-lg border border-gray-200 px-3 py-2 text-left text-xs font-medium text-gray-700 transition hover:border-brand-400 hover:bg-brand-50 dark:border-gray-700 dark:text-gray-300"
                                x-text="meta.label"></button>
                        </template>
                    </div>
                    {{-- Layout elements: presentation only, no submitted value --}}
                    <h3 class="mb-2 mt-4 text-sm font-semibold text-gray-800 dark:text-white/90">Layout</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <template x-for="(meta, type) in layoutPalette" :key="type">
                            <button type="button" @click="addField(type)"
                                class="rounded-lg border border-dashed border-gray-300 px-3 py-2 text-left text-xs font-medium text-gray-500 transition hover:border-brand-400 hover:bg-brand-50 dark:border-gray-700 dark:text-gray-400"
                                x-text="meta.label"></button>
                        </template>
                    </div>

                    {{-- Special fields: only shown for forms whose kit unlocks them --}}
                    <template x-if="hasSpecialPalette">
                        <div>
                            <h3 class="mb-2 mt-4 text-sm font-semibold text-brand-600 dark:text-brand-400">
                                {{ $kit ? \App\Forms\FieldKit::label($kit) : 'Special fields' }}
                            </h3>
                            <div class="grid grid-cols-2 gap-2">
                                <template x-for="(meta, type) in specialPalette" :key="type">
                                    <button type="button" @click="addField(type)"
                                        class="rounded-lg border border-brand-200 bg-brand-50/40 px-3 py-2 text-left text-xs font-medium text-brand-700 transition hover:border-brand-400 hover:bg-brand-50 dark:border-brand-500/30 dark:bg-brand-500/5 dark:text-brand-300"
                                        x-text="meta.label"></button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <button type="button" @click="addRow()"
                        class="mt-3 w-full rounded-lg border border-dashed border-gray-300 px-3 py-2 text-xs font-medium text-gray-500 transition hover:border-brand-400 hover:text-brand-600 dark:border-gray-700">
                        + Empty row
                    </button>
                </div>
            </div>

            {{-- CENTER: canvas --}}
            <div class="col-span-12 lg:col-span-6">
                <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] min-h-[400px]">
                    <template x-if="rows.length === 0">
                        <div class="py-16 text-center text-sm text-gray-400">Add fields from the palette to start building.</div>
                    </template>

                    <div class="space-y-4">
                        <template x-for="(row, rowIndex) in rows" :key="rowIndex">
                            <div class="rounded-xl border border-dashed border-gray-300 p-3 dark:border-gray-700">
                                <div class="mb-2 flex items-center justify-between">
                                    <div class="flex items-center gap-2 text-xs text-gray-400">
                                        {{-- ▲▼ reorder this row --}}
                                        <span class="flex items-center">
                                            <button type="button" @click="moveRow(rowIndex, -1)" :disabled="rowIndex === 0"
                                                class="px-1 leading-none text-gray-400 hover:text-brand-500 disabled:cursor-not-allowed disabled:opacity-30"
                                                title="Move row up">▲</button>
                                            <button type="button" @click="moveRow(rowIndex, 1)" :disabled="rowIndex === rows.length - 1"
                                                class="px-1 leading-none text-gray-400 hover:text-brand-500 disabled:cursor-not-allowed disabled:opacity-30"
                                                title="Move row down">▼</button>
                                        </span>
                                        <span>Columns:</span>
                                        <template x-for="n in 3" :key="n">
                                            <button type="button" @click="setColumnCount(rowIndex, n)"
                                                class="rounded px-1.5 py-0.5"
                                                :class="row.columns.length === n ? 'bg-brand-500 text-white' : 'border border-gray-300 text-gray-500'"
                                                x-text="n"></button>
                                        </template>
                                    </div>
                                    <button type="button" @click="removeRow(rowIndex)" class="text-xs text-error-500">remove row</button>
                                </div>
                                <div class="grid grid-cols-1 gap-3"
                                    :class="{'sm:grid-cols-2': row.columns.length===2,'sm:grid-cols-3': row.columns.length===3}">
                                    <template x-for="(col, colIndex) in row.columns" :key="colIndex">
                                        {{-- SortableJS list: wireColumn() attaches on render; data-row/data-col
                                             stay Alpine-bound so onFieldDrop reads current indices. --}}
                                        <div class="min-h-[48px] rounded-lg bg-gray-50 p-2 dark:bg-white/[0.02]"
                                            x-init="wireColumn($el)"
                                            :data-row="rowIndex" :data-col="colIndex">
                                            <template x-for="(key, fi) in col.fields" :key="key">
                                                <div @click="selectedKey = key"
                                                    data-field-card
                                                    class="mb-2 cursor-grab rounded-lg border bg-white px-3 py-2 active:cursor-grabbing dark:bg-gray-900"
                                                    :class="selectedKey === key ? 'border-brand-500 ring-1 ring-brand-300' : 'border-gray-200 dark:border-gray-700'">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <div class="min-w-0">
                                                            <div class="flex items-center gap-1 text-sm font-medium text-gray-800 dark:text-white/90">
                                                                <span class="shrink-0 text-gray-300" title="Drag to reorder">⠿</span>
                                                                <span class="truncate" x-text="field(key)?.field_label"></span>
                                                                <span x-show="field(key)?.is_required" class="text-error-500" title="Required">*</span>
                                                            </div>
                                                            <div class="text-[10px] uppercase tracking-wide text-gray-400" x-text="field(key)?.field_type"></div>
                                                        </div>
                                                        <button type="button" data-no-drag @click.stop="removeField(key)" class="shrink-0 px-1 leading-none text-error-400 hover:text-error-500" title="Remove field">✕</button>
                                                    </div>
                                                </div>
                                            </template>
                                            <template x-if="col.fields.length === 0">
                                                <div class="pointer-events-none py-3 text-center text-[11px] text-gray-300">empty column — drag a field here</div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- RIGHT: field config panel (floats as the canvas scrolls) --}}
            <div class="col-span-12 lg:col-span-3">
                <div class="rounded-2xl border border-gray-200 bg-palette-surface p-4 dark:border-gray-800 dark:bg-white/[0.03] lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto">
                    <h3 class="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Field settings</h3>
                    <template x-if="!selectedKey">
                        <p class="text-xs text-gray-400">Select a field on the canvas to configure it.</p>
                    </template>
                    <template x-if="selectedKey && field(selectedKey)">
                        <div class="space-y-3" x-data="{ get f() { return field(selectedKey); } }">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Label</label>
                                <input type="text" x-model="f.field_label" @input="onLabelInput(f)" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Field key</label>
                                <p class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-xs text-gray-600 dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-300" x-text="f.field_key"></p>
                                <p class="mt-1 text-[10px] text-gray-400"
                                    x-text="f._keyLocked
                                        ? 'Stored in the submission payload — locked after save.'
                                        : 'Generated from the label; locks once the form is saved.'"></p>
                            </div>
                            <div x-show="!['heading','static-text'].includes(f.field_type)">
                                <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                                    <input type="checkbox" x-model="f.is_required" class="h-4 w-4 rounded border-gray-300 text-brand-500" /> Required
                                </label>
                            </div>
                            <div x-show="!['heading','static-text','signature','image','file','select','radio','checkbox'].includes(f.field_type)">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Placeholder</label>
                                <input type="text" x-model="f.placeholder_hint" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90" />
                            </div>

                            {{-- autofill (universal field mapping: profile, organization, or a
                                 system value — current date/time/year, school year) --}}
                            <div x-show="!['heading','static-text'].includes(f.field_type)">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Autofill</label>
                                <select x-model="f.universal_key" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
                                    <option value="">— none —</option>
                                    @foreach ($universalGroups as $group => $entries)
                                        <optgroup label="{{ $universalGroupLabels[$group] ?? ucfirst($group) }}">
                                            @foreach ($entries as $ukey => $meta)
                                                <option value="{{ $ukey }}">{{ $meta['label'] }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                    <template x-if="systemAutofillOptions(f.field_type).length">
                                        <optgroup label="Current value">
                                            <template x-for="opt in systemAutofillOptions(f.field_type)" :key="opt.value">
                                                <option :value="opt.value" x-text="opt.label"></option>
                                            </template>
                                        </optgroup>
                                    </template>
                                </select>
                                <p class="mt-1 text-[10px] text-gray-400">Pre-fills this field when the form loads — from the signed-in user's profile, their organization, or a current value.</p>
                            </div>

                            {{-- static text body --}}
                            <div x-show="f.field_type === 'static-text'">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Content</label>
                                <textarea x-model="f.field_options.content" rows="3" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"></textarea>
                            </div>

                            {{-- numeric constraints --}}
                            <div x-show="isNumeric(f.field_type)" class="grid grid-cols-3 gap-2">
                                <div><label class="mb-1 block text-[10px] text-gray-500">Min</label><input type="number" x-model="f.field_options.min" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:text-white/90" /></div>
                                <div><label class="mb-1 block text-[10px] text-gray-500">Max</label><input type="number" x-model="f.field_options.max" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:text-white/90" /></div>
                                <div><label class="mb-1 block text-[10px] text-gray-500">Step</label><input type="number" x-model="f.field_options.step" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:text-white/90" /></div>
                            </div>

                            {{-- file accept — checkboxes over the hard allowlist --}}
                            <div x-show="isFileLike(f.field_type)">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Accepted types</label>
                                <div class="flex flex-wrap gap-x-3 gap-y-1.5">
                                    <template x-for="ext in acceptChoices(f.field_type)" :key="ext">
                                        <label class="flex cursor-pointer items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300">
                                            <input type="checkbox" :checked="acceptHas(f, ext)" @change="toggleAccept(f, ext)"
                                                class="h-3.5 w-3.5 rounded border-gray-300 text-brand-500" />
                                            <span x-text="ext.toUpperCase()"></span>
                                        </label>
                                    </template>
                                </div>
                                <p class="mt-1 text-[10px] text-gray-400">
                                    Uploads are limited to JPEG, PNG and HEIC<span x-show="f.field_type === 'file'"> — plus PDF for file fields</span>.
                                </p>
                            </div>

                            {{-- autofill with current date/time (date/time/datetime) --}}
                            <div x-show="supportsAutofillNow(f.field_type)">
                                <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                                    <input type="checkbox" x-model="f.field_options.autofill_now" class="h-4 w-4 rounded border-gray-300 text-brand-500" />
                                    Autofill with the current date/time
                                </label>
                            </div>

                            {{-- password minimum length --}}
                            <div x-show="f.field_type === 'password'">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Minimum length</label>
                                <input type="number" min="4" x-model.number="f.field_options.min" class="h-9 w-24 rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:text-white/90" />
                            </div>

                            {{-- photo set limit --}}
                            <div x-show="f.field_type === 'multi-image'">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Max photos</label>
                                <input type="number" min="1" max="10" x-model.number="f.field_options.max_files" class="h-9 w-24 rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:text-white/90" />
                                <label class="mt-2 flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                                    <input type="checkbox" :checked="f.field_options.media_copy === 'accomplishment'"
                                        @change="f.field_options.media_copy = $event.target.checked ? 'accomplishment' : ''"
                                        class="h-4 w-4 rounded border-gray-300 text-brand-500" />
                                    Also post to the organization wall
                                </label>
                            </div>

                            {{-- table-input columns --}}
                            <div x-show="f.field_type === 'table-input'" class="space-y-2">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Columns</label>
                                <template x-for="(col, ci) in (f.field_options.columns || [])" :key="ci">
                                    <div class="rounded-lg border border-gray-200 p-2 dark:border-gray-700">
                                        <div class="flex items-center gap-1">
                                            <input type="text" x-model="col.label" @input="onColumnLabel(col)" placeholder="Label"
                                                class="h-8 w-full rounded border border-gray-300 bg-transparent px-2 text-xs dark:border-gray-700 dark:text-white/90" />
                                            <button type="button" @click="removeColumn(selectedKey, ci)" class="px-1 text-error-400">✕</button>
                                        </div>
                                        <div class="mt-1 flex items-center gap-2">
                                            <select x-model="col.type" class="h-8 flex-1 rounded border border-gray-300 bg-transparent px-1 text-xs dark:border-gray-700 dark:text-white/90">
                                                <option value="text">Text</option>
                                                <option value="number">Number</option>
                                                <option value="date">Date</option>
                                                <option value="event-select">Event</option>
                                            </select>
                                            <label class="flex items-center gap-1 text-[10px] text-gray-500">
                                                <input type="checkbox" x-model="col.required" class="h-3.5 w-3.5 rounded border-gray-300 text-brand-500" /> req
                                            </label>
                                        </div>
                                    </div>
                                </template>
                                <button type="button" @click="addColumn(selectedKey)" class="text-xs text-brand-500">+ Add column</button>

                                <label class="mt-2 block text-xs font-medium text-gray-600 dark:text-gray-400">Row total (optional)</label>
                                <div class="flex items-center gap-1">
                                    <input type="text" x-model="f.field_options.row_total.label" placeholder="Total label"
                                        class="h-8 w-full rounded border border-gray-300 bg-transparent px-2 text-xs dark:border-gray-700 dark:text-white/90" />
                                    <input type="text" x-model="f.field_options.row_total.key" placeholder="key"
                                        class="h-8 w-24 rounded border border-gray-300 bg-transparent px-2 font-mono text-[10px] dark:border-gray-700 dark:text-white/90" />
                                </div>
                                <p class="text-[10px] text-gray-400">Multiply these number columns per row (keys, comma-separated):</p>
                                <input type="text" :value="(f.field_options.row_total.multiply || []).join(', ')"
                                    @input="f.field_options.row_total.multiply = $event.target.value.split(',').map(s => s.trim()).filter(Boolean)"
                                    placeholder="e.g. price_per_unit, quantity"
                                    class="h-8 w-full rounded border border-gray-300 bg-transparent px-2 font-mono text-[10px] dark:border-gray-700 dark:text-white/90" />
                            </div>

                            {{-- computed formula --}}
                            <div x-show="f.field_type === 'computed'" class="space-y-2">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Formula</label>
                                <select x-model="f.field_options.formula" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
                                    <option value="sum">Sum of fields</option>
                                    <option value="difference">First minus the rest</option>
                                </select>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Fields</label>
                                <template x-for="(arg, ai) in (f.field_options.args || [])" :key="ai">
                                    <div class="flex items-center gap-1">
                                        <select x-model="f.field_options.args[ai]" class="h-8 w-full rounded border border-gray-300 bg-transparent px-1 text-xs dark:border-gray-700 dark:text-white/90">
                                            <option value="">— choose —</option>
                                            <template x-for="s in numericSiblings(f.field_key)" :key="s.value">
                                                <option :value="s.value" x-text="s.label"></option>
                                            </template>
                                        </select>
                                        <button type="button" @click="removeArg(selectedKey, ai)" class="px-1 text-error-400">✕</button>
                                    </div>
                                </template>
                                <button type="button" @click="addArg(selectedKey)" class="text-xs text-brand-500">+ Add field</button>
                            </div>

                            {{-- conditional visibility --}}
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Visibility</label>
                                <select :value="f.field_options.visible_when ? 'conditional' : 'always'"
                                    @change="setVisibilityMode(f, $event.target.value)"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                    <option value="always">Always shown</option>
                                    <option value="conditional">Only when a condition holds</option>
                                </select>
                                <template x-if="f.field_options.visible_when">
                                    <div class="mt-2 space-y-2">
                                        <select x-model="f.field_options.visible_when.field"
                                            :disabled="conditionSources(f.field_key).length === 0"
                                            class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            <option value="" x-text="conditionSources(f.field_key).length === 0 ? '— no other fields in this row —' : '— when this field… —'"></option>
                                            <template x-for="c in conditionSources(f.field_key)" :key="c.field_key">
                                                <option :value="c.field_key" x-text="c.field_label"></option>
                                            </template>
                                        </select>
                                        <select x-model="f.field_options.visible_when.op"
                                            class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            <option value="equals">equals</option>
                                            <option value="not_equals">does not equal</option>
                                            <option value="contains">contains / includes</option>
                                            <option value="filled">is filled in</option>
                                            <option value="empty">is empty</option>
                                        </select>
                                        <template x-if="needsConditionValue(f)">
                                            <div>
                                                <template x-if="conditionController(f) && isOptioned(conditionController(f).field_type)">
                                                    <select x-model="f.field_options.visible_when.value"
                                                        class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                                        <option value="">— choose an option —</option>
                                                        <template x-for="opt in ((conditionController(f).field_options || {}).options || [])" :key="opt.value">
                                                            <option :value="opt.value" x-text="opt.label"></option>
                                                        </template>
                                                    </select>
                                                </template>
                                                <template x-if="!conditionController(f) || !isOptioned(conditionController(f).field_type)">
                                                    <input type="text" x-model="f.field_options.visible_when.value" placeholder="Comparison value"
                                                        class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90" />
                                                </template>
                                            </div>
                                        </template>
                                        <p class="text-[10px] text-gray-400">The field is shown (and required) only while the condition holds; otherwise its value is not submitted.</p>
                                    </div>
                                </template>
                            </div>

                            {{-- option source toggle (select) / picker (search) --}}
                            <div x-show="supportsSource(f.field_type)">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Options</label>
                                {{-- A dropdown can be pre-defined or drawn from registered entries;
                                     the search field is always drawn from registered entries. --}}
                                <template x-if="f.field_type === 'select'">
                                    <div class="mb-2 flex flex-wrap gap-3 text-xs text-gray-600 dark:text-gray-300">
                                        <label class="flex items-center gap-1.5">
                                            <input type="radio" :checked="!f.field_options.source" @change="setOptionMode(f, 'static')"
                                                class="h-3.5 w-3.5 border-gray-300 text-brand-500" /> Pre-defined
                                        </label>
                                        <label class="flex items-center gap-1.5">
                                            <input type="radio" :checked="!!f.field_options.source" @change="setOptionMode(f, 'source')"
                                                class="h-3.5 w-3.5 border-gray-300 text-brand-500" /> From registered entries
                                        </label>
                                    </div>
                                </template>
                                <template x-if="f.field_type === 'search' || f.field_options.source">
                                    <div>
                                        <select x-model="f.field_options.source"
                                            class="h-8 w-full rounded border border-gray-300 bg-transparent px-2 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            <template x-for="src in optionSources" :key="src.key">
                                                <option :value="src.key" x-text="src.label"></option>
                                            </template>
                                        </select>
                                        <p class="mt-1 text-[10px] text-gray-400">Submitters pick from this registered list, scoped to the entries they may access.</p>
                                    </div>
                                </template>
                            </div>

                            {{-- choice options (hand-typed; hidden when a source is chosen) --}}
                            <div x-show="isOptioned(f.field_type) && !f.field_options.source">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Options</label>
                                <div class="space-y-2">
                                    <template x-for="(opt, i) in (f.field_options.options || [])" :key="i">
                                        <div class="flex items-center gap-1">
                                            <input type="text" x-model="opt.label" placeholder="Label" class="h-8 w-full rounded border border-gray-300 bg-transparent px-2 text-xs dark:border-gray-700 dark:text-white/90" />
                                            <input type="text" x-model="opt.value" placeholder="value" class="h-8 w-24 rounded border border-gray-300 bg-transparent px-2 font-mono text-[10px] dark:border-gray-700 dark:text-white/90" />
                                            <button type="button" @click="removeOption(selectedKey, i)" class="px-1 text-error-400">✕</button>
                                        </div>
                                    </template>
                                </div>
                                <button type="button" @click="addOption(selectedKey)" class="mt-2 text-xs text-brand-500">+ Add option</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- ===================== STEP 2: PRINTED PDF TEMPLATE ===================== --}}
        <div x-show="step === 2" x-cloak>
            <x-form-builder.pdf-template
                :exportUrl="route('admin.form-builder.template.export-docx')"
                :importUrl="route('admin.form-builder.template.import-docx')" />
        </div>

        {{-- ========================= STEP 3: META DETAILS ========================= --}}
        <div x-show="step === 3" x-cloak class="grid grid-cols-12 gap-5">
            <div class="col-span-12 lg:col-span-8 lg:col-start-3">
                <div class="rounded-2xl border border-gray-200 bg-palette-surface p-6 dark:border-gray-800 dark:bg-white/[0.03] space-y-4">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Form details</h3>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Form name</label>
                        <input type="text" x-model="name" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90" />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Purpose</label>
                        <textarea x-model="description_text" rows="3" placeholder="What is this form for? (used by search)"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"></textarea>
                    </div>

                    {{-- Sidebar icon picker: the glyph shown beside this form in the nav. --}}
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Sidebar icon</label>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="icon = ''" title="Default"
                                class="flex h-10 w-10 items-center justify-center rounded-lg border transition hover:border-brand-400 [&_svg]:h-5 [&_svg]:w-5"
                                :class="icon === '' ? 'border-brand-500 text-brand-600 ring-1 ring-brand-300' : 'border-gray-200 text-gray-500 dark:border-gray-700'">
                                {!! \App\Helpers\MenuHelper::getIconSvg('forms') !!}
                            </button>
                            @foreach ($iconChoices as $name => $svg)
                                <button type="button" @click="icon = @js($name)" title="{{ ucfirst(str_replace('-', ' ', $name)) }}"
                                    class="flex h-10 w-10 items-center justify-center rounded-lg border transition hover:border-brand-400 [&_svg]:h-5 [&_svg]:w-5"
                                    :class="icon === @js($name) ? 'border-brand-500 text-brand-600 ring-1 ring-brand-300' : 'border-gray-200 text-gray-500 dark:border-gray-700'">
                                    {!! $svg !!}
                                </button>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Shown next to this form in the sidebar.</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Route name (URL slug)</label>
                        <input type="text" x-model="route_name" @input="routeTouched = true"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 font-mono text-xs dark:border-gray-700 dark:text-white/90" />
                        <p class="mt-1 text-xs text-gray-400">/forms/<span x-text="route_name || '…'"></span></p>
                    </div>

                    {{-- System function is bound from the "System Functions" slot
                         on the Forms list; it is fixed here (read-only chip). --}}
                    @if ($lockedFunction ?? null)
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">System function</label>
                            <div class="flex items-center gap-2 rounded-lg border border-brand-200 bg-brand-50/50 px-3 py-2.5 text-sm font-medium text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/5 dark:text-brand-300">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                {{ \App\Forms\SystemFunction::label($lockedFunction) }}
                            </div>
                            <p class="mt-1 text-xs text-gray-400">
                                This form drives the fixed <strong>{{ \App\Forms\SystemFunction::label($lockedFunction) }}</strong> flow.
                                Submissions become requests an admin approves. The binding cannot be changed here.
                            </p>
                        </div>
                    @endif

                    {{-- Forms publish on save — no draft/sidebar-targeting config. --}}
                    <p class="rounded-lg px-3 py-2 text-xs font-medium"
                        :class="hasTemplate
                            ? 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-500'
                            : 'bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-orange-400'"
                        x-text="hasTemplate
                            ? 'Saving publishes this form to officers immediately.'
                            : 'This form goes live to officers once it has a printed template (Step 2).'"></p>
                </div>
            </div>
        </div>

        {{-- Wizard footer nav --}}
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
