@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Promotion Requests" />

    <div class="space-y-6">

        {{-- Flash --}}
        @if(session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif
        @if(session('status'))
            <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm font-medium text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                {{ session('status') }}
            </div>
        @endif

        {{-- Stats --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Pending</p>
                <p class="mt-2 text-3xl font-bold text-warning-600 dark:text-warning-400">{{ $pendingCount }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Approved</p>
                <p class="mt-2 text-3xl font-bold text-success-600 dark:text-success-400">{{ $approvedCount }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Rejected</p>
                <p class="mt-2 text-3xl font-bold text-error-600 dark:text-error-400">{{ $rejectedCount }}</p>
            </div>
        </div>

        {{-- Requests Table --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            @if($rows->isEmpty())
                <div class="p-10 text-center text-sm text-gray-400 dark:text-gray-500">
                    No promotion requests found.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm" id="promotion-requests-table">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-5 py-4 font-semibold text-gray-700 dark:text-gray-300">Member</th>
                                <th class="px-5 py-4 font-semibold text-gray-700 dark:text-gray-300">Organization</th>
                                <th class="px-5 py-4 font-semibold text-gray-700 dark:text-gray-300">Promotion</th>
                                <th class="px-5 py-4 font-semibold text-gray-700 dark:text-gray-300">Source</th>
                                <th class="px-5 py-4 font-semibold text-gray-700 dark:text-gray-300">Submitted</th>
                                <th class="px-5 py-4 font-semibold text-gray-700 dark:text-gray-300">Status</th>
                                <th class="px-5 py-4 font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($rows as $row)
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                                    data-request-id="{{ $row['request_id'] }}">
                                    <td class="px-5 py-4 text-gray-800 dark:text-white/90 font-medium">{{ $row['requester_name'] }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $row['organization_name'] }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                        {{ ucfirst($row['current_role']) }} → {{ ucfirst($row['requested_role']) }}
                                    </td>
                                    <td class="px-5 py-4">
                                        @if($row['member_initiated'])
                                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-500/15 dark:text-blue-400">Self-Request</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">President</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-gray-500 dark:text-gray-400 text-xs">
                                        {{ \Illuminate\Support\Carbon::parse($row['requested_at'])->format('M d, Y') }}
                                    </td>
                                    <td class="px-5 py-4">
                                        @if($row['status'] === 'approved')
                                            <span class="inline-flex items-center rounded-full bg-success-100 px-2.5 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">Approved</span>
                                        @elseif($row['status'] === 'rejected')
                                            <span class="inline-flex items-center rounded-full bg-error-100 px-2.5 py-0.5 text-xs font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400">Rejected</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-warning-100 px-2.5 py-0.5 text-xs font-medium text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">Pending</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            @if($row['can_decide'])
                                                <button type="button"
                                                    onclick="decideRequest({{ $row['request_id'] }}, 'approve', this)"
                                                    class="inline-flex items-center rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-brand-600">
                                                    Approve
                                                </button>
                                                <button type="button"
                                                    onclick="decideRequest({{ $row['request_id'] }}, 'reject', this)"
                                                    class="inline-flex items-center rounded-lg border border-error-300 px-3 py-1.5 text-xs font-medium text-error-600 transition hover:bg-error-50 dark:border-error-500/40 dark:text-error-400 dark:hover:bg-error-500/10">
                                                    Reject
                                                </button>
                                            @endif

                                            @if($row['status'] === 'approved' && $row['submission_id'])
                                                <a href="{{ route('promotion-requests.confirmation', $row['submission_id']) }}"
                                                   class="inline-flex items-center rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800">
                                                    Review Draft
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>

    <script>
        function decideRequest(requestId, decision, btn) {
            if (!confirm(decision === 'approve' ? 'Approve this promotion request?' : 'Reject this promotion request?')) return;

            const row = btn.closest('tr');
            btn.disabled = true;
            btn.textContent = '...';

            fetch('/api/requests/' + requestId + '/decision', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ decision }),
            })
            .then(r => r.json())
            .then(data => {
                if (data.message) {
                    // Refresh the page to reflect new status and show "Review Draft" if applicable
                    window.location.reload();
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.textContent = decision === 'approve' ? 'Approve' : 'Reject';
                alert('Request failed. Please try again.');
            });
        }
    </script>
@endsection
