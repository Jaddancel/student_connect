@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Application for Recognition Requests" />

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($formMissing)
            <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm font-medium text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                Organization recognition form is not configured. Seed or publish the form to review requests.
            </div>
        @endif

        <div x-data="{
                page: 1, perPage: 10, total: {{ $pending->count() }},
                get pages() { return Math.max(1, Math.ceil(this.total / this.perPage)); },
                get visiblePages() { let s = Math.max(1, this.page - 1), e = Math.min(this.pages, this.page + 1); return Array.from({length: e - s + 1}, (_, i) => s + i); },
                onPage(i) { return i >= (this.page - 1) * this.perPage && i < this.page * this.perPage; },
                setPage(p) { this.page = Math.max(1, Math.min(this.pages, p)); },
                get from() { return this.total === 0 ? 0 : (this.page - 1) * this.perPage + 1; },
                get to() { return Math.min(this.page * this.perPage, this.total); }
            }" class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Pending Requests</h3>
                <span class="inline-flex items-center rounded-full bg-warning-50 px-2.5 py-0.5 text-xs font-medium text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">
                    {{ $pending->count() }}
                </span>
            </div>

            @if ($pending->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                    No pending recognition requests.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Organization</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Requester</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Type</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Submitted</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($pending as $row)
                                <tr x-show="onPage({{ $loop->index }})" class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-4 font-medium text-gray-800 dark:text-white/90">{{ $row['org_name'] }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $row['requester_name'] }}</td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                        @if ($row['recognition_type'] === 'c1')
                                            <span class="inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">New / Recognition</span>
                                        @elseif ($row['recognition_type'] === 'c2')
                                            <span class="inline-flex items-center rounded-full bg-purple-50 px-2 py-0.5 text-xs font-medium text-purple-700 dark:bg-purple-500/15 dark:text-purple-400">Renewal</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                        {{ \Illuminate\Support\Carbon::parse($row['request']->requested_at)->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('admin.recognition-requests.show', $row['request']->request_id) }}"
                                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                                            Review
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div x-show="pages > 1" x-cloak
                    class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 px-6 py-3 dark:border-gray-800">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Showing <span class="font-semibold text-gray-700 dark:text-gray-300" x-text="from"></span>–<span class="font-semibold text-gray-700 dark:text-gray-300" x-text="to"></span>
                        of <span class="font-semibold text-gray-700 dark:text-gray-300" x-text="total"></span>
                    </p>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="setPage(page - 1)" :disabled="page === 1"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:border-palette-lime hover:bg-palette-lime-pale hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:text-gray-400 dark:hover:border-palette-lime/40 dark:hover:bg-palette-lime/10 dark:hover:text-white">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg>
                        </button>
                        <span x-show="visiblePages[0] > 1" class="px-1 text-xs text-gray-400 dark:text-gray-600">…</span>
                        <template x-for="p in visiblePages" :key="p">
                            <button type="button" @click="setPage(p)"
                                :class="p === page ? 'bg-palette-lime text-gray-900 border-palette-lime' : 'border-gray-200 text-gray-600 hover:border-palette-lime hover:bg-palette-lime-pale dark:border-gray-700 dark:text-gray-400 dark:hover:border-palette-lime/40 dark:hover:bg-palette-lime/10 dark:hover:text-white'"
                                class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-lg border px-2 text-xs font-medium transition"
                                x-text="p"></button>
                        </template>
                        <span x-show="visiblePages[visiblePages.length - 1] < pages" class="px-1 text-xs text-gray-400 dark:text-gray-600">…</span>
                        <button type="button" @click="setPage(page + 1)" :disabled="page === pages"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:border-palette-lime hover:bg-palette-lime-pale hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:text-gray-400 dark:hover:border-palette-lime/40 dark:hover:bg-palette-lime/10 dark:hover:text-white">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6"/></svg>
                        </button>
                    </div>
                </div>
            @endif
        </div>

        @if ($decided->isNotEmpty())
            <div x-data="{
                    page: 1, perPage: 15, total: {{ $decided->count() }},
                    get pages() { return Math.max(1, Math.ceil(this.total / this.perPage)); },
                    get visiblePages() { let s = Math.max(1, this.page - 1), e = Math.min(this.pages, this.page + 1); return Array.from({length: e - s + 1}, (_, i) => s + i); },
                    onPage(i) { return i >= (this.page - 1) * this.perPage && i < this.page * this.perPage; },
                    setPage(p) { this.page = Math.max(1, Math.min(this.pages, p)); },
                    get from() { return this.total === 0 ? 0 : (this.page - 1) * this.perPage + 1; },
                    get to() { return Math.min(this.page * this.perPage, this.total); }
                }" class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Recent Decisions</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Organization</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Requester</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Type</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Decision</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Reason</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Decided At</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($decided as $row)
                                <tr x-show="onPage({{ $loop->index }})" class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-4 font-medium text-gray-800 dark:text-white/90">{{ $row['org_name'] }}</td>
                                    <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $row['requester_name'] }}</td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                        @if ($row['recognition_type'] === 'c1') New @elseif ($row['recognition_type'] === 'c2') Renewal @else — @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @if ($row['approval']->is_rejected)
                                            <span class="inline-flex items-center rounded-full bg-error-50 px-2.5 py-0.5 text-xs font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400">Rejected</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">Approved</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400">{{ $row['approval']->rejection_reason ?: '—' }}</td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                        {{ \Illuminate\Support\Carbon::parse($row['approval']->approved_at)->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('admin.recognition-requests.show', $row['request']->request_id) }}"
                                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div x-show="pages > 1" x-cloak
                    class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 px-6 py-3 dark:border-gray-800">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Showing <span class="font-semibold text-gray-700 dark:text-gray-300" x-text="from"></span>–<span class="font-semibold text-gray-700 dark:text-gray-300" x-text="to"></span>
                        of <span class="font-semibold text-gray-700 dark:text-gray-300" x-text="total"></span>
                    </p>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="setPage(page - 1)" :disabled="page === 1"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:border-palette-lime hover:bg-palette-lime-pale hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:text-gray-400 dark:hover:border-palette-lime/40 dark:hover:bg-palette-lime/10 dark:hover:text-white">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg>
                        </button>
                        <span x-show="visiblePages[0] > 1" class="px-1 text-xs text-gray-400 dark:text-gray-600">…</span>
                        <template x-for="p in visiblePages" :key="p">
                            <button type="button" @click="setPage(p)"
                                :class="p === page ? 'bg-palette-lime text-gray-900 border-palette-lime' : 'border-gray-200 text-gray-600 hover:border-palette-lime hover:bg-palette-lime-pale dark:border-gray-700 dark:text-gray-400 dark:hover:border-palette-lime/40 dark:hover:bg-palette-lime/10 dark:hover:text-white'"
                                class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-lg border px-2 text-xs font-medium transition"
                                x-text="p"></button>
                        </template>
                        <span x-show="visiblePages[visiblePages.length - 1] < pages" class="px-1 text-xs text-gray-400 dark:text-gray-600">…</span>
                        <button type="button" @click="setPage(page + 1)" :disabled="page === pages"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:border-palette-lime hover:bg-palette-lime-pale hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:text-gray-400 dark:hover:border-palette-lime/40 dark:hover:bg-palette-lime/10 dark:hover:text-white">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
