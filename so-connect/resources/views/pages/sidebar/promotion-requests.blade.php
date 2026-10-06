@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Account Creation Request" />

    <div class="space-y-6" x-data="{
        reviewOpen: false,
        current: null,
        open(row) { this.current = row; this.reviewOpen = true; },
        close() { this.reviewOpen = false; this.current = null; }
    }">

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
                                <th class="px-5 py-4 font-semibold text-gray-700 dark:text-gray-300">Type</th>
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
                                    <td class="px-5 py-4">
                                        @if(($row['display_type'] ?? '') === 'new_officer')
                                            <span class="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700 dark:bg-purple-500/15 dark:text-purple-400">New Officer</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">Role Change</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                        @if(($row['display_type'] ?? '') === 'new_officer')
                                            New → Officer
                                        @else
                                            {{ ucfirst($row['current_role']) }} → {{ ucfirst($row['requested_role']) }}
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        @if(($row['display_type'] ?? '') === 'new_officer')
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">Admin</span>
                                        @elseif($row['member_initiated'])
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
                                            <a href="{{ route('promotion-requests.show', $row['request_id']) }}"
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-brand-300 bg-brand-50 px-3 py-1.5 text-xs font-medium text-brand-700 transition hover:bg-brand-100 dark:border-brand-600/40 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/20">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                Review
                                            </a>

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

        {{-- Review Modal --}}
        <div x-show="reviewOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 p-4"
            @click.self="close()"
            @keydown.escape.window="close()">

            <div x-show="reviewOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl dark:bg-gray-900">

                {{-- Modal header --}}
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-800 dark:bg-gray-900">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white" x-text="current?.requester_name ?? '—'"></h2>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            <span x-text="current?.organization_name ?? ''"></span>
                            <template x-if="current?.display_type === 'role_change'">
                                <span> &mdash; <span x-text="current?.current_role ?? ''"></span> → <span x-text="current?.requested_role ?? ''"></span></span>
                            </template>
                        </p>
                    </div>
                    <button type="button" @click="close()" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                {{-- Modal body --}}
                <div class="px-6 py-5">

                    {{-- New Officer details --}}
                    <template x-if="current?.display_type === 'new_officer' && current?.review_payload">
                        <div class="space-y-5">

                            {{-- Photo + identity --}}
                            <div class="flex items-start gap-4">
                                <template x-if="current.review_payload.photo_url">
                                    <img :src="current.review_payload.photo_url" alt="Photo"
                                         @click="$store.lightbox.show(current.review_payload.photo_url, 'Photo')"
                                         class="h-24 w-20 shrink-0 cursor-zoom-in rounded-lg object-cover border border-gray-200 dark:border-gray-700" />
                                </template>
                                <template x-if="!current.review_payload.photo_url">
                                    <div class="flex h-24 w-20 shrink-0 items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 text-xs text-gray-400 dark:border-gray-700 dark:bg-gray-800">No photo</div>
                                </template>
                                <div class="min-w-0 flex-1 space-y-1.5 text-sm">
                                    <div><span class="font-medium text-gray-700 dark:text-gray-300">Email:</span> <span class="text-gray-600 dark:text-gray-400" x-text="current.review_payload.email"></span></div>
                                    <div><span class="font-medium text-gray-700 dark:text-gray-300">Position:</span> <span class="text-gray-600 dark:text-gray-400" x-text="current.review_payload.position"></span></div>
                                    <div><span class="font-medium text-gray-700 dark:text-gray-300">Organization:</span> <span class="text-gray-600 dark:text-gray-400" x-text="current.organization_name"></span></div>
                                    <div><span class="font-medium text-gray-700 dark:text-gray-300">Semester:</span> <span class="text-gray-600 dark:text-gray-400" x-text="(current.review_payload.semester ?? '') + ' — ' + (current.review_payload.school_year ?? '')"></span></div>
                                </div>
                            </div>

                            <div class="h-px bg-gray-100 dark:bg-gray-800"></div>

                            {{-- Basic info grid --}}
                            <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Age</p>
                                    <p class="mt-0.5 text-gray-800 dark:text-white/90" x-text="current.review_payload.age || '—'"></p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Sex</p>
                                    <p class="mt-0.5 text-gray-800 dark:text-white/90" x-text="current.review_payload.sex || '—'"></p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Birthday</p>
                                    <p class="mt-0.5 text-gray-800 dark:text-white/90" x-text="current.review_payload.birthday || '—'"></p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Birthplace</p>
                                    <p class="mt-0.5 text-gray-800 dark:text-white/90" x-text="current.review_payload.birthplace || '—'"></p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Nationality</p>
                                    <p class="mt-0.5 text-gray-800 dark:text-white/90" x-text="current.review_payload.nationality || '—'"></p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Religion</p>
                                    <p class="mt-0.5 text-gray-800 dark:text-white/90" x-text="current.review_payload.religious_affiliation || '—'"></p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Contact Number</p>
                                    <p class="mt-0.5 text-gray-800 dark:text-white/90" x-text="current.review_payload.contact_number || '—'"></p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Parents / Guardian</p>
                                    <p class="mt-0.5 text-gray-800 dark:text-white/90" x-text="current.review_payload.parents_guardian || '—'"></p>
                                </div>
                                <div class="col-span-2">
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Present Address</p>
                                    <p class="mt-0.5 text-gray-800 dark:text-white/90" x-text="current.review_payload.present_address || '—'"></p>
                                </div>
                                <div class="col-span-2">
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Home Address</p>
                                    <p class="mt-0.5 text-gray-800 dark:text-white/90" x-text="current.review_payload.home_address || '—'"></p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Course</p>
                                    <p class="mt-0.5 text-gray-800 dark:text-white/90" x-text="current.review_payload.course || '—'"></p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Year Level</p>
                                    <p class="mt-0.5 text-gray-800 dark:text-white/90" x-text="current.review_payload.year_level || '—'"></p>
                                </div>
                                <div class="col-span-2">
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Talents / Hobbies</p>
                                    <p class="mt-0.5 text-gray-800 dark:text-white/90" x-text="current.review_payload.talents_hobbies || '—'"></p>
                                </div>
                                <div class="col-span-2">
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Financial Support</p>
                                    <p class="mt-0.5 text-gray-800 dark:text-white/90"
                                        x-text="Array.isArray(current.review_payload.financial_support) ? current.review_payload.financial_support.join(', ') || '—' : (current.review_payload.financial_support || '—')"></p>
                                </div>
                                <div class="col-span-2" x-show="current.review_payload.faculty_advisers">
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Faculty Advisers</p>
                                    <p class="mt-0.5 text-gray-800 dark:text-white/90" x-text="current.review_payload.faculty_advisers"></p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Date Filed</p>
                                    <p class="mt-0.5 text-gray-800 dark:text-white/90" x-text="current.review_payload.date_filed || '—'"></p>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- Role Change details --}}
                    <template x-if="current?.display_type === 'role_change'">
                        <div class="space-y-4 text-sm">
                            <div class="grid grid-cols-2 gap-x-6 gap-y-3">
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Member</p>
                                    <p class="mt-0.5 text-gray-800 dark:text-white/90" x-text="current.requester_name"></p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Organization</p>
                                    <p class="mt-0.5 text-gray-800 dark:text-white/90" x-text="current.organization_name"></p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Current Role</p>
                                    <p class="mt-0.5 capitalize text-gray-800 dark:text-white/90" x-text="current.current_role"></p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Requested Role</p>
                                    <p class="mt-0.5 capitalize text-gray-800 dark:text-white/90" x-text="current.requested_role"></p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Source</p>
                                    <p class="mt-0.5 text-gray-800 dark:text-white/90" x-text="current.member_initiated ? 'Self-Request' : 'President'"></p>
                                </div>
                            </div>
                        </div>
                    </template>

                </div>

                {{-- Modal footer --}}
                <div class="sticky bottom-0 flex justify-end gap-3 border-t border-gray-200 bg-white px-6 py-4 dark:border-gray-800 dark:bg-gray-900">
                    <button type="button" @click="close()"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        Cancel
                    </button>
                    <button type="button"
                        @click="decideRequest(current.request_id, 'reject', $el); close()"
                        class="rounded-lg border border-error-300 px-4 py-2 text-sm font-medium text-error-600 transition hover:bg-error-50 dark:border-error-500/40 dark:text-error-400 dark:hover:bg-error-500/10">
                        Reject
                    </button>
                    <button type="button"
                        @click="decideRequest(current.request_id, 'approve', $el); close()"
                        class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600">
                        Approve
                    </button>
                </div>

            </div>
        </div>

    </div>

    <script>
        function decideRequest(requestId, decision, btn) {
            const rows = document.querySelectorAll('#promotion-requests-table tr[data-request-id="' + requestId + '"]');

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
                    window.location.reload();
                }
            })
            .catch(() => {
                alert('Request failed. Please try again.');
            });
        }
    </script>
@endsection
