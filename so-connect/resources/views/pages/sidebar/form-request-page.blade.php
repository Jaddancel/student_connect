@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title" />

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $form->name }}</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $form->description_text ?: 'Complete the fields below to submit this form.' }}
            </p>

            @if (session('success'))
                <div
                    class="mt-4 rounded-lg border border-success-300 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/40 dark:bg-success-500/10 dark:text-success-400">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div
                    class="mt-4 rounded-lg border border-error-300 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/40 dark:bg-error-500/10 dark:text-error-400">
                    {{ $errors->first() }}
                </div>
            @endif

            @if ($form->templates->isEmpty())
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    No active template is available for this form yet.
                </p>
            @else
                <form action="{{ route('forms.submit', ['formId' => $form->id]) }}" method="post" class="mt-5 space-y-4">
                    @csrf

                    @foreach ($form->fields as $field)
                        <div>
                            <label for="field_{{ $field->field_key }}"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                {{ $field->field_label ?: $field->field_key }}
                                @if ($field->is_required)
                                    <span class="text-error-500">*</span>
                                @endif
                            </label>

                            @php
                                $oldValue = old('fields.' . $field->field_key);
                            @endphp

                            @if ($field->field_type === 'multipleInputs')
                                @php
                                    $fieldValues = is_array($oldValue)
                                        ? $oldValue
                                        : (trim((string) $oldValue) !== ''
                                            ? [$oldValue]
                                            : []);
                                    $minimumRows = max(1, $field->mappings->count());

                                    while (count($fieldValues) < $minimumRows) {
                                        $fieldValues[] = '';
                                    }
                                @endphp

                                <div x-data="{ values: @js($fieldValues) }" class="space-y-3">
                                    <div
                                        class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/30">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">List Entries
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    Use one row per {{ $field->field_label ?: $field->field_key }}#
                                                    placeholder.
                                                </p>
                                            </div>
                                            <button type="button" x-on:click="values.push('')"
                                                class="inline-flex items-center rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-white dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                                                Add Row
                                            </button>
                                        </div>

                                        <div class="mt-4 space-y-3">
                                            <template x-for="(value, index) in values" :key="index">
                                                <div class="flex items-center gap-2">
                                                    <input type="text" name="fields[{{ $field->field_key }}][]"
                                                        x-model="values[index]"
                                                        placeholder="Enter {{ strtolower($field->field_label ?: $field->field_key) }} #"
                                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                                    <button type="button" x-on:click="values.splice(index, 1)"
                                                        x-show="values.length > 1"
                                                        class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-3 text-xs font-medium text-gray-700 transition hover:bg-white dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                                                        Remove
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            @elseif ($field->field_type === 'dynamicSelect')
                                @php
                                    $dynConfig = $dynamicFieldOptions[$field->field_key] ?? ['options' => [], 'slot_count' => 1];
                                    $dynOptions = $dynConfig['options'];
                                    $slotCount = max(1, $dynConfig['slot_count']);
                                    $dynOldValues = is_array($oldValue) ? $oldValue : [];
                                @endphp
                                <div class="space-y-2">
                                    @for ($slot = 0; $slot < $slotCount; $slot++)
                                        @php $slotOld = $dynOldValues[$slot] ?? ''; @endphp
                                        <select name="fields[{{ $field->field_key }}][]"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                            <option value="">
                                                {{ $slotCount > 1 ? 'Select ' . ($slot + 1) . '…' : 'Select…' }}
                                            </option>
                                            @foreach ($dynOptions as $opt)
                                                <option value="{{ $opt['value'] }}" @selected((string) $slotOld === (string) $opt['value'])>
                                                    {{ $opt['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @endfor
                                    @if (empty($dynOptions))
                                        <p class="text-xs text-gray-400 dark:text-gray-500">No options available for your role.</p>
                                    @endif
                                </div>
                            @elseif (in_array($field->field_type, ['textarea', 'long_text'], true))
                                <textarea id="field_{{ $field->field_key }}" name="fields[{{ $field->field_key }}]" rows="4"
                                    placeholder="Enter {{ strtolower($field->field_label ?: $field->field_key) }}"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ $oldValue }}</textarea>
                            @elseif ($field->field_type === 'select' && is_array($field->field_options))
                                <select id="field_{{ $field->field_key }}" name="fields[{{ $field->field_key }}]"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                    <option value="">Select an option</option>
                                    @foreach ($field->field_options as $option)
                                        <option value="{{ $option }}" @selected($oldValue === $option)>
                                            {{ $option }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input
                                    type="{{ $field->field_type === 'email' ? 'email' : ($field->field_type === 'date' ? 'date' : 'text') }}"
                                    id="field_{{ $field->field_key }}" name="fields[{{ $field->field_key }}]"
                                    value="{{ $oldValue }}"
                                    placeholder="Enter {{ strtolower($field->field_label ?: $field->field_key) }}"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            @endif
                        </div>
                    @endforeach

                    <div class="flex justify-end">
                        <button type="submit"
                            class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                            Submit Form
                        </button>
                    </div>
                </form>
            @endif
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h4 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Your Recent Generation Requests</h4>

            @if ($requestRows->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No generation requests submitted yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Request</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Form</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Status</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requestRows as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <p class="font-medium">#{{ $row['request_id'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ \Illuminate\Support\Carbon::parse($row['requested_at'])->format('M d, Y h:i A') }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row['form_name'] }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $row['status'] === 'approved' ? 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-400' : ($row['status'] === 'rejected' ? 'bg-error-100 text-error-700 dark:bg-error-500/15 dark:text-error-400' : 'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400') }}">
                                            {{ $row['status_label'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        @if ((int) $row['generated_document_id'] > 0 && $row['generated_status'] === 'generated' && $row['has_pdf'])
                                            <a href="{{ route('generated-documents.download', ['generatedDocumentId' => $row['generated_document_id']]) }}"
                                                class="inline-flex rounded-lg bg-brand-500 px-3 py-2 text-xs font-medium text-white transition hover:bg-brand-600">
                                                Download PDF
                                            </a>
                                        @else
                                            <span class="text-xs text-gray-500 dark:text-gray-400">Pending output</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
