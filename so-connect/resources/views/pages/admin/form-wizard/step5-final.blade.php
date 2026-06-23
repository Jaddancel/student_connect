@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Create New Form" />

    <div class="space-y-6">

        <x-admin.wizard-progress :step="5" :total="5" :labels="[1 => 'Upload', 2 => 'Details', 3 => 'AI Review', 4 => 'Revise', 5 => 'Confirm']" />

        {{-- Header --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Step 5 — Final Review</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Review your form configuration before activating it.
                    </p>
                </div>
            </div>
        </div>

        {{-- Summary --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6 space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Form Name</p>
                    <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90">{{ $form->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Fields Defined</p>
                    <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90">{{ $form->fields->count() }}</p>
                </div>
                @if($form->description_text)
                    <div class="sm:col-span-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Purpose</p>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $form->description_text }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Fields table --}}
        @if($form->fields->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] overflow-hidden">
                <div class="border-b border-gray-100 dark:border-gray-800 px-5 py-4">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Form Fields</h3>
                </div>
                <div class="overflow-x-auto">
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
                            @foreach($form->fields as $field)
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-5 py-3 text-gray-400 dark:text-gray-500">{{ $loop->iteration }}</td>
                                    <td class="px-5 py-3 text-gray-800 dark:text-white/90 font-medium">{{ $field->field_label }}</td>
                                    <td class="px-5 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $field->field_key }}</td>
                                    <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $field->field_type }}</td>
                                    <td class="px-5 py-3">
                                        @if($field->is_required)
                                            <span class="inline-flex items-center rounded-full bg-error-100 px-2 py-0.5 text-xs font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400">Yes</span>
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500">No</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="rounded-2xl border border-warning-200 bg-warning-50 dark:border-warning-500/30 dark:bg-warning-500/10 px-5 py-4">
                <p class="text-sm font-medium text-warning-700 dark:text-warning-400">
                    No fields defined. You can activate the form now and add fields later, or go back to revise.
                </p>
            </div>
        @endif

        {{-- Actions --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Activating the form makes it available in the system. You can upload a DOCX template for it from the Template Manager.
                </p>
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.form-wizard.revise') }}"
                       class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        ← Back to Revise
                    </a>

                    <form method="POST" action="{{ route('admin.form-wizard.discard') }}"
                          onsubmit="return confirm('Discard this draft? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="rounded-lg border border-error-300 px-4 py-2.5 text-sm font-medium text-error-600 transition hover:bg-error-50 dark:border-error-500/40 dark:text-error-400 dark:hover:bg-error-500/10">
                            Discard
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.form-wizard.confirm') }}">
                        @csrf
                        <button type="submit"
                                class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                            Confirm &amp; Activate Form
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
@endsection
