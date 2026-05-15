@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Verify Fields" />

    <div class="space-y-6">

        {{-- Header --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Verify Template Fields</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Review how the uploaded DOCX placeholders align with the form's defined fields before activating.
                    </p>
                </div>
                <a href="{{ route('admin.templates.index') }}"
                   class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800">
                    Back
                </a>
            </div>
        </div>

        {{-- Template Info --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Form</p>
                    <p class="mt-1 text-sm font-medium text-gray-800 dark:text-white/90">{{ $form->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Template</p>
                    <p class="mt-1 text-sm font-medium text-gray-800 dark:text-white/90">{{ $template->template_name }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Version</p>
                    <p class="mt-1">
                        <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-500/15 dark:text-blue-400">
                            v{{ $template->version }}
                        </span>
                    </p>
                </div>
            </div>
        </div>

        {{-- Warning: missing required fields --}}
        @php
            $missingRequired = collect($missing)->filter(fn($f) => $f->is_required);
        @endphp
        @if($missingRequired->isNotEmpty())
            <div class="rounded-xl border border-error-300 bg-error-50 px-4 py-3 dark:border-error-500/30 dark:bg-error-500/10">
                <p class="text-sm font-semibold text-error-700 dark:text-error-400">
                    Warning: {{ $missingRequired->count() }} required field(s) have no matching placeholder in the DOCX.
                </p>
                <p class="mt-1 text-sm text-error-600 dark:text-error-400">
                    These fields will be skipped during document generation. You may still activate the template, but consider updating the DOCX to include the missing <span class="font-mono">@{{placeholder}}</span> tags.
                </p>
            </div>
        @endif

        {{-- Field Comparison --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

            {{-- Matched --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center gap-2 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-success-100 dark:bg-success-500/15">
                        <svg class="h-3 w-3 text-success-600 dark:text-success-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Matched</h3>
                    <span class="ml-auto inline-flex items-center rounded-full bg-success-100 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">
                        {{ count($matched) }}
                    </span>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800 px-5">
                    @forelse($matched as $key => $entry)
                        <div class="py-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ '{{' }}{{ $key }}{{ '}}' }}</span>
                                    <p class="mt-0.5 text-sm text-gray-700 dark:text-gray-300">{{ $entry['field']->field_label }}</p>
                                </div>
                                @if($entry['field']->is_required)
                                    <span class="mt-0.5 shrink-0 text-xs font-medium text-error-500">required</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-gray-400 dark:text-gray-500 italic">No matches found.</p>
                    @endforelse
                </div>
            </div>

            {{-- Missing from Template --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center gap-2 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-warning-100 dark:bg-warning-500/15">
                        <svg class="h-3 w-3 text-warning-600 dark:text-warning-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Missing from Template</h3>
                    <span class="ml-auto inline-flex items-center rounded-full {{ $missing->isNotEmpty() ? 'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' }} px-2 py-0.5 text-xs font-medium">
                        {{ $missing->count() }}
                    </span>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800 px-5">
                    @forelse($missing as $key => $field)
                        <div class="py-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <span class="font-mono text-xs {{ $field->is_required ? 'text-error-500' : 'text-gray-400 dark:text-gray-500' }}">
                                        {{ '{{' }}{{ $key }}{{ '}}' }}
                                    </span>
                                    <p class="mt-0.5 text-sm text-gray-700 dark:text-gray-300">{{ $field->field_label }}</p>
                                </div>
                                @if($field->is_required)
                                    <span class="mt-0.5 shrink-0 text-xs font-semibold text-error-500">REQUIRED</span>
                                @else
                                    <span class="mt-0.5 shrink-0 text-xs text-gray-400 dark:text-gray-500">optional</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-gray-400 dark:text-gray-500 italic">All form fields are present in the template.</p>
                    @endforelse
                </div>
            </div>

            {{-- Extra in Template --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center gap-2 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-500/15">
                        <svg class="h-3 w-3 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Extra in Template</h3>
                    <span class="ml-auto inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-500/15 dark:text-blue-400">
                        {{ count($extra) }}
                    </span>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800 px-5">
                    @forelse($extra as $placeholder)
                        <div class="py-3">
                            <span class="font-mono text-xs text-blue-600 dark:text-blue-400">{{ '{{' }}{{ $placeholder }}{{ '}}' }}</span>
                            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">No matching form field — will be left blank in generated documents.</p>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-gray-400 dark:text-gray-500 italic">No extra placeholders in the template.</p>
                    @endforelse
                </div>
            </div>

        </div>

        {{-- Actions --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Confirming will activate this template and make the form visible in the sidebar.
                    @if($missingRequired->isNotEmpty())
                        <span class="font-medium text-warning-600 dark:text-warning-400">Proceed with caution — {{ $missingRequired->count() }} required field(s) are missing.</span>
                    @endif
                </p>

                <div class="flex items-center gap-3">
                    <form method="POST" action="{{ route('admin.templates.destroy', $template) }}"
                          onsubmit="return confirm('Discard this template and delete the uploaded file?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="rounded-lg border border-error-300 px-4 py-2.5 text-sm font-medium text-error-600 transition hover:bg-error-50 dark:border-error-500/40 dark:text-error-400 dark:hover:bg-error-500/10">
                            Cancel &amp; Delete
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.templates.confirm', $template) }}">
                        @csrf
                        <button type="submit"
                                class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                            Confirm &amp; Activate
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
@endsection
