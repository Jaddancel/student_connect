@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Review Event Plan" />

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
                    <p class="mt-0.5 text-xs text-warning-600 dark:text-warning-400">Review the full event plan below before making a decision.</p>
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

        @if ($plan)

            {{-- Section I: Plan Details --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center gap-3 border-b border-gray-100 bg-gray-50/60 px-6 py-3.5 dark:border-gray-800 dark:bg-white/[0.02]">
                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">I. Plan Details</h3>
                </div>
                <div class="grid grid-cols-1 gap-x-6 gap-y-5 px-6 py-5 text-sm sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <p class="text-xs font-medium text-gray-400">Title</p>
                        <p class="mt-1 text-base font-semibold text-gray-800 dark:text-white/90">{{ $plan->title ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Target Date</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">
                            {{ $plan->target_date ? \Illuminate\Support\Carbon::parse($plan->target_date)->format('M d, Y') : '—' }}
                        </p>
                    </div>
                    @if ($plan->purpose_of_activity)
                        <div class="sm:col-span-2">
                            <p class="text-xs font-medium text-gray-400">Purpose of Activity</p>
                            <p class="mt-1 whitespace-pre-wrap leading-relaxed text-gray-800 dark:text-white/90">{{ $plan->purpose_of_activity }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Section II: Event Details (only if it's an event request) --}}
            @if ($plan->isEventRequest())
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex items-center gap-3 border-b border-gray-100 bg-gray-50/60 px-6 py-3.5 dark:border-gray-800 dark:bg-white/[0.02]">
                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">II. Event Details</h3>
                    </div>
                    <div class="grid grid-cols-1 gap-x-6 gap-y-5 px-6 py-5 text-sm sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <p class="text-xs font-medium text-gray-400">Location / Venue</p>
                            <p class="mt-1 text-gray-800 dark:text-white/90">{{ $plan->event_location ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-400">Start Time</p>
                            <p class="mt-1 text-gray-800 dark:text-white/90">
                                {{ $plan->event_start_time ? $plan->event_start_time->format('M d, Y g:i A') : '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-400">End Time</p>
                            <p class="mt-1 text-gray-800 dark:text-white/90">
                                {{ $plan->event_end_time ? $plan->event_end_time->format('M d, Y g:i A') : '—' }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Section III: Activity Information --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center gap-3 border-b border-gray-100 bg-gray-50/60 px-6 py-3.5 dark:border-gray-800 dark:bg-white/[0.02]">
                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $plan->isEventRequest() ? 'III' : 'II' }}. Activity Information</h3>
                </div>
                <div class="grid grid-cols-1 gap-x-6 gap-y-5 px-6 py-5 text-sm sm:grid-cols-2">
                    @if (!empty($plan->activity_types))
                        <div class="sm:col-span-2">
                            <p class="text-xs font-medium text-gray-400">Activity Type(s)</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ((array) $plan->activity_types as $type)
                                    @if (trim((string) $type) !== '')
                                        <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-white/[0.08] dark:text-gray-300">{{ $type }}</span>
                                    @endif
                                @endforeach
                                @if (!empty($plan->activity_types_other))
                                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-white/[0.08] dark:text-gray-300">{{ $plan->activity_types_other }}</span>
                                @endif
                            </div>
                        </div>
                    @endif
                    <div>
                        <p class="text-xs font-medium text-gray-400">Area / Scope</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">
                            {{ $plan->area_scope ?? '—' }}
                            @if (!empty($plan->area_scope_other)) ({{ $plan->area_scope_other }})@endif
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Sponsor</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">
                            {{ $plan->sponsor ?? '—' }}
                            @if (!empty($plan->sponsor_other)) ({{ $plan->sponsor_other }})@endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- Section IV: Organizer --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center gap-3 border-b border-gray-100 bg-gray-50/60 px-6 py-3.5 dark:border-gray-800 dark:bg-white/[0.02]">
                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $plan->isEventRequest() ? 'IV' : 'III' }}. Organizer</h3>
                </div>
                <div class="grid grid-cols-1 gap-x-6 gap-y-5 px-6 py-5 text-sm sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-medium text-gray-400">President</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $plan->president_name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">President Contact</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $plan->president_contact ?? '—' }}</p>
                    </div>
                    @if (!empty($plan->faculty_advisers))
                        <div class="sm:col-span-2">
                            <p class="text-xs font-medium text-gray-400">Faculty Adviser(s)</p>
                            <ul class="mt-2 space-y-1.5">
                                @foreach ((array) $plan->faculty_advisers as $adviser)
                                    @if (trim((string) $adviser) !== '')
                                        <li class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-palette-lime"></span>
                                            {{ $adviser }}
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

        @else
            <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                Event plan data not found.
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
                        <form method="POST" action="{{ route('admin.event-plan-requests.decide', $actionRequest->request_id) }}">
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
                                <form method="POST" action="{{ route('admin.event-plan-requests.decide', $actionRequest->request_id) }}" class="space-y-3">
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
            <a href="{{ route('admin.event-plan-requests.index') }}" class="inline-flex items-center gap-1.5 text-sm text-brand-500 hover:text-brand-600 hover:underline">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg>
                Back to list
            </a>
        </div>

    </div>
@endsection
