@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Approval Requests" />

    <div class="space-y-4">
        <div id="approval-request-feedback" class="hidden rounded-lg px-4 py-3 text-sm"></div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Requests Requiring Review</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Membership, event, and role-change requests scoped to your assigned organizations.
                    </p>
                </div>
                <span
                    class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    {{ $rows->count() }} in queue
                </span>
            </div>

            @if ($rows->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No requests are currently assigned to you.</p>
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
                                    Requester
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Organization
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Status
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr class="border-b border-gray-100 align-top dark:border-gray-800"
                                    id="approval-row-{{ $row['request_id'] }}">
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <p class="font-medium">#{{ $row['request_id'] }} · {{ $row['type_label'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['summary'] }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ \Illuminate\Support\Carbon::parse($row['requested_at'])->format('M d, Y h:i A') }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $row['requester_name'] }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $row['request_organization'] }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span id="approval-status-{{ $row['request_id'] }}"
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $row['status'] === 'approved' ? 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-400' : ($row['status'] === 'rejected' ? 'bg-error-100 text-error-700 dark:bg-error-500/15 dark:text-error-400' : 'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400') }}">
                                            {{ $row['status_label'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        @if ($row['can_decide'])
                                            <div class="flex items-center gap-2">
                                                <button type="button"
                                                    class="inline-flex items-center rounded-lg bg-success-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-success-700"
                                                    data-approval-action="approve"
                                                    data-request-id="{{ $row['request_id'] }}">
                                                    Approve
                                                </button>
                                                <button type="button"
                                                    class="inline-flex items-center rounded-lg bg-error-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-error-700"
                                                    data-approval-action="reject"
                                                    data-request-id="{{ $row['request_id'] }}">
                                                    Reject
                                                </button>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-500 dark:text-gray-400">No action required</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const feedback = document.getElementById('approval-request-feedback');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const setFeedback = (message, type = 'info') => {
                feedback.classList.remove('hidden', 'border', 'border-success-300', 'border-error-300',
                    'bg-success-50', 'bg-error-50', 'text-success-700', 'text-error-700');

                if (type === 'error') {
                    feedback.classList.add('border', 'border-error-300', 'bg-error-50', 'text-error-700');
                } else {
                    feedback.classList.add('border', 'border-success-300', 'bg-success-50', 'text-success-700');
                }

                feedback.textContent = message;
            };

            document.querySelectorAll('[data-approval-action]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const requestId = button.getAttribute('data-request-id');
                    const decision = button.getAttribute('data-approval-action');

                    if (!requestId || !decision) {
                        return;
                    }

                    const row = document.getElementById(`approval-row-${requestId}`);
                    row?.classList.add('opacity-60');
                    button.disabled = true;

                    try {
                        const response = await fetch(`/api/requests/${requestId}/decision`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({ decision }),
                        });

                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok) {
                            throw new Error(payload?.message || 'Unable to submit decision.');
                        }

                        setFeedback(payload?.message || 'Decision submitted successfully.');
                        window.location.reload();
                    } catch (error) {
                        setFeedback(error instanceof Error ? error.message : 'Unable to submit decision.', 'error');
                        button.disabled = false;
                        row?.classList.remove('opacity-60');
                    }
                });
            });
        });
    </script>
@endpush