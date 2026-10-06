@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Document Generation Requests" />

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Request a Generated Document</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Submit form values and route the PDF generation request to your president.
            </p>

            @if (session('success'))
                <div
                    class="mt-4 rounded-lg border border-success-300 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/40 dark:bg-success-500/10 dark:text-success-400">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('status'))
                <div
                    class="mt-4 rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/40 dark:bg-warning-500/10 dark:text-warning-400">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div
                    class="mt-4 rounded-lg border border-error-300 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/40 dark:bg-error-500/10 dark:text-error-400">
                    {{ $errors->first() }}
                </div>
            @endif

            @if ($forms->isEmpty())
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    No published forms are available for your officer organizations yet.
                </p>
            @else
                <form action="{{ route('forms.request-generation') }}" method="get"
                    class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div class="md:col-span-3">
                        <label for="form_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Select Form
                        </label>
                        <select id="form_id" name="form_id"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                            @foreach ($forms as $form)
                                <option value="{{ $form->id }}" @selected((int) $selectedFormId === (int) $form->id)>
                                    {{ $form->name }} (Org #{{ (int) ($form->organization_id ?? 0) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                            Load Form
                        </button>
                    </div>
                </form>

                @if ($selectedForm)
                    <form action="{{ route('forms.request-generation.store') }}" method="post" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="form_id" value="{{ $selectedForm->id }}" />

                        @foreach ($selectedForm->fields as $field)
                            <div>
                                <label for="field_{{ $field->field_key }}"
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    {{ $field->field_label ?: $field->field_key }}
                                    @if ($field->is_required)
                                        <span class="text-error-500">*</span>
                                    @endif
                                </label>
                                <input type="text" id="field_{{ $field->field_key }}" name="fields[{{ $field->field_key }}]"
                                    value="{{ old('fields.' . $field->field_key) }}"
                                    placeholder="Enter {{ strtolower($field->field_label ?: $field->field_key) }}"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            </div>
                        @endforeach

                        <div class="flex justify-end">
                            <button type="submit"
                                class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                                Submit Generation Request
                            </button>
                        </div>
                    </form>
                @endif
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
                                    Request
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Form
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Status
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Action
                                </th>
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
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $row['form_name'] }}
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