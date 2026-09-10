@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Review Promotion Request" />

    <div class="mx-auto max-w-3xl space-y-5">

        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Status Banner --}}
        @if ($approval)
            @if ($approval->is_rejected)
                <div class="flex items-start gap-4 rounded-2xl border border-error-200 bg-error-50 px-5 py-4 dark:border-error-500/30 dark:bg-error-500/10">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-error-100 dark:bg-error-500/20">
                        <svg class="h-4.5 w-4.5 text-error-600 dark:text-error-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-error-800 dark:text-error-300">Rejected</p>
                        <p class="mt-0.5 text-xs text-error-600 dark:text-error-400">
                            Decided {{ \Illuminate\Support\Carbon::parse($approval->approved_at)->format('M d, Y') }}
                        </p>
                    </div>
                </div>
            @else
                <div class="flex items-start gap-4 rounded-2xl border border-success-200 bg-success-50 px-5 py-4 dark:border-success-500/30 dark:bg-success-500/10">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-success-100 dark:bg-success-500/20">
                        <svg class="h-4.5 w-4.5 text-success-600 dark:text-success-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-success-800 dark:text-success-300">Approved</p>
                        <p class="mt-0.5 text-xs text-success-600 dark:text-success-400">
                            Approved on {{ \Illuminate\Support\Carbon::parse($approval->approved_at)->format('M d, Y') }}
                        </p>
                    </div>
                </div>
            @endif
        @else
            <div class="flex items-start gap-4 rounded-2xl border border-warning-200 bg-warning-50 px-5 py-4 dark:border-warning-500/30 dark:bg-warning-500/10">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-warning-100 dark:bg-warning-500/20">
                    <svg class="h-4.5 w-4.5 text-warning-600 dark:text-warning-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-warning-800 dark:text-warning-300">Awaiting Review</p>
                    <p class="mt-0.5 text-xs text-warning-600 dark:text-warning-400">Review the full submission below before making a decision.</p>
                </div>
            </div>
        @endif

        {{-- Meta --}}
        <div class="rounded-2xl border border-gray-200 bg-white px-6 py-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Organization</p>
                    <p class="mt-1 font-medium text-gray-800 dark:text-white/90">{{ $orgName }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Applicant</p>
                    <p class="mt-1 text-gray-800 dark:text-white/90">{{ $requesterName }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Submitted At</p>
                    <p class="mt-1 text-gray-800 dark:text-white/90">{{ \Illuminate\Support\Carbon::parse($actionRequest->requested_at)->format('M d, Y h:i A') }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Type</p>
                    <p class="mt-1">
                        @if ($displayType === 'new_officer')
                            <span class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-700 dark:bg-purple-500/15 dark:text-purple-400">New Officer</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">Role Change</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Promotion</p>
                    <p class="mt-1 text-gray-800 dark:text-white/90">
                        {{ $displayType === 'new_officer' ? 'New → Officer' : (ucfirst($currentRole) . ' → ' . ucfirst($requestedRole)) }}
                    </p>
                </div>
                @if ($displayType === 'role_change')
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Source</p>
                        <p class="mt-1">
                            @if ($memberInitiated)
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-500/15 dark:text-blue-400">Self-Request</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">President</span>
                            @endif
                        </p>
                    </div>
                @endif
            </div>
        </div>

        @if ($displayType === 'new_officer' && $reviewPayload)
            @php $p = $reviewPayload; @endphp

            {{-- Section I: Identity --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center gap-3 border-b border-gray-100 bg-gray-50/60 px-6 py-3.5 dark:border-gray-800 dark:bg-white/[0.02]">
                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">I. Personal Identity</h3>
                </div>
                <div class="flex flex-col gap-5 px-6 py-5 sm:flex-row">
                    {{-- Photo --}}
                    <div class="shrink-0">
                        @if (!empty($p['photo_url']))
                            <x-admin.zoomable-image :src="$p['photo_url']" alt="Photo" label="Officer Photo"
                                 class="h-32 w-28 rounded-xl border border-gray-200 object-cover dark:border-gray-700" />
                        @else
                            <div class="flex h-32 w-28 items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 text-xs text-gray-400 dark:border-gray-700 dark:bg-gray-800">No photo</div>
                        @endif
                    </div>
                    {{-- Fields --}}
                    <div class="grid flex-1 grid-cols-2 gap-x-6 gap-y-4 text-sm">
                        <div class="col-span-2">
                            <p class="text-xs font-medium text-gray-400">Full Name</p>
                            <p class="mt-1 font-semibold text-gray-800 dark:text-white/90">
                                {{ trim(($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?: '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-400">Email</p>
                            <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['email'] ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-400">Position</p>
                            <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['position'] ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-400">Contact Number</p>
                            <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['contact_number'] ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-400">Semester / School Year</p>
                            <p class="mt-1 text-gray-800 dark:text-white/90">{{ trim(($p['semester'] ?? '') . ' — ' . ($p['school_year'] ?? '')) ?: '—' }}</p>
                        </div>
                        @if (!empty($p['student_id']))
                            <div>
                                <p class="text-xs font-medium text-gray-400">Student ID</p>
                                <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['student_id'] }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- ID Photos --}}
                    @if (!empty($p['id_photo_front_url']) || !empty($p['id_photo_back_url']))
                        <div class="mt-4 flex flex-wrap gap-4">
                            @if (!empty($p['id_photo_front_url']))
                                <div>
                                    <p class="mb-1.5 text-xs font-medium text-gray-400">Front of ID</p>
                                    <x-admin.zoomable-image :src="$p['id_photo_front_url']" alt="Front of ID"
                                         class="h-24 w-36 rounded-lg border border-gray-200 object-cover dark:border-gray-700" />
                                </div>
                            @endif
                            @if (!empty($p['id_photo_back_url']))
                                <div>
                                    <p class="mb-1.5 text-xs font-medium text-gray-400">Back of ID</p>
                                    <x-admin.zoomable-image :src="$p['id_photo_back_url']" alt="Back of ID"
                                         class="h-24 w-36 rounded-lg border border-gray-200 object-cover dark:border-gray-700" />
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            {{-- Section II: Personal Details --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center gap-3 border-b border-gray-100 bg-gray-50/60 px-6 py-3.5 dark:border-gray-800 dark:bg-white/[0.02]">
                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">II. Personal Details</h3>
                </div>
                <div class="grid grid-cols-2 gap-x-6 gap-y-4 px-6 py-5 text-sm">
                    <div>
                        <p class="text-xs font-medium text-gray-400">Age</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['age'] ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Sex</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['sex'] ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Birthday</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['birthday'] ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Birthplace</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['birthplace'] ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Nationality</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['nationality'] ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Religion</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['religious_affiliation'] ?: '—' }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs font-medium text-gray-400">Present Address</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['present_address'] ?: '—' }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs font-medium text-gray-400">Home Address</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['home_address'] ?: '—' }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs font-medium text-gray-400">Parents / Guardian</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['parents_guardian'] ?: '—' }}</p>
                    </div>
                </div>
            </div>

            {{-- Section III: Academic & Background --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center gap-3 border-b border-gray-100 bg-gray-50/60 px-6 py-3.5 dark:border-gray-800 dark:bg-white/[0.02]">
                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">III. Academic & Background</h3>
                </div>
                <div class="grid grid-cols-2 gap-x-6 gap-y-4 px-6 py-5 text-sm">
                    <div>
                        <p class="text-xs font-medium text-gray-400">Course</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['course'] ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Year Level</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['year_level'] ?: '—' }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs font-medium text-gray-400">Talents / Hobbies</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['talents_hobbies'] ?: '—' }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs font-medium text-gray-400">Financial Support</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">
                            @php
                                $fs = is_array($p['financial_support']) ? $p['financial_support'] : (array) $p['financial_support'];
                            @endphp
                            {{ implode(', ', array_filter($fs)) ?: '—' }}
                        </p>
                    </div>
                    @if (!empty($p['scholar_provider']))
                        <div>
                            <p class="text-xs font-medium text-gray-400">Scholar Provider</p>
                            <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['scholar_provider'] }}</p>
                        </div>
                    @endif
                    @if (!empty($p['others_specify']))
                        <div>
                            <p class="text-xs font-medium text-gray-400">Others (specify)</p>
                            <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['others_specify'] }}</p>
                        </div>
                    @endif
                    @if (!empty($p['faculty_advisers']))
                        <div class="col-span-2">
                            <p class="text-xs font-medium text-gray-400">Faculty Advisers</p>
                            <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['faculty_advisers'] }}</p>
                        </div>
                    @endif
                    @if (!empty($p['date_filed']))
                        <div>
                            <p class="text-xs font-medium text-gray-400">Date Filed</p>
                            <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['date_filed'] }}</p>
                        </div>
                    @endif
                </div>
            </div>

        @elseif ($displayType === 'role_change')

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center gap-3 border-b border-gray-100 bg-gray-50/60 px-6 py-3.5 dark:border-gray-800 dark:bg-white/[0.02]">
                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Role Change Details</h3>
                </div>
                <div class="grid grid-cols-2 gap-x-6 gap-y-4 px-6 py-5 text-sm">
                    <div>
                        <p class="text-xs font-medium text-gray-400">Current Role</p>
                        <p class="mt-1 capitalize text-gray-800 dark:text-white/90">{{ $currentRole }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Requested Role</p>
                        <p class="mt-1 capitalize text-gray-800 dark:text-white/90">{{ $requestedRole }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Initiated By</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $memberInitiated ? 'Member (Self-Request)' : 'President' }}</p>
                    </div>
                </div>
            </div>

        @endif

        {{-- Decision Panel --}}
        @if ($approval)
            <div class="rounded-2xl border border-gray-200 bg-white px-6 py-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Decision</p>
                <div class="mt-3 flex items-center gap-3">
                    @if ($approval->is_rejected)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-error-50 px-3 py-1.5 text-sm font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            Rejected
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-success-50 px-3 py-1.5 text-sm font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Approved
                        </span>
                    @endif
                </div>
            </div>
        @else
            <div x-data="{ rejectOpen: false }" class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 bg-gray-50/60 px-6 py-3.5 dark:border-gray-800 dark:bg-white/[0.02]">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Make a Decision</p>
                </div>
                <div class="px-6 py-5">
                    <div class="flex flex-wrap gap-3">
                        <button type="button"
                            onclick="decideRequest({{ $actionRequest->request_id }}, 'approve')"
                            class="inline-flex items-center gap-2 rounded-lg bg-success-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-success-600 active:scale-[0.98]">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Approve
                        </button>

                        <div>
                            <button type="button" @click="rejectOpen = !rejectOpen"
                                :class="rejectOpen ? 'bg-error-50 border-error-400 text-error-700 dark:bg-error-900/30' : ''"
                                class="inline-flex items-center gap-2 rounded-lg border border-error-300 px-5 py-2.5 text-sm font-semibold text-error-600 transition hover:bg-error-50 dark:border-error-600 dark:text-error-400 dark:hover:bg-error-900/20">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                Reject
                            </button>
                            <div x-show="rejectOpen" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" class="mt-3 max-w-md">
                                <div class="space-y-3">
                                    <textarea id="rejection-reason" rows="3" placeholder="Reason for rejection (optional)"
                                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3.5 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-2 focus:ring-brand-500/10 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                                    <button type="button"
                                        onclick="decideRequest({{ $actionRequest->request_id }}, 'reject')"
                                        class="inline-flex items-center gap-2 rounded-lg bg-error-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-error-600 active:scale-[0.98]">
                                        Confirm Rejection
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="pb-2">
            <a href="{{ route('promotion-requests') }}" class="inline-flex items-center gap-1.5 text-sm text-brand-500 hover:text-brand-600 hover:underline">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg>
                Back to list
            </a>
        </div>

    </div>

    <script>
        function decideRequest(requestId, decision) {
            const reason = document.getElementById('rejection-reason')?.value ?? '';

            fetch('/api/requests/' + requestId + '/decision', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ decision, rejection_reason: reason }),
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
