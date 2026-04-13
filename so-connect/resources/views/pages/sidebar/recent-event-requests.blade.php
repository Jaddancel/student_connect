@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Recent Event Requests" />

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
        <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Submitted Event Requests</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Track approval status of your organization event proposals.
                </p>
            </div>
            <span
                class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                {{ $rows->count() }} total
            </span>
        </div>

        @if ($rows->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No event requests submitted yet.</p>
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
                                Event
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Schedule
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-b border-gray-100 align-top dark:border-gray-800">
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <p class="font-medium">#{{ $row['request_id'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Submitted {{ \Illuminate\Support\Carbon::parse($row['requested_at'])->format('M d, Y h:i A') }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['organization_name'] }}</p>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <p class="font-medium">{{ $row['event_name'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['location'] }}</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['description'] }}</p>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <p>{{ \Illuminate\Support\Carbon::parse($row['start_time'])->format('M d, Y h:i A') }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        to {{ \Illuminate\Support\Carbon::parse($row['end_time'])->format('M d, Y h:i A') }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $row['status'] === 'approved' ? 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-400' : ($row['status'] === 'rejected' ? 'bg-error-100 text-error-700 dark:bg-error-500/15 dark:text-error-400' : 'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400') }}">
                                        {{ $row['status_label'] }}
                                    </span>
                                    @if ($row['approved_at'])
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Updated {{ \Illuminate\Support\Carbon::parse($row['approved_at'])->format('M d, Y h:i A') }}
                                        </p>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection