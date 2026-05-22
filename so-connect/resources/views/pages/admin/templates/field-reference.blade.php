@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Template Field Reference" />

    <div class="space-y-6">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    All <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-mono dark:bg-gray-800">@{{ placeholder }}</code> keys recognised by each form.
                    Use these exact names in your DOCX template files.
                </p>
            </div>
            <a href="{{ route('admin.templates.index') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Templates
            </a>
        </div>

        {{-- Per-form field tables --}}
        @foreach($forms as $form)
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">

                {{-- Form header --}}
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $form->name }}</h3>
                        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500 font-mono">/forms/{{ $form->route_name }}</p>
                    </div>
                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ $form->fields->count() }} field{{ $form->fields->count() === 1 ? '' : 's' }}</span>
                </div>

                @if($form->fields->isEmpty())
                    <div class="px-6 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                        No fields registered for this form.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 bg-gray-50/60 dark:border-gray-800 dark:bg-white/[0.02]">
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Placeholder</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Label</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Type</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Required</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($form->fields as $field)
                                    @php $pk = '{' . '{' . $field->field_key . '}' . '}'; @endphp
                                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                        <td class="px-6 py-3">
                                            <code class="rounded bg-brand-50 px-2 py-0.5 text-xs font-mono font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                                                {{ $pk }}
                                            </code>
                                        </td>
                                        <td class="px-6 py-3 text-gray-700 dark:text-gray-300">{{ $field->field_label }}</td>
                                        <td class="px-6 py-3">
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                                {{ $field->field_type }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-3">
                                            @if($field->is_required)
                                                <span class="inline-flex items-center gap-1 text-xs font-medium text-error-600 dark:text-error-400">
                                                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                                    </svg>
                                                    Required
                                                </span>
                                            @else
                                                <span class="text-xs text-gray-400 dark:text-gray-500">Optional</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach

    </div>
@endsection
