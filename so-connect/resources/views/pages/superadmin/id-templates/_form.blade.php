{{--
    Shared Konva ID-template editor, used by create.blade.php and edit.blade.php.
    Expects: $templateData (array) and $template (?IdTemplate).
    The Alpine component `idTemplateEditor` is registered in resources/js/app.js.
--}}
@php
    // Zone-field targets for the ID scanner: the profile-source universal fields
    // (names, sex, address, …) plus ID-specific extraction keys that map to the
    // signup form but aren't profile fields. `full_name` is split into
    // first/middle/last by the scanner (OcrClient::expandName). Org-scoped
    // universal fields are intentionally excluded — they don't live on an ID.
    $scanFieldGroups = \App\Support\UniversalField::groupedBySource('profile');
    $scanFieldGroups['identity'] = [
        'student_id' => ['label' => 'Student ID'],
        'full_name' => ['label' => 'Full Name (LAST, FIRST MIDDLE)'],
    ];
    $scanFieldKeys = [];
    foreach ($scanFieldGroups as $group) {
        $scanFieldKeys = array_merge($scanFieldKeys, array_keys($group));
    }
@endphp
<div class="space-y-6"
    x-data="idTemplateEditor({
        data: {{ Js::from($templateData) }},
        storeUrl: '{{ route('superadmin.id-templates.store') }}',
        updateUrl: '{{ $template ? route('superadmin.id-templates.update', $template) : '' }}',
        uploadUrl: '{{ route('superadmin.id-templates.upload-image') }}',
        csrf: '{{ csrf_token() }}',
        assetBase: '/storage',
        universalKeys: {{ Js::from($scanFieldKeys) }},
    })">

    {{-- Meta bar: name, toggles, save --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 lg:items-end">
            <div class="lg:col-span-1">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Template name <span class="text-error-500">*</span>
                </label>
                <input type="text" x-model="name" placeholder="e.g. University X Student ID 2026"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
            </div>
            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" x-model="isActive"
                        class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                    Active
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" x-model="isDefault"
                        class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                    Default (drives scanner)
                </label>
            </div>
            <div class="flex items-center justify-end gap-3">
                <span x-show="note" x-cloak x-text="note" class="text-xs text-error-500"></span>
                <a href="{{ route('superadmin.id-templates.index') }}"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                    Cancel
                </a>
                <button type="button" @click="save()" :disabled="saving" x-show="mode === 'zones'"
                    class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:opacity-60">
                    <span x-show="!saving">Save template</span>
                    <span x-show="saving" x-cloak>Saving…</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Front / Back side switcher. Both sides must be completed before saving. --}}
    <div class="flex items-center gap-2 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800 dark:bg-white/[0.03]">
        <template x-for="side in ['front', 'back']" :key="side">
            <button type="button" @click="switchSide(side)"
                class="flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition"
                :class="currentSide === side
                    ? 'bg-brand-500 text-white'
                    : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800'">
                <span x-text="side === 'front' ? 'Front of ID' : 'Back of ID'"></span>
                <span x-show="isSideReady(side)" x-cloak
                    class="inline-flex h-4 w-4 items-center justify-center rounded-full"
                    :class="currentSide === side ? 'bg-white/25 text-white' : 'bg-success-500 text-white'">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                </span>
            </button>
        </template>
    </div>

    {{-- Empty state: no image picked yet for this side. --}}
    <div x-show="mode !== 'crop' && mode !== 'zones'"
        class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">
                Reference image — <span x-text="currentSide === 'front' ? 'front of ID' : 'back of ID'"></span>
            </h3>
            <label class="cursor-pointer rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                Upload image
                <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="uploadImage($event)" />
            </label>
        </div>
        <p class="rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-12 text-center text-sm text-gray-400 dark:border-gray-700 dark:bg-gray-900/30 dark:text-gray-500">
            Upload a photo of the <span x-text="currentSide === 'front' ? 'front' : 'back'"></span> of the reference ID.
            You'll crop and straighten it before drawing zones. Both sides are required.
        </p>
    </div>

    {{-- Crop / straighten step: drag the 4 corners, preview the de-skewed result. --}}
    <div x-show="mode === 'crop'" x-cloak class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Straighten the ID</h3>
                <label class="cursor-pointer rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                    Replace image
                    <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="uploadImage($event)" />
                </label>
            </div>

            {{-- Konva crop stage: image + 4 draggable corner anchors. --}}
            <div class="overflow-auto">
                <div x-ref="cropStage" class="inline-block rounded-lg border border-gray-200 dark:border-gray-700"></div>
            </div>

            <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                Drag the four blue corners to the edges of the card. The preview shows the straightened result.
            </p>
        </div>

        {{-- Crop controls + live preview. --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90">Output</h3>

            <div class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-[11px] font-medium text-gray-500 dark:text-gray-400">Orientation</label>
                    <select x-model="orientation" @change="onAspectChange()"
                        class="dark:bg-dark-900 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                        <option value="vertical">Vertical (portrait)</option>
                        <option value="horizontal">Horizontal (landscape)</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-[11px] font-medium text-gray-500 dark:text-gray-400">Aspect ratio</label>
                    <select x-model="aspectMode" @change="onAspectChange()"
                        class="dark:bg-dark-900 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                        <option value="id1">Standard ID card (85.6 × 54)</option>
                        <option value="free">Free (from corners)</option>
                        <option value="custom">Custom…</option>
                    </select>
                </div>

                <div x-show="aspectMode === 'custom'" x-cloak class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-[11px] font-medium text-gray-500 dark:text-gray-400">Width</label>
                        <input type="number" min="1" x-model.number="customW" @input="onAspectChange()"
                            class="dark:bg-dark-900 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-[11px] font-medium text-gray-500 dark:text-gray-400">Height</label>
                        <input type="number" min="1" x-model.number="customH" @input="onAspectChange()"
                            class="dark:bg-dark-900 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-[11px] font-medium text-gray-500 dark:text-gray-400">Output long edge (px)</label>
                    <input type="number" min="64" step="1" x-model.number="outputLongEdge" @input="onAspectChange()"
                        class="dark:bg-dark-900 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                </div>

                <div>
                    <label class="mb-1.5 block text-[11px] font-medium text-gray-500 dark:text-gray-400">Preview</label>
                    <div class="flex items-center justify-center rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-900/30">
                        <canvas x-ref="preview" class="max-h-56 max-w-full rounded"></canvas>
                    </div>
                </div>

                <div class="flex flex-col gap-2 pt-1">
                    <button type="button" @click="straighten()" :disabled="saving"
                        class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:opacity-60">
                        <span x-show="!saving">Straighten &amp; continue</span>
                        <span x-show="saving" x-cloak>Working…</span>
                    </button>
                    <button type="button" @click="skipStraighten()" :disabled="saving"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        Skip &amp; use as-is
                    </button>
                    <span x-show="note" x-cloak x-text="note" class="text-xs text-error-500"></span>
                </div>
            </div>
        </div>
    </div>

    {{-- Zone-drawing step. --}}
    <div x-show="mode === 'zones'" x-cloak class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">

        {{-- Canvas --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Reference image</h3>
                <div class="flex items-center gap-2">
                    <button type="button" @click="reCrop()" x-show="canReCrop"
                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        Re-crop
                    </button>
                    <label class="cursor-pointer rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        Replace image
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="uploadImage($event)" />
                    </label>
                </div>
            </div>

            {{-- Konva injects its canvas into this container. --}}
            <div class="overflow-auto">
                <div x-ref="stage" class="inline-block rounded-lg border border-gray-200 dark:border-gray-700"></div>
            </div>

            <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                Click a zone to select and resize it. Coordinates are saved in the image's native pixels, so
                re-opening this editor overlays the zones exactly.
            </p>
        </div>

        {{-- Zone panel --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Zones</h3>
                <button type="button" @click="addZone()"
                    class="inline-flex items-center gap-1 rounded-lg border border-brand-300 px-2.5 py-1.5 text-xs font-medium text-brand-600 transition hover:bg-brand-50 dark:border-brand-700 dark:text-brand-400 dark:hover:bg-brand-900/20">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add zone
                </button>
            </div>

            <p x-show="zones.length === 0" x-cloak class="text-xs text-gray-400 dark:text-gray-500">
                No zones yet. Add a zone, then position its rectangle over an ID feature (e.g. the student number).
            </p>

            <div class="space-y-3">
                <template x-for="(zone, i) in zones" :key="i">
                    <div class="rounded-xl border p-3 transition"
                        :class="selectedIndex === i ? 'border-brand-400 ring-1 ring-brand-400 dark:border-brand-600' : 'border-gray-200 dark:border-gray-700'"
                        @click="selectZone(i)">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                <span class="inline-block h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: zone.color }"></span>
                                <span x-text="'Zone ' + (i + 1)"></span>
                            </span>
                            <button type="button" @click.stop="removeZone(i)"
                                class="text-gray-400 transition hover:text-error-500">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                        <div class="space-y-2">
                            <div>
                                <label class="mb-1 block text-[11px] font-medium text-gray-500 dark:text-gray-400">Key (a-z, 0-9, _)</label>
                                <input type="text" x-model="zone.name" @input="updateZoneLabel(i)" placeholder="student_id"
                                    class="dark:bg-dark-900 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-medium text-gray-500 dark:text-gray-400">Label</label>
                                <input type="text" x-model="zone.label" @input="updateZoneLabel(i)" placeholder="Student ID Number"
                                    class="dark:bg-dark-900 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            </div>
                            <div>
                                <div class="mb-1 flex items-center justify-between gap-3">
                                    <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">Writes to field</label>
                                    <div class="inline-flex rounded-full border border-gray-200 bg-gray-50 p-0.5 text-[10px] font-medium dark:border-gray-700 dark:bg-gray-900/40">
                                        <button type="button" @click.stop="setZoneFieldMode(zone, 'universal')"
                                            class="rounded-full px-2.5 py-1 transition"
                                            :class="zone.fieldMode === 'universal' ? 'bg-brand-500 text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                                            Universal
                                        </button>
                                        <button type="button" @click.stop="setZoneFieldMode(zone, 'custom')"
                                            class="rounded-full px-2.5 py-1 transition"
                                            :class="zone.fieldMode === 'custom' ? 'bg-brand-500 text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                                            Custom
                                        </button>
                                    </div>
                                </div>

                                <div x-show="zone.fieldMode === 'universal'" x-cloak>
                                    <select x-model="zone.universalField" @change="setZoneUniversalField(zone)"
                                        class="dark:bg-dark-900 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                        @foreach ($scanFieldGroups as $group => $fields)
                                            <optgroup label="{{ ucfirst($group) }}">
                                                @foreach ($fields as $ukey => $meta)
                                                    <option value="{{ $ukey }}">{{ $meta['label'] }} ({{ $ukey }})</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </div>

                                <div x-show="zone.fieldMode === 'custom'" x-cloak>
                                    <input type="text" x-model="zone.customField" @input="setZoneCustomField(zone)" placeholder="student_id"
                                        class="dark:bg-dark-900 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                </div>

                                <p class="mt-1 text-[10px] text-gray-400 dark:text-gray-500">Universal fields pre-fill the signup form; custom keys stay extraction-only.</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-medium text-gray-500 dark:text-gray-400">Regex (optional)</label>
                                <input type="text" x-model="zone.regex" placeholder="\d{2}-\d{4}-\d{3}"
                                    class="dark:bg-dark-900 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 font-mono text-xs text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
