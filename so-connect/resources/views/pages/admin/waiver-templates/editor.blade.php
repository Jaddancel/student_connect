@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="New Waiver Template" />

    <div x-data="waiverTemplateEditor({
            storeUrl: '{{ route('admin.waiver-templates.store') }}',
            indexUrl: '{{ route('admin.waiver-templates.index') }}',
            csrf: '{{ csrf_token() }}',
            zoneTypes: {{ Illuminate\Support\Js::from(config('waiver.zone_types', ['text','signature','stamp'])) }},
        })" class="mx-auto max-w-5xl space-y-5">

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <a href="{{ route('admin.waiver-templates.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400">← Back</a>
            <p x-show="error" x-cloak x-text="error" class="text-sm font-medium text-error-600"></p>
            <button type="button" @click="save()" :disabled="saving"
                class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:opacity-60">
                <span x-show="!saving">Save template</span><span x-show="saving">Saving…</span>
            </button>
        </div>

        <div class="grid grid-cols-12 gap-5 lg:items-start">
            {{-- Canvas --}}
            <div class="col-span-12 lg:col-span-8">
                <div class="rounded-2xl border border-gray-200 bg-palette-surface p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="mb-3 flex flex-wrap items-center gap-3">
                        <label class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                            <input type="file" accept="image/jpeg,image/png" class="hidden" @change="uploadImage($event)" />
                            <span x-text="hasImage ? 'Replace image' : 'Upload reference waiver image'"></span>
                        </label>
                        <span x-show="hasImage" x-cloak class="text-xs text-gray-400">Drag on the image to draw a zone.</span>
                    </div>
                    <template x-if="!hasImage">
                        <div class="grid h-64 place-items-center rounded-xl border-2 border-dashed border-gray-300 text-sm text-gray-400 dark:border-gray-700">
                            Upload a reference waiver to start drawing zones.
                        </div>
                    </template>
                    <div x-ref="stage" class="overflow-hidden rounded-xl"></div>
                </div>
            </div>

            {{-- Zone list --}}
            <div class="col-span-12 lg:col-span-4">
                <div class="rounded-2xl border border-gray-200 bg-palette-surface p-4 dark:border-gray-800 dark:bg-white/[0.03] lg:sticky lg:top-24">
                    <div class="mb-3">
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Template name</label>
                        <input type="text" x-model="name" placeholder="e.g. Standard Event Waiver"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90" />
                    </div>

                    <h3 class="mb-2 text-sm font-semibold text-gray-800 dark:text-white/90">Zones</h3>
                    <template x-if="zones.length === 0">
                        <p class="text-xs text-gray-400">No zones yet — draw rectangles on the image.</p>
                    </template>
                    <div class="space-y-2">
                        <template x-for="(zone, i) in zones" :key="i">
                            <div class="rounded-lg border p-2"
                                :class="selected === i ? 'border-brand-400 ring-1 ring-brand-200' : 'border-gray-200 dark:border-gray-700'"
                                @click="select(i)">
                                <div class="flex items-center gap-1">
                                    <input type="text" x-model="zone.name" placeholder="Zone name"
                                        class="h-8 w-full rounded border border-gray-300 bg-transparent px-2 text-xs dark:border-gray-700 dark:text-white/90" />
                                    <button type="button" @click.stop="removeZone(i)" class="px-1 text-error-400">✕</button>
                                </div>
                                <select x-model="zone.type" class="mt-1 h-8 w-full rounded border border-gray-300 bg-transparent px-1 text-xs dark:border-gray-700 dark:text-white/90">
                                    <template x-for="t in zoneTypes" :key="t">
                                        <option :value="t" x-text="t"></option>
                                    </template>
                                </select>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
