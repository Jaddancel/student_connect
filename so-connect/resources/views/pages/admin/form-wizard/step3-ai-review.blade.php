@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Create New Form" />

    <div class="space-y-6">

        <x-admin.wizard-progress :step="3" :total="5" :labels="[1 => 'Upload', 2 => 'Details', 3 => 'AI Review', 4 => 'Revise', 5 => 'Confirm']" />

        {{-- Header --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Step 3 — AI Field Detection</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        The AI is reading your DOCX and detecting form fields. This may take up to a minute.
                    </p>
                </div>
            </div>
        </div>

        {{-- AI status panel --}}
        <div x-data="{
            status: '{{ $aiResult['status'] ?? 'pending' }}',
            fields: {{ json_encode($aiResult['fields'] ?? []) }},
            error: {{ json_encode($aiResult['error'] ?? null) }},
            debug: {{ app()->environment('local') ? 'true' : 'false' }},
            pollInterval: null,
            log(...args) {
                if (this.debug) console.log('[AI field detection]', ...args);
            },
            init() {
                this.log('init', { status: this.status, fields: this.fields, error: this.error });
                if (this.status === 'pending') {
                    this.pollInterval = setInterval(() => this.poll(), 2000);
                }
            },
            async poll() {
                try {
                    const res = await fetch('{{ route('admin.form-wizard.ai-status') }}');
                    const data = await res.json();
                    this.log('poll response', data);
                    this.status = data.status;
                    this.fields = data.fields ?? [];
                    this.error = data.error ?? null;
                    if (this.status !== 'pending') {
                        clearInterval(this.pollInterval);
                        this.log('polling stopped', { status: this.status, fieldCount: this.fields.length, error: this.error });
                    }
                } catch (e) {
                    this.log('poll error', e);
                    // silently retry
                }
            }
        }">

            {{-- Spinner (pending) --}}
            <template x-if="status === 'pending'">
                <div class="rounded-2xl border border-gray-200 bg-white p-10 dark:border-gray-800 dark:bg-white/[0.03] text-center">
                    <svg class="mx-auto h-10 w-10 animate-spin text-brand-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <p class="mt-4 text-sm font-medium text-gray-700 dark:text-gray-300">Analyzing document…</p>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Running LibreOffice → OCR → AI field detection. Please wait.</p>
                </div>
            </template>

            {{-- Done — fields found --}}
            <template x-if="status === 'done' && fields.length > 0">
                <div class="space-y-4">
                    <div class="rounded-2xl border border-success-200 bg-success-50 dark:border-success-500/30 dark:bg-success-500/10 px-5 py-4">
                        <p class="text-sm font-semibold text-success-700 dark:text-success-400">
                            AI detected <span x-text="fields.length"></span> field(s) in your document.
                        </p>
                        <p class="mt-0.5 text-xs text-success-600 dark:text-success-400">Review the list below. Click "Looks good" to accept or "Revise" to edit manually.</p>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] overflow-hidden">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">#</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Label</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Field Key</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Type</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Required</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <template x-for="(field, idx) in fields" :key="idx">
                                    <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                        <td class="px-5 py-3 text-gray-400 dark:text-gray-500" x-text="idx + 1"></td>
                                        <td class="px-5 py-3 text-gray-800 dark:text-white/90 font-medium" x-text="field.label"></td>
                                        <td class="px-5 py-3 font-mono text-xs text-gray-500 dark:text-gray-400" x-text="field.field_key"></td>
                                        <td class="px-5 py-3 text-gray-600 dark:text-gray-300" x-text="field.field_type"></td>
                                        <td class="px-5 py-3">
                                            <template x-if="field.is_required">
                                                <span class="inline-flex items-center rounded-full bg-error-100 px-2 py-0.5 text-xs font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400">Yes</span>
                                            </template>
                                            <template x-if="!field.is_required">
                                                <span class="text-xs text-gray-400 dark:text-gray-500">No</span>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="flex justify-end gap-3">
                            <form method="POST" action="{{ route('admin.form-wizard.ai-confirm') }}">
                                @csrf
                                <input type="hidden" name="choice" value="revise">
                                <button type="submit"
                                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                                    I want to revise
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.form-wizard.ai-confirm') }}">
                                @csrf
                                <input type="hidden" name="choice" value="looks_good">
                                <button type="submit"
                                        class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                                    Looks good →
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Done — no fields detected, or failed --}}
            <template x-if="status === 'done' && fields.length === 0 || status === 'failed'">
                <div class="space-y-4">
                    <div class="rounded-2xl border border-warning-200 bg-warning-50 dark:border-warning-500/30 dark:bg-warning-500/10 px-5 py-4">
                        <p class="text-sm font-semibold text-warning-700 dark:text-warning-400">
                            <template x-if="status === 'failed'">
                                <span>AI detection failed. You can still add fields manually.</span>
                            </template>
                            <template x-if="status !== 'failed'">
                                <span>No fields were detected automatically.</span>
                            </template>
                        </p>
                        <p class="mt-0.5 text-xs text-warning-600 dark:text-warning-400" x-show="error" x-text="error"></p>
                        <p class="mt-0.5 text-xs text-warning-600 dark:text-warning-400" x-show="!error">
                            Proceed to the next step to add fields manually.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="flex justify-end">
                            <form method="POST" action="{{ route('admin.form-wizard.ai-confirm') }}">
                                @csrf
                                <input type="hidden" name="choice" value="revise">
                                <button type="submit"
                                        class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                                    Add Fields Manually →
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </template>

        </div>

    </div>
@endsection
