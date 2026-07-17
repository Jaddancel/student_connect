@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Action Logs" />

    @php
        $exportQuery = array_filter([
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'category' => $filters['category'] ?? null,
            'user' => $filters['user'] ?? null,
        ]);
    @endphp

    <div class="space-y-6">
        {{-- Filters --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Filters</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Search administrator actions by user, category, or date range.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('superadmin.action-logs.export.pdf', $exportQuery) }}"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]">
                        Download PDF
                    </a>
                    <a href="{{ route('superadmin.action-logs.export.xlsx', $exportQuery) }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-green-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-green-600">
                        Download Excel
                    </a>
                    <a href="{{ route('superadmin.action-logs.export.json', $exportQuery) }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Download JSON
                    </a>
                </div>
            </div>

            <form method="GET" action="{{ route('superadmin.action-logs.index') }}" x-ref="filterForm"
                x-data="{
                    modalOpen: false,
                    userValue: @js($filters['user'] ?? ''),
                    userLabel: @js($filters['user'] ?? ''),
                    q: '', results: [], loading: false, searched: false,
                    async search() {
                        if (this.q.trim().length < 2) { this.results = []; this.searched = false; return; }
                        this.loading = true;
                        try {
                            const res = await fetch(`/superadmin/profiles/search?admins=1&q=${encodeURIComponent(this.q)}`, { headers: { Accept: 'application/json' } });
                            const json = await res.json();
                            this.results = json.data || [];
                        } catch (e) { this.results = []; }
                        this.loading = false; this.searched = true;
                    },
                    pick(r) {
                        const name = [r.first_name, r.middle_name, r.last_name].filter(Boolean).join(' ');
                        this.userValue = r.user_email || name;
                        this.userLabel = name || r.user_email;
                        this.modalOpen = false;
                        this.$refs.filterForm.submit();
                    },
                    clearUser() { this.userValue = ''; this.userLabel = ''; this.$refs.filterForm.submit(); },
                }"
                class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <x-form.date-picker name="from" label="From" :defaultDate="$filters['from'] ?? null" placeholder="Start date" />
                <x-form.date-picker name="to" label="To" :defaultDate="$filters['to'] ?? null" placeholder="End date" />
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Category</label>
                    <select name="category"
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90">
                        <option value="">All categories</option>
                        @foreach ($categories as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['category'] ?? null) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">User</label>
                    <input type="hidden" name="user" :value="userValue" />
                    <div class="flex gap-1">
                        <button type="button" @click="modalOpen = true; $nextTick(() => $refs.q && $refs.q.focus())"
                            class="flex h-11 flex-1 items-center justify-between gap-2 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.06]">
                            <span class="truncate" x-text="userLabel || 'Users (All)'"></span>
                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <button type="button" x-show="userValue" x-cloak @click="clearUser()" title="Clear"
                            class="h-11 rounded-lg border border-gray-300 px-2.5 text-sm text-error-500 transition hover:bg-gray-50 dark:border-gray-700">✕</button>
                    </div>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit"
                        class="h-11 flex-1 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white transition hover:bg-brand-600">
                        Apply
                    </button>
                    <a href="{{ route('superadmin.action-logs.index') }}"
                        class="flex h-11 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.06]">
                        Reset
                    </a>
                </div>

                {{-- User search modal (Profile Manager suggestion pattern, admins only) --}}
                <div x-show="modalOpen" x-cloak class="fixed inset-0 z-[99999] flex items-start justify-center bg-black/40 p-4 pt-24 backdrop-blur-sm"
                    @keydown.escape.window="modalOpen = false" @click.self="modalOpen = false">
                    <div class="w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-800 dark:bg-gray-900">
                        <div class="mb-3 flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90">Filter by administrator</h4>
                            <button type="button" @click="modalOpen = false" class="text-gray-400 hover:text-gray-600">✕</button>
                        </div>
                        <input type="text" x-ref="q" x-model="q" @input.debounce.300ms="search()" placeholder="Type an admin's name…"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90" />
                        <div class="mt-3 max-h-72 space-y-1 overflow-y-auto">
                            <template x-if="loading"><p class="py-3 text-center text-xs text-gray-400">Searching…</p></template>
                            <template x-for="r in results" :key="r.profile_id">
                                <button type="button" @click="pick(r)"
                                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left transition hover:bg-gray-50 dark:hover:bg-white/[0.06]">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand-100 text-xs font-semibold text-brand-600 dark:bg-brand-500/20"
                                        x-text="((r.first_name||'')[0]||'') + ((r.last_name||'')[0]||'')"></span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm text-gray-800 dark:text-white/90" x-text="[r.first_name, r.middle_name, r.last_name].filter(Boolean).join(' ')"></span>
                                        <span class="block truncate text-xs text-gray-400" x-text="r.user_email || ''"></span>
                                    </span>
                                </button>
                            </template>
                            <template x-if="searched && !loading && results.length === 0">
                                <p class="py-3 text-center text-xs text-gray-400">No administrators match “<span x-text="q"></span>”.</p>
                            </template>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- Table --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Administrator Actions</h3>
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $logs->total() }} {{ Str::plural('record', $logs->total()) }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Time</th>
                            <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">User</th>
                            <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Category</th>
                            <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Action</th>
                            <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Description</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($logs as $log)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500 dark:text-gray-400">{{ $log['created_at'] }}</td>
                                <td class="px-6 py-4">
                                    <p class="font-medium text-gray-800 dark:text-white/90">{{ $log['name'] }}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ $log['user_email'] }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex whitespace-nowrap rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                                        {{ $log['category_label'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $log['action'] }}</td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                                    {{ $log['description'] }}
                                    @if ($log['meta'] !== [])
                                        <p class="mt-0.5 break-all text-xs text-gray-400 dark:text-gray-500">{{ json_encode($log['meta']) }}</p>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                                    No actions found for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
