@extends('layouts.app')

@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $drafts */
    $statusLabels = [
        'preparing' => ['Preparing', 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'],
        'awaiting_scan' => ['Awaiting scan', 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300'],
        'parsing' => ['Reading', 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400'],
        'review' => ['Ready to review', 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400'],
        'failed' => ['Needs attention', 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400'],
    ];
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Drafts" />

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                Forms you started filling by hand. Resume to upload a completed scan or review what was read.
            </p>

            {{-- Filters --}}
            <form method="get" class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Form</label>
                    <select name="form_id" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
                        <option value="">All forms</option>
                        @foreach ($formOptions as $option)
                            <option value="{{ $option->id }}" @selected($filters['form_id'] === (int) $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] }}" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] }}" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90" />
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">Filter</button>
                    <a href="{{ route('manual.drafts') }}" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Reset</a>
                </div>
            </form>

            @if ($drafts->isEmpty())
                <p class="py-8 text-center text-sm text-gray-400 dark:text-gray-500">No drafts match these filters.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Form</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Started</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Updated</th>
                                <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($drafts as $draft)
                                @php [$label, $badge] = $statusLabels[$draft->status] ?? [$draft->status, 'bg-gray-100 text-gray-600']; @endphp
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-3 py-3 font-medium text-gray-800 dark:text-white/90">{{ $draft->form?->name ?? 'Form' }}</td>
                                    <td class="px-3 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $badge }}">{{ $label }}</span></td>
                                    <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $draft->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</td>
                                    <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $draft->updated_at?->diffForHumans() }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('manual.show', $draft) }}" class="rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-brand-600">Resume</a>
                                            @if ($draft->documents->count() > 1)
                                                @foreach ($draft->documents as $document)
                                                    @if ($document->partial_pdf_path)
                                                        <a href="{{ route('manual.documents.download', [$draft, $document]) }}" target="_blank" rel="noopener" aria-label="Download document {{ $loop->iteration }} PDF" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">PDF {{ $loop->iteration }}</a>
                                                    @endif
                                                @endforeach
                                            @elseif ($draft->partial_pdf_path || $draft->documents->first()?->partial_pdf_path)
                                                <a href="{{ route('manual.download', $draft) }}" target="_blank" rel="noopener" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">PDF</a>
                                            @endif
                                            <form method="post" action="{{ route('manual.destroy', $draft) }}" onsubmit="return confirm('Delete this draft?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="rounded-lg border border-error-300 px-3 py-1.5 text-xs font-medium text-error-600 transition hover:bg-error-50 dark:border-error-600 dark:text-error-400">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $drafts->links() }}</div>
            @endif
        </div>
    </div>
@endsection
