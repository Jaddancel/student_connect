@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Scoring Trigger" />

    <div class="space-y-6"
        x-data="scoringRuleEditor({
            saveUrl: '{{ route('admin.scoring.rules.update', $criterion) }}',
            csrf: '{{ csrf_token() }}',
            variables: {{ Js::from($variables) }},
            workspace: {{ Js::from($rule?->workspace) }},
            enabled: {{ ($rule?->enabled ?? true) ? 'true' : 'false' }},
        })">

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $criterion->label }}</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $categories[$criterion->category_key]['label'] ?? $criterion->category_key }}
                        · {{ $criterion->weight }} point(s) per instance
                        · Build the trigger by snapping blocks into the <em>when / if / then</em> sockets.
                        Drag blocks in from the palette on the left.
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
                    <button type="button" @click="save()" :disabled="saving || loading"
                        class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:opacity-60">
                        <span x-show="!saving">Save trigger</span>
                        <span x-show="saving" x-cloak>Saving…</span>
                    </button>
                </div>
            </div>

            <p x-show="error" x-cloak x-text="error"
                class="mt-3 rounded-lg bg-error-50 px-3 py-2 text-xs font-medium text-error-600 dark:bg-error-500/10"></p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800 dark:bg-white/[0.03]">
            <div x-show="loading" class="flex h-[520px] items-center justify-center text-sm text-gray-400">
                Loading the block editor…
            </div>
            <div x-ref="blockly" class="h-[520px] w-full rounded-xl" x-show="!loading"></div>
        </div>

        <p class="text-xs text-gray-400 dark:text-gray-500">
            Variables cover every field of every form page, the universal profile fields, and event-plan
            details. Only <strong>approved</strong> records in the scored semester are tallied — a rejected
            request sends the submitter back to the form and never counts.
        </p>
    </div>
@endsection
