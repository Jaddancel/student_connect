@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Request Records" />

    @php
        $exportQuery = $orgId ? ['org_id' => $orgId] : [];
        $sections = [
            ['key' => 'accepted', 'label' => 'Accepted', 'accent' => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400'],
            ['key' => 'pending', 'label' => 'Pending', 'accent' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400'],
            ['key' => 'rejected', 'label' => 'Rejected', 'accent' => 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-400'],
        ];
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

        {{-- Status tables --}}
        @foreach ($sections as $section)
            @php $rows = $records[$section['key']]; @endphp
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $section['accent'] }}">
                            {{ $section['label'] }}
                        </span>
                        <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Requests</h3>
                    </div>
                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ count($rows) }} {{ Str::plural('record', count($rows)) }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">User ID</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Org ID</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Request Time</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Request Type</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($rows as $row)
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-4 font-medium text-gray-800 dark:text-white/90">{{ $row['user_id'] }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $row['org_id'] ?? '—' }}</td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400">{{ $row['request_time'] }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $row['request_type'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                                        No {{ strtolower($section['label']) }} records.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
@endsection
