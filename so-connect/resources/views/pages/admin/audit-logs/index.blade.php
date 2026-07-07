@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Audit Logs" />

    @php
        $exportQuery = array_filter([
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'org_id' => $filters['org_id'] ?? null,
            'name' => $filters['name'] ?? null,
        ]);
    @endphp

    <div class="space-y-6">
        {{-- Filters --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Filters</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Narrow the login / logout activity by date, organization, or name.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.audit-logs.export.json', $exportQuery) }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Download JSON
                    </a>
                    <a href="{{ route('admin.audit-logs.export.print', $exportQuery) }}" target="_blank"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]">
                        Print / Save as PDF
                    </a>
                    <a href="{{ route('admin.audit-logs.export.xlsx', $exportQuery) }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-green-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-green-600">
                        Download Excel
                    </a>
                </div>
            </div>

            <form method="GET" action="{{ route('admin.audit-logs.index') }}"
                class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <x-form.date-picker name="from" label="From" :defaultDate="$filters['from'] ?? null" placeholder="Start date" />
                <x-form.date-picker name="to" label="To" :defaultDate="$filters['to'] ?? null" placeholder="End date" />
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Organization</label>
                    <select name="org_id"
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90">
                        <option value="">All organizations</option>
                        @foreach ($organizations as $org)
                            <option value="{{ $org['organization_id'] }}" @selected(($filters['org_id'] ?? null) == $org['organization_id'])>
                                {{ $org['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Name / Email</label>
                    <input type="text" name="name" value="{{ $filters['name'] ?? '' }}" placeholder="Search name or email"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90" />
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit"
                        class="h-11 flex-1 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white transition hover:bg-brand-600">
                        Apply
                    </button>
                    <a href="{{ route('admin.audit-logs.index') }}"
                        class="flex h-11 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.06]">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        {{-- Table --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Login / Logout Activity</h3>
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $logs->total() }} {{ Str::plural('record', $logs->total()) }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Email</th>
                            <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Name</th>
                            <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Interaction</th>
                            <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Logged At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($logs as $log)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $log['user_email'] }}</td>
                                <td class="px-6 py-4 font-medium text-gray-800 dark:text-white/90">{{ $log['name'] }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $log['interaction'] === 'LOGIN' ? 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' : 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300' }}">
                                        {{ $log['interaction'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-500 dark:text-gray-400">{{ $log['logged_at'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                                    No activity found for the selected filters.
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
