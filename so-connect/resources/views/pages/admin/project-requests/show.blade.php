@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Review Project Request" />

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
                            @if ($approval->rejection_reason) — {{ $approval->rejection_reason }}@endif
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
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Submitted By</p>
                    <p class="mt-1 text-gray-800 dark:text-white/90">{{ $requesterName }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Submitted At</p>
                    <p class="mt-1 text-gray-800 dark:text-white/90">{{ \Illuminate\Support\Carbon::parse($actionRequest->requested_at)->format('M d, Y h:i A') }}</p>
                </div>
            </div>
        </div>

        @if ($submission)
            @php $p = $submissionPayload; $isDonation = !empty($p['is_donation']); @endphp

            {{-- Section I: Project Type --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center gap-3 border-b border-gray-100 bg-gray-50/60 px-6 py-3.5 dark:border-gray-800 dark:bg-white/[0.02]">
                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">I. Project Type</h3>
                </div>
                <div class="px-6 py-5 text-sm">
                    <div class="flex items-center gap-3">
                        @if ($isDonation)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 ring-1 ring-brand-200 dark:bg-brand-500/10 dark:text-brand-300 dark:ring-brand-500/30">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                Donation
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 dark:bg-white/[0.06] dark:text-gray-300 dark:ring-white/10">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                Project
                            </span>
                        @endif
                    </div>

                    @if ($isDonation)
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            @if (!empty($p['donation_amount']))
                                <div>
                                    <p class="text-xs font-medium text-gray-400">Expected Donation Amount</p>
                                    <p class="mt-1 text-base font-semibold text-gray-800 dark:text-white/90">₱{{ number_format((float) $p['donation_amount'], 2) }}</p>
                                </div>
                            @endif
                            @if (!empty($p['in_kinds']))
                                <div class="sm:col-span-2">
                                    <p class="text-xs font-medium text-gray-400">Donated Materials</p>
                                    <ul class="mt-2 space-y-1.5">
                                        @foreach ((array) $p['in_kinds'] as $item)
                                            @if (trim((string) $item) !== '')
                                                <li class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-palette-lime"></span>
                                                    {{ $item }}
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            {{-- Section II: Project Details --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center gap-3 border-b border-gray-100 bg-gray-50/60 px-6 py-3.5 dark:border-gray-800 dark:bg-white/[0.02]">
                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">II. Project Details</h3>
                </div>
                <div class="grid grid-cols-1 gap-x-6 gap-y-5 px-6 py-5 text-sm sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <p class="text-xs font-medium text-gray-400">Project Title</p>
                        <p class="mt-1 text-base font-semibold text-gray-800 dark:text-white/90">{{ $p['projectTitle'] ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Nature of Project</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['natureOfProject'] ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Project Area</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['projectArea'] ?? '—' }}</p>
                    </div>
                </div>
            </div>

            {{-- Section III: Letter of Intent --}}
            @if (!empty($p['letterOfIntent']))
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex items-center gap-3 border-b border-gray-100 bg-gray-50/60 px-6 py-3.5 dark:border-gray-800 dark:bg-white/[0.02]">
                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">III. Letter of Intent</h3>
                    </div>
                    <div class="px-6 py-5">
                        <div class="rounded-xl border border-gray-100 bg-gray-50/60 px-5 py-4 dark:border-gray-800 dark:bg-white/[0.02]">
                            <p class="whitespace-pre-wrap text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $p['letterOfIntent'] }}</p>
                        </div>
                    </div>
                </div>
            @endif

        @else
            <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                Submission data not found.
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
                    @if ($approval->rejection_reason)
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $approval->rejection_reason }}</span>
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
                        <form method="POST" action="{{ route('admin.project-requests.decide', $actionRequest->request_id) }}">
                            @csrf
                            <input type="hidden" name="decision" value="approve" />
                            <button type="submit"
                                class="inline-flex items-center gap-2 rounded-lg bg-success-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-success-600 active:scale-[0.98]">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Approve
                            </button>
                        </form>

                        <div>
                            <button type="button" @click="rejectOpen = !rejectOpen"
                                :class="rejectOpen ? 'bg-error-50 border-error-400 text-error-700 dark:bg-error-900/30' : ''"
                                class="inline-flex items-center gap-2 rounded-lg border border-error-300 px-5 py-2.5 text-sm font-semibold text-error-600 transition hover:bg-error-50 dark:border-error-600 dark:text-error-400 dark:hover:bg-error-900/20">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                Reject
                            </button>
                            <div x-show="rejectOpen" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" class="mt-3 max-w-md">
                                <form method="POST" action="{{ route('admin.project-requests.decide', $actionRequest->request_id) }}" class="space-y-3">
                                    @csrf
                                    <input type="hidden" name="decision" value="reject" />
                                    <textarea name="rejection_reason" rows="3" placeholder="Reason for rejection (optional)"
                                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3.5 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-2 focus:ring-brand-500/10 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                                    <button type="submit"
                                        class="inline-flex items-center gap-2 rounded-lg bg-error-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-error-600 active:scale-[0.98]">
                                        Confirm Rejection
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="pb-2">
            <a href="{{ route('admin.project-requests.index') }}" class="inline-flex items-center gap-1.5 text-sm text-brand-500 hover:text-brand-600 hover:underline">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg>
                Back to list
            </a>
        </div>

    </div>
@endsection
