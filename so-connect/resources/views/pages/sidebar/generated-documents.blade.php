@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Generated Documents" />

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Generated Document Files</h3>
                <span
                    class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    {{ $rows->count() }} entries
                </span>
            </div>

            @if (session('status'))
                <div
                    class="mb-4 rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/40 dark:bg-warning-500/10 dark:text-warning-400">
                    {{ session('status') }}
                </div>
            @endif

            @if ($rows->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No generated documents available yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Document
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Submission
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
                            @foreach ($rows as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <p class="font-medium">Generated #{{ (int) $row->generated_document_id }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $row->submission?->form?->name ?: 'Unknown Form' }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <p>Submission #{{ (int) $row->form_submission_id }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Request #{{ (int) ($row->request_id ?? 0) }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $row->status === 'generated' ? 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-400' : ($row->status === 'failed' ? 'bg-error-100 text-error-700 dark:bg-error-500/15 dark:text-error-400' : 'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400') }}">
                                            {{ ucfirst((string) $row->status) }}
                                        </span>
                                        @if ($row->failure_reason)
                                            <p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $row->failure_reason }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        @if ($row->status === 'generated' && $row->pdf_path)
                                            <a href="{{ route('generated-documents.download', ['generatedDocumentId' => (int) $row->generated_document_id]) }}"
                                                class="inline-flex rounded-lg bg-brand-500 px-3 py-2 text-xs font-medium text-white transition hover:bg-brand-600">
                                                Download PDF
                                            </a>
                                        @else
                                            <span class="text-xs text-gray-500 dark:text-gray-400">Not downloadable yet</span>
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
