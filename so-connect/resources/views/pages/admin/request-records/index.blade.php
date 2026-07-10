@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Request Records" />

    @php
        $exportQuery = $orgId ? ['org_id' => $orgId] : [];
        $columns = [
            'user_id' => 'User ID',
            'org_id' => 'Org ID',
            'request_time' => 'Request Time',
            'request_type' => 'Request Type',
            'status' => 'Status',
        ];
        $statusAccent = [
            'accepted' => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400',
            'pending' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400',
            'rejected' => 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-400',
        ];
        $sortUrl = function ($column) use ($sort, $direction) {
            $nextDirection = ($sort === $column && $direction === 'asc') ? 'desc' : 'asc';

            return request()->fullUrlWithQuery(['sort' => $column, 'direction' => $nextDirection, 'page' => null]);
        };
    @endphp

    <div class="space-y-6">
        {{-- Filters + exports --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <form method="GET" action="{{ route('admin.request-records.index') }}" class="flex flex-wrap items-end gap-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Organization</label>
                        <select name="org_id" onchange="this.form.submit()"
                            class="dark:bg-dark-900 h-11 w-full max-w-sm rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90">
                            <option value="">All organizations</option>
                            @foreach ($organizations as $org)
                                <option value="{{ $org['organization_id'] }}" @selected($orgId == $org['organization_id'])>
                                    {{ $org['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.request-records.export.json', $exportQuery) }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Download JSON
                    </a>
                    <a href="{{ route('admin.request-records.export.print', $exportQuery) }}" target="_blank"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]">
                        Print / Save as PDF
                    </a>
                    <a href="{{ route('admin.request-records.export.xlsx', $exportQuery) }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-green-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-green-600">
                        Download Excel
                    </a>
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Only officer and member request records are included.</p>
        </div>

        {{-- Requests --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Requests</h3>
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $records->total() }} {{ Str::plural('record', $records->total()) }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            @foreach ($columns as $key => $label)
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                    <a href="{{ $sortUrl($key) }}"
                                        class="inline-flex items-center gap-1 transition hover:text-gray-700 dark:hover:text-gray-200">
                                        {{ $label }}
                                        @if ($sort === $key)
                                            <span aria-hidden="true">{{ $direction === 'asc' ? '▲' : '▼' }}</span>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">↕</span>
                                        @endif
                                    </a>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($records as $row)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                <td class="px-6 py-4 font-medium text-gray-800 dark:text-white/90">{{ $row['user_id'] }}</td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $row['org_id'] ?? '—' }}</td>
                                <td class="px-6 py-4 text-gray-500 dark:text-gray-400">{{ $row['request_time'] }}</td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $row['request_type'] }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusAccent[$row['status']] ?? '' }}">
                                        {{ ucfirst($row['status']) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                                    No request records.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($records->hasPages())
                <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">
                    {{ $records->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
