@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Workplan Requests" />

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
                Workplan form is not configured. Seed or publish the form to review requests.
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Pending Requests</h3>
                <span class="inline-flex items-center rounded-full bg-warning-50 px-2.5 py-0.5 text-xs font-medium text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">
                    {{ $pending->count() }}
                </span>
            </div>

            @if ($pending->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                    No pending workplan requests.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Organization</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Requester</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">School Year</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Submitted</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($pending as $row)
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-4 font-medium text-gray-800 dark:text-white/90">{{ $row['org_name'] }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $row['requester_name'] }}</td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400">{{ $row['schoolyear'] ?: '—' }}</td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                        {{ \Illuminate\Support\Carbon::parse($row['request']->requested_at)->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('admin.workplan-requests.show', $row['request']->request_id) }}"
                                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                                            Review
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($decided->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Recent Decisions</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Organization</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Requester</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">School Year</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Decision</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Reason</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Decided At</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($decided as $row)
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-4 font-medium text-gray-800 dark:text-white/90">{{ $row['org_name'] }}</td>
                                    <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $row['requester_name'] }}</td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400">{{ $row['schoolyear'] ?: '—' }}</td>
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
                                        <a href="{{ route('admin.workplan-requests.show', $row['request']->request_id) }}"
                                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
