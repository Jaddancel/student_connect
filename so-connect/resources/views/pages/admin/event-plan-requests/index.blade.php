@extends('layouts.app')

@section('content')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('epFilter', { org: '{{ $preselectedOrg > 0 ? $preselectedOrg : '' }}' });
        });
    </script>

    <x-common.page-breadcrumb pageTitle="Activity Requests" />

    <div class="space-y-4">

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

        {{-- Organization Filter --}}
        @if (!empty($orgGroups))
            <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-gray-200 bg-white px-5 py-3 dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                    </svg>
                    Organization
                </div>

                <div class="relative">
                    <select
                        x-data
                        x-model="$store.epFilter.org"
                        class="h-8 appearance-none rounded-lg border border-gray-200 bg-white py-0 pl-3 pr-8 text-sm text-gray-700 transition focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-400 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                        <option value="">All Organizations</option>
                        @foreach ($orgGroups as $category => $orgs)
                            <optgroup label="{{ $category }}">
                                @foreach ($orgs as $org)
                                    <option value="{{ $org['id'] }}">{{ $org['name'] }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-2.5 flex items-center">
                        <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </span>
                </div>

                {{-- Active filter chip --}}
                <div x-data x-show="$store.epFilter.org !== ''" x-cloak class="flex items-center gap-1.5">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 py-0.5 pl-2.5 pr-1.5 text-xs font-medium text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-300">
                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm0 2h12v1.586l-4.293 4.293A1 1 0 0011 11.586V15H9v-3.414a1 1 0 00-.293-.707L4 6.586V5z" clip-rule="evenodd"/>
                        </svg>
                        Filtered
                        <button
                            type="button"
                            @click="$store.epFilter.org = ''"
                            class="ml-0.5 rounded-full p-0.5 hover:bg-brand-100 dark:hover:bg-brand-500/20">
                            <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </span>
                </div>

            </div>
        @endif

        {{-- Pending Requests --}}
        <div x-data="{
                page: 1,
                perPage: 10,
                orgIds: {{ $pending->map(fn($r) => (int) $r['org_id'])->values()->toJson() }},
                init() {
                    this.$watch(() => Alpine.store('epFilter').org, () => { this.page = 1; });
                },
                get filteredIndices() {
                    let org = Alpine.store('epFilter').org;
                    if (!org) return Array.from({ length: this.orgIds.length }, (_, i) => i);
                    return this.orgIds.reduce((acc, id, i) => {
                        if (String(id) === String(org)) acc.push(i);
                        return acc;
                    }, []);
                },
                get total() { return this.filteredIndices.length; },
                get pages() { return Math.max(1, Math.ceil(this.total / this.perPage)); },
                get visiblePages() {
                    let s = Math.max(1, this.page - 1), e = Math.min(this.pages, this.page + 1);
                    return Array.from({ length: e - s + 1 }, (_, i) => s + i);
                },
                onPage(i) {
                    let idx = this.filteredIndices.indexOf(i);
                    return idx !== -1 && idx >= (this.page - 1) * this.perPage && idx < this.page * this.perPage;
                },
                setPage(p) { this.page = Math.max(1, Math.min(this.pages, p)); },
                get from() { return this.total === 0 ? 0 : (this.page - 1) * this.perPage + 1; },
                get to() { return Math.min(this.page * this.perPage, this.total); }
            }" class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">

            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Pending Requests</h3>
                <span class="inline-flex items-center rounded-full bg-warning-50 px-2.5 py-0.5 text-xs font-medium text-warning-700 dark:bg-warning-500/15 dark:text-warning-400"
                    x-text="total">{{ $pending->count() }}</span>
            </div>

            @if ($pending->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                    No pending event plan requests.
                </div>
            @else
                {{-- Empty filtered state --}}
                <div x-show="total === 0" x-cloak class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                    No pending requests match the selected organization.
                </div>

                <div x-show="total > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Organization</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Requester</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Plan Title</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Target Date</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Submitted</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($pending as $row)
                                <tr x-show="onPage({{ $loop->index }})" class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-4 font-medium text-gray-800 dark:text-white/90">{{ $row['org_name'] }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $row['requester_name'] }}</td>
                                    <td class="px-6 py-4 text-gray-800 dark:text-white/80">
                                        {{ $row['plan']?->title ?? '—' }}
                                        @if ($row['plan']?->isEventRequest())
                                            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                                {{ $row['plan']->event_location }}
                                                &middot; {{ $row['plan']->event_start_time?->format('M d, Y g:i A') }}
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                                        @if ($row['plan']?->target_date)
                                            {{ \Illuminate\Support\Carbon::parse($row['plan']->target_date)->format('M d, Y') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                        {{ \Illuminate\Support\Carbon::parse($row['request']->requested_at)->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('admin.event-plan-requests.show', $row['request']->request_id) }}"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-brand-400 hover:bg-brand-50 hover:text-brand-700 dark:border-gray-700 dark:text-gray-300 dark:hover:border-brand-500 dark:hover:bg-brand-500/10 dark:hover:text-brand-300">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
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

        {{-- Recent Decisions --}}
        @if ($decided->isNotEmpty())
            <div x-data="{
                    page: 1,
                    perPage: 15,
                    orgIds: {{ $decided->map(fn($r) => (int) $r['org_id'])->values()->toJson() }},
                    init() {
                        this.$watch(() => Alpine.store('epFilter').org, () => { this.page = 1; });
                    },
                    get filteredIndices() {
                        let org = Alpine.store('epFilter').org;
                        if (!org) return Array.from({ length: this.orgIds.length }, (_, i) => i);
                        return this.orgIds.reduce((acc, id, i) => {
                            if (String(id) === String(org)) acc.push(i);
                            return acc;
                        }, []);
                    },
                    get total() { return this.filteredIndices.length; },
                    get pages() { return Math.max(1, Math.ceil(this.total / this.perPage)); },
                    get visiblePages() {
                        let s = Math.max(1, this.page - 1), e = Math.min(this.pages, this.page + 1);
                        return Array.from({ length: e - s + 1 }, (_, i) => s + i);
                    },
                    onPage(i) {
                        let idx = this.filteredIndices.indexOf(i);
                        return idx !== -1 && idx >= (this.page - 1) * this.perPage && idx < this.page * this.perPage;
                    },
                    setPage(p) { this.page = Math.max(1, Math.min(this.pages, p)); },
                    get from() { return this.total === 0 ? 0 : (this.page - 1) * this.perPage + 1; },
                    get to() { return Math.min(this.page * this.perPage, this.total); }
                }" class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">

                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Recent Decisions</h3>
                    <span class="text-xs text-gray-400 dark:text-gray-500" x-text="total + ' result' + (total !== 1 ? 's' : '')"></span>
                </div>

                {{-- Empty filtered state --}}
                <div x-show="total === 0" x-cloak class="px-6 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                    No decided requests match the selected organization.
                </div>

                <div x-show="total > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Organization</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Plan Title</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Decision</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Reason</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Decided At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($decided as $row)
                                <tr x-show="onPage({{ $loop->index }})" class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-4 font-medium text-gray-800 dark:text-white/90">{{ $row['org_name'] }}</td>
                                    <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $row['plan']?->title ?? '—' }}</td>
                                    <td class="px-6 py-4">
                                        @if ($row['approval']->is_rejected)
                                            <span class="inline-flex items-center rounded-full bg-error-50 px-2.5 py-0.5 text-xs font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400">Rejected</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">Approved</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                        {{ $row['approval']->rejection_reason ?: '—' }}
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                        {{ \Illuminate\Support\Carbon::parse($row['approval']->approved_at)->format('M d, Y') }}
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
