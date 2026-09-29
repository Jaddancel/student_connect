@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Membership Requests" />

    <div class="space-y-4">
        <div id="approval-request-feedback" class="hidden rounded-lg px-4 py-3 text-sm"></div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Membership Requests Requiring Review</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Review membership requests submitted to your organizations. Approved requests are forwarded to an admin for final approval.
                    </p>
                </div>
                <span
                    class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    {{ $rows->count() }} in queue
                </span>
            </div>

            @if ($rows->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No membership requests are currently assigned to you.</p>
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
                                    Requester's Organization
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
                                            {{ $row['requested_at']->timezone(config('app.display_timezone'))->format('M d, Y h:i A') }}
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
                                                @if (($row['action_type'] ?? 0) === 3 && ($row['form_id'] ?? 0) === $orgRecognitionFormId && $orgRecognitionFormId > 0)
                                                    <button type="button"
                                                        class="inline-flex items-center rounded-lg bg-success-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-success-700"
                                                        data-approval-action="approve-with-signatures"
                                                        data-request-id="{{ $row['request_id'] }}">
                                                        Approve
                                                    </button>
                                                @else
                                                    <button type="button"
                                                        class="inline-flex items-center rounded-lg bg-success-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-success-700"
                                                        data-approval-action="approve"
                                                        data-request-id="{{ $row['request_id'] }}">
                                                        Approve
                                                    </button>
                                                @endif
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

    {{-- ── ORG RECOGNITION APPROVAL MODAL ─────────────────────────────── --}}
    <div id="org-recognition-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900">
            <h3 class="mb-1 text-base font-semibold text-gray-800 dark:text-white/90">Approve Application for Recognition/Renewal</h3>
            <p class="mb-5 text-xs text-gray-500 dark:text-gray-400">Please fill in the approval details before confirming.</p>

            <div class="space-y-4">
                <div>
                    <label for="modal-chair" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Chair, Student Organizations <span class="text-error-500">*</span>
                    </label>
                    <input type="text" id="modal-chair" placeholder="Full name"
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                </div>
                <div>
                    <label for="modal-sig-chair" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Chair Signature (Recommending Approval) <span class="text-error-500">*</span>
                    </label>
                    <input type="file" id="modal-sig-chair" accept="image/jpeg,image/png"
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                </div>
                <div>
                    <label for="modal-director" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Director, Student Services and Development <span class="text-error-500">*</span>
                    </label>
                    <input type="text" id="modal-director" placeholder="Full name"
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                </div>
                <div>
                    <label for="modal-sig-director" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Director Signature (Approved) <span class="text-error-500">*</span>
                    </label>
                    <input type="file" id="modal-sig-director" accept="image/jpeg,image/png"
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                </div>

                <p id="modal-error" class="hidden text-xs text-error-500"></p>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" id="modal-cancel"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                    Cancel
                </button>
                <button type="button" id="modal-confirm"
                    class="rounded-lg bg-success-600 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-success-700">
                    Confirm Approval
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const feedback = document.getElementById('approval-request-feedback');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const modal = document.getElementById('org-recognition-modal');
            const modalCancel = document.getElementById('modal-cancel');
            const modalConfirm = document.getElementById('modal-confirm');
            const modalError = document.getElementById('modal-error');
            let pendingRequestId = null;
            let pendingButton = null;
            let pendingRow = null;

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

            const openModal = (requestId, button, row) => {
                pendingRequestId = requestId;
                pendingButton = button;
                pendingRow = row;
                document.getElementById('modal-chair').value = '';
                document.getElementById('modal-director').value = '';
                document.getElementById('modal-sig-chair').value = '';
                document.getElementById('modal-sig-director').value = '';
                modalError.classList.add('hidden');
                modalError.textContent = '';
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            };

            const closeModal = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                if (pendingButton) pendingButton.disabled = false;
                if (pendingRow) pendingRow.classList.remove('opacity-60');
                pendingRequestId = null;
                pendingButton = null;
                pendingRow = null;
            };

            modalCancel?.addEventListener('click', closeModal);

            modal?.addEventListener('click', (e) => {
                if (e.target === modal) closeModal();
            });

            modalConfirm?.addEventListener('click', async () => {
                const chair = document.getElementById('modal-chair').value.trim();
                const director = document.getElementById('modal-director').value.trim();
                const sigChair = document.getElementById('modal-sig-chair').files[0];
                const sigDirector = document.getElementById('modal-sig-director').files[0];

                if (!chair || !director || !sigChair || !sigDirector) {
                    modalError.textContent = 'All fields are required.';
                    modalError.classList.remove('hidden');
                    return;
                }

                modalConfirm.disabled = true;
                modalError.classList.add('hidden');

                const formData = new FormData();
                formData.append('chair', chair);
                formData.append('director', director);
                formData.append('signatureChair', sigChair);
                formData.append('signatureDirector', sigDirector);
                formData.append('_token', csrfToken);

                try {
                    const response = await fetch(`/api/requests/${pendingRequestId}/approve-with-signatures`, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body: formData,
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(payload?.message || 'Unable to submit approval.');
                    }

                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    setFeedback(payload?.message || 'Request approved successfully.');
                    window.location.reload();
                } catch (error) {
                    modalError.textContent = error instanceof Error ? error.message : 'Unable to submit approval.';
                    modalError.classList.remove('hidden');
                    modalConfirm.disabled = false;
                }
            });

            document.querySelectorAll('[data-approval-action]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const requestId = button.getAttribute('data-request-id');
                    const decision = button.getAttribute('data-approval-action');

                    if (!requestId || !decision) return;

                    const row = document.getElementById(`approval-row-${requestId}`);

                    if (decision === 'approve-with-signatures') {
                        row?.classList.add('opacity-60');
                        button.disabled = true;
                        openModal(requestId, button, row);
                        return;
                    }

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
