@extends('layouts.app')

@section('content')
    @php
        $attentionCount = $pendingRequestCount + $openDraftCount + $unfiledAfterEvents->count();
        $statusClasses = [
            'pending' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
            'approved' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
            'rejected' => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
        ];
    @endphp

    <div class="space-y-6">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-brand-600 dark:text-brand-400">Student workspace</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white sm:text-3xl">
                    Welcome back, {{ $studentName }}
                </h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Review what needs your attention and continue your organization work.
                </p>
            </div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ now()->format('l, F j, Y') }}
            </p>
        </header>

        @if ($organization)
            <section aria-labelledby="organization-dashboard-title"
                x-data="organizationDashboard(@js($semesterEnd?->toIso8601String()))"
                class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div aria-hidden="true" class="pointer-events-none absolute inset-0"
                    :style="'background: linear-gradient(115deg, rgba(' + themeColor + ', 0.20), rgba(' + themeColor + ', 0.03) 65%)'"></div>
                <div class="relative grid gap-5 p-5 lg:grid-cols-[1fr_auto] lg:items-center lg:p-6">
                    <div class="flex min-w-0 items-center gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-white/70 bg-white shadow-theme-xs dark:border-gray-700 dark:bg-gray-800 sm:h-20 sm:w-20">
                            @if ($organizationLogo)
                                <img x-ref="logo" src="{{ $organizationLogo }}" alt="{{ $organizationDetail?->name }} logo"
                                    class="h-full w-full object-contain" @load="applyLogoTheme($event.target)"
                                    x-on:error="themeError = true">
                            @else
                                <span class="text-xl font-bold text-gray-700 dark:text-gray-200">
                                    {{ $organizationDetail?->initials ?: 'ORG' }}
                                </span>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 id="organization-dashboard-title"
                                    class="truncate text-lg font-semibold text-gray-900 dark:text-white sm:text-xl">
                                    {{ $organizationDetail?->name ?? 'Organization' }}
                                </h2>
                                <span class="rounded-full bg-white/80 px-2.5 py-1 text-xs font-semibold text-gray-700 ring-1 ring-inset ring-gray-200 dark:bg-gray-800/80 dark:text-gray-200 dark:ring-gray-700">
                                    {{ $organizationRole }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                @if ($organization->created_at)
                                    Founded on {{ $organization->created_at->format('F j, Y') }}.
                                @else
                                    Registration date unavailable.
                                @endif
                                {{ $organization->officer_count }} {{ \Illuminate\Support\Str::plural('officer', $organization->officer_count) }}.
                            </p>
                            <p x-cloak x-show="themeError" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Logo theme unavailable.
                            </p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-white/70 bg-white/80 px-4 py-3 backdrop-blur-sm dark:border-gray-700 dark:bg-gray-800/80 lg:min-w-64">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Current semester</p>
                        <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-100" x-text="semesterText">
                            @if ($semesterDaysRemaining === null)
                                Semester end date not configured.
                            @elseif ($semesterEnd->isPast())
                                Semester has ended.
                            @elseif ($semesterDaysRemaining <= 7)
                                Time until semester end: {{ $semesterDaysRemaining }} days
                            @else
                                Days until semester end: {{ $semesterDaysRemaining }}
                            @endif
                        </p>
                    </div>
                </div>
            </section>
        @else
            <section class="rounded-2xl border border-dashed border-gray-300 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">No organization selected</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Your organization details and organization-specific tasks will appear here after membership is assigned.
                </p>
            </section>
        @endif

        <div class="grid grid-cols-12 gap-4 md:gap-6">
            <section aria-labelledby="attention-title"
                class="col-span-12 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] lg:col-span-8">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800 lg:px-6">
                    <div>
                        <h2 id="attention-title" class="text-base font-semibold text-gray-900 dark:text-white">Needs your attention</h2>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Your most important pending work</p>
                    </div>
                    <span class="inline-flex min-w-8 items-center justify-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $attentionCount > 0 ? 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400' : 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400' }}">
                        {{ $attentionCount }}
                    </span>
                </div>

                @if ($attentionCount === 0)
                    <div class="flex items-start gap-3 p-5 lg:p-6">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">You are all caught up</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">There are no pending submissions, open drafts, or due reports.</p>
                        </div>
                    </div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @if ($unfiledAfterEvents->isNotEmpty())
                            <a href="{{ route('after-event-reports.index') }}" class="group flex items-center gap-4 px-5 py-4 transition hover:bg-gray-50 dark:hover:bg-white/[0.03] lg:px-6">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.8 2.6 17.2A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.8L13.7 3.8a2 2 0 0 0-3.4 0Z" />
                                    </svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">After-event reports due</span>
                                    <span class="mt-0.5 block truncate text-xs text-gray-500 dark:text-gray-400">
                                        {{ $unfiledAfterEvents->count() === 1 ? '1 event requires a report' : $unfiledAfterEvents->count().' events require reports' }}
                                    </span>
                                </span>
                                <span class="text-sm font-semibold text-error-600 dark:text-error-400">{{ $unfiledAfterEvents->count() }}</span>
                            </a>
                        @endif

                        @if ($openDraftCount > 0)
                            <a href="{{ route('manual.drafts') }}" class="group flex items-center gap-4 px-5 py-4 transition hover:bg-gray-50 dark:hover:bg-white/[0.03] lg:px-6">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 19.5V5a2 2 0 0 1 2-2h8l6 6v10.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 19.5Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v6h6M8 14h8M8 17h5" />
                                    </svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">Continue open drafts</span>
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">Finish forms you started earlier</span>
                                </span>
                                <span class="text-sm font-semibold text-brand-600 dark:text-brand-400">{{ $openDraftCount }}</span>
                            </a>
                        @endif

                        @if ($pendingRequestCount > 0)
                            <div class="flex items-center gap-4 px-5 py-4 lg:px-6">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2.5 2.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">Submissions awaiting review</span>
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">OSSD has not decided on these submissions yet</span>
                                </span>
                                <span class="text-sm font-semibold text-warning-600 dark:text-warning-400">{{ $pendingRequestCount }}</span>
                            </div>
                        @endif
                    </div>
                @endif
            </section>

            <section aria-labelledby="quick-actions-title"
                class="col-span-12 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] lg:col-span-4 lg:p-6">
                <h2 id="quick-actions-title" class="text-base font-semibold text-gray-900 dark:text-white">Quick actions</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Go directly to common tasks</p>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    @if ($canSubmitForms)
                        <a href="{{ route('forms.directory') }}" class="rounded-xl border border-gray-200 p-3 transition hover:border-brand-300 hover:bg-brand-50/50 dark:border-gray-700 dark:hover:border-brand-500/40 dark:hover:bg-brand-500/5">
                            <svg class="h-5 w-5 text-brand-600 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 3h9l4 4v14H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5M8 13h8M8 17h5"/></svg>
                            <span class="mt-2 block text-sm font-semibold text-gray-800 dark:text-gray-100">Browse forms</span>
                        </a>
                        <a href="{{ route('event-plans') }}" class="rounded-xl border border-gray-200 p-3 transition hover:border-brand-300 hover:bg-brand-50/50 dark:border-gray-700 dark:hover:border-brand-500/40 dark:hover:bg-brand-500/5">
                            <svg class="h-5 w-5 text-brand-600 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3v3m8-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m9 15 2 2 4-5"/></svg>
                            <span class="mt-2 block text-sm font-semibold text-gray-800 dark:text-gray-100">Event plans</span>
                        </a>
                    @endif
                    <a href="{{ route('manual.drafts') }}" class="rounded-xl border border-gray-200 p-3 transition hover:border-brand-300 hover:bg-brand-50/50 dark:border-gray-700 dark:hover:border-brand-500/40 dark:hover:bg-brand-500/5">
                        <svg class="h-5 w-5 text-brand-600 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 4h14v16H5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 8h8M8 12h8M8 16h5"/></svg>
                        <span class="mt-2 block text-sm font-semibold text-gray-800 dark:text-gray-100">My drafts</span>
                    </a>
                    <a href="{{ route('calendar') }}" class="rounded-xl border border-gray-200 p-3 transition hover:border-brand-300 hover:bg-brand-50/50 dark:border-gray-700 dark:hover:border-brand-500/40 dark:hover:bg-brand-500/5">
                        <svg class="h-5 w-5 text-brand-600 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3v3m8-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z"/></svg>
                        <span class="mt-2 block text-sm font-semibold text-gray-800 dark:text-gray-100">Calendar</span>
                    </a>
                </div>
            </section>

            <section aria-labelledby="upcoming-events-title"
                class="col-span-12 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] xl:col-span-7">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800 lg:px-6">
                    <div>
                        <h2 id="upcoming-events-title" class="text-base font-semibold text-gray-900 dark:text-white">Upcoming events</h2>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Your organization schedule</p>
                    </div>
                    <a href="{{ route('calendar') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">View calendar</a>
                </div>

                @if ($upcomingEvents->isEmpty())
                    <div class="flex flex-col items-center justify-center px-5 py-12 text-center">
                        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3v3m8-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z"/></svg>
                        </span>
                        <p class="mt-3 text-sm font-medium text-gray-600 dark:text-gray-300">No upcoming events</p>
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">New organization events will appear here.</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($upcomingEvents as $event)
                            @php $start = \Illuminate\Support\Carbon::parse($event->start_time); @endphp
                            <a href="{{ route('calendar', ['event' => $event->event_id]) }}" class="flex gap-4 px-5 py-4 transition hover:bg-gray-50 dark:hover:bg-white/[0.03] lg:px-6">
                                <span class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">
                                    <span class="text-[10px] font-bold uppercase leading-none">{{ $start->format('M') }}</span>
                                    <span class="mt-1 text-lg font-bold leading-none">{{ $start->format('d') }}</span>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $event->event_name }}</span>
                                    <span class="mt-1 block truncate text-xs text-gray-500 dark:text-gray-400">{{ $start->format('g:i A') }} · {{ $event->event_location ?: 'Location to be announced' }}</span>
                                    <span class="mt-1 block truncate text-xs text-gray-400 dark:text-gray-500">{{ $event->organization_name }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <section aria-labelledby="recent-submissions-title"
                class="col-span-12 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] xl:col-span-5">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800 lg:px-6">
                    <h2 id="recent-submissions-title" class="text-base font-semibold text-gray-900 dark:text-white">Recent submissions</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Latest review status from OSSD</p>
                </div>

                @if ($recentRequests->isEmpty())
                    <div class="px-5 py-12 text-center">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-300">No submissions yet</p>
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Your submitted forms and their status will appear here.</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($recentRequests as $request)
                            <div class="px-5 py-4 lg:px-6">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        @if ($request['route'])
                                            <a href="{{ $request['route'] }}" class="block truncate text-sm font-semibold text-gray-800 hover:text-brand-600 dark:text-gray-100 dark:hover:text-brand-400">{{ $request['name'] }}</a>
                                        @else
                                            <p class="truncate text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $request['name'] }}</p>
                                        @endif
                                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                            {{ $request['submitted_at']?->format('M j, Y · g:i A') }}
                                        </p>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $statusClasses[$request['status']] }}">
                                        {{ $request['status_label'] }}
                                    </span>
                                </div>
                                @if ($request['status'] === 'rejected' && $request['rejection_reason'] !== '')
                                    <p class="mt-2 line-clamp-2 text-xs text-error-600 dark:text-error-400">{{ $request['rejection_reason'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
