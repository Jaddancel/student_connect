@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Event Plans" />

    @php
        $eventApplicationForm = \App\Forms\SystemFunction::form(\App\Forms\SystemFunction::NEW_EVENT);
        $eventApplicationAvailable = $eventApplicationForm
            && $eventApplicationForm->is_active
            && $eventApplicationForm->is_published
            && $eventApplicationForm->route_name;
        $isStudentOfficer = (int) auth()->user()->user_type === \App\Models\User::TYPE_OFFICER;

        $planGroups = [
            'in_workplan' => collect($grouped['in_workplan'] ?? []),
            'pending' => collect($grouped['pending'] ?? []),
            'approved' => collect($grouped['approved'] ?? []),
            'rejected' => collect($grouped['rejected'] ?? []),
            'junked' => collect($grouped['junked'] ?? []),
        ];
        $pendingCount = $planGroups['in_workplan']->count() + $planGroups['pending']->count();
        $activeCount = $pendingCount + $planGroups['approved']->count() + $planGroups['rejected']->count();
        $allPlansEmpty = collect($planGroups)->every(fn ($plans) => $plans->isEmpty());

        $sections = [
            ['key' => 'in_workplan', 'filter' => 'pending', 'title' => 'Awaiting workplan approval', 'description' => 'Proposed activities waiting to be admitted to the organization workplan.', 'status' => 'pending', 'dot' => 'bg-blue-500'],
            ['key' => 'pending', 'filter' => 'pending', 'title' => 'Under OSSD review', 'description' => 'Event applications submitted for review and decision.', 'status' => 'pending', 'dot' => 'bg-warning-500'],
            ['key' => 'rejected', 'filter' => 'rejected', 'title' => 'Needs revision', 'description' => 'Update the requested details, then resubmit the event plan.', 'status' => 'rejected', 'dot' => 'bg-error-500'],
            ['key' => 'approved', 'filter' => 'approved', 'title' => 'Approved events', 'description' => 'Approved plans that are ready for scheduling and implementation.', 'status' => 'approved', 'dot' => 'bg-success-500'],
            ['key' => 'junked', 'filter' => 'junked', 'title' => 'Archived plans', 'description' => 'Plans removed from the active event workflow.', 'status' => 'junked', 'dot' => 'bg-gray-400'],
        ];
    @endphp

    <div class="space-y-6" x-data="{ planFilter: 'all' }">
        <section class="relative overflow-hidden rounded-3xl border border-brand-100 bg-gradient-to-br from-brand-50 via-white to-white p-6 shadow-sm dark:border-brand-500/20 dark:from-brand-500/10 dark:via-gray-900 dark:to-gray-900 sm:p-8">
            <div class="pointer-events-none absolute -right-16 -top-20 h-56 w-56 rounded-full bg-brand-200/40 blur-3xl dark:bg-brand-500/10"></div>
            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-2xl">
                    <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-brand-200 bg-white/80 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-brand-700 dark:border-brand-500/30 dark:bg-white/5 dark:text-brand-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>
                        Event application center
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-3xl">Plan and track every organization event</h1>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-gray-600 dark:text-gray-300">Submit one application for each proposed event, follow its OSSD review status, and take action when revisions are requested.</p>
                </div>

                @if ($isStudentOfficer && $eventApplicationAvailable)
                    <a href="{{ route('forms.render', $eventApplicationForm->route_name) }}"
                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-500/20 transition hover:-translate-y-0.5 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/40 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke-linecap="round" /></svg>
                        Apply for a New Event
                    </a>
                @elseif ($isStudentOfficer)
                    <div class="max-w-sm rounded-xl border border-warning-200 bg-warning-50/90 px-4 py-3 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                        <p class="font-semibold">Applications are temporarily unavailable</p>
                        <p class="mt-1 text-xs leading-5">OSSD must publish the event application form before you can submit a new event.</p>
                    </div>
                @endif
            </div>

            <div class="relative mt-7 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ([
                    ['value' => $activeCount, 'label' => 'Active plans', 'color' => 'text-gray-900 dark:text-white'],
                    ['value' => $pendingCount, 'label' => 'Awaiting review', 'color' => 'text-warning-600 dark:text-warning-400'],
                    ['value' => $planGroups['approved']->count(), 'label' => 'Approved', 'color' => 'text-success-600 dark:text-success-400'],
                    ['value' => $planGroups['rejected']->count(), 'label' => 'Needs action', 'color' => 'text-error-600 dark:text-error-400'],
                ] as $metric)
                    <div class="rounded-2xl border border-white/80 bg-white/80 p-4 backdrop-blur dark:border-white/10 dark:bg-white/5">
                        <p class="text-2xl font-bold {{ $metric['color'] }}">{{ $metric['value'] }}</p>
                        <p class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        @if (session('success'))
            <div role="status" class="flex items-start gap-3 rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-800 dark:border-success-500/20 dark:bg-success-500/10 dark:text-success-300">
                <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="flex items-start gap-3 rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-800 dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-300">
                <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 3.7 2.6 17a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 3.7a2 2 0 0 0-3.4 0Z" stroke-linecap="round" stroke-linejoin="round" /></svg>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-5 py-5 dark:border-gray-800 sm:px-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-600 dark:text-brand-400">Application tracker</p>
                        <h2 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Event plan status</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Select a status to focus on the plans that need your attention.</p>
                    </div>

                    <div class="flex max-w-full gap-1 overflow-x-auto rounded-xl bg-gray-100 p-1 dark:bg-gray-800/80" role="tablist" aria-label="Filter event plans by status">
                        @foreach ([
                            ['key' => 'all', 'label' => 'All', 'count' => $activeCount + $planGroups['junked']->count()],
                            ['key' => 'pending', 'label' => 'In review', 'count' => $pendingCount],
                            ['key' => 'approved', 'label' => 'Approved', 'count' => $planGroups['approved']->count()],
                            ['key' => 'rejected', 'label' => 'Needs action', 'count' => $planGroups['rejected']->count()],
                            ['key' => 'junked', 'label' => 'Archived', 'count' => $planGroups['junked']->count()],
                        ] as $filter)
                            <button type="button" role="tab" @click="planFilter = '{{ $filter['key'] }}'"
                                :aria-selected="planFilter === '{{ $filter['key'] }}'"
                                :class="planFilter === '{{ $filter['key'] }}' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white'"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold transition">
                                {{ $filter['label'] }}
                                <span class="rounded-full bg-gray-200/70 px-1.5 py-0.5 text-[10px] leading-none dark:bg-gray-600">{{ $filter['count'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="px-5 py-2 sm:px-6">
                @if ($allPlansEmpty)
                    <div class="flex flex-col items-center px-4 py-14 text-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-gray-900 dark:text-white">No event plans yet</h3>
                        <p class="mt-1 max-w-sm text-sm leading-6 text-gray-500 dark:text-gray-400">Your submitted event applications and their approval status will appear here.</p>
                        @if ($isStudentOfficer && $eventApplicationAvailable)
                            <a href="{{ route('forms.render', $eventApplicationForm->route_name) }}" class="mt-5 text-sm font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">Start your first event application &rarr;</a>
                        @endif
                    </div>
                @else
                    @foreach ($sections as $section)
                        @php($plans = $planGroups[$section['key']])
                        @if ($plans->isNotEmpty())
                            <div x-show="planFilter === 'all' || planFilter === '{{ $section['filter'] }}'" x-transition.opacity class="border-b border-gray-100 py-5 last:border-b-0 dark:border-gray-800">
                                <div class="mb-3 flex items-start justify-between gap-4">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $section['dot'] }}"></span>
                                        <div>
                                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $section['title'] }}</h3>
                                            <p class="mt-0.5 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $section['description'] }}</p>
                                        </div>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $plans->count() }}</span>
                                </div>

                                <div class="space-y-2">
                                    @foreach ($plans as $plan)
                                        <x-event-plan-card
                                            :plan="$plan"
                                            :personNames="$personNames ?? []"
                                            :orgNames="$orgNames ?? []"
                                            :status="$section['status']"
                                            :hasPendingEventRequest="$section['key'] === 'approved' && in_array((int) $plan->event_plan_id, $pendingEventRequestParentIds ?? [], true)"
                                        />
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach

                    <div x-show="planFilter === 'pending' && {{ $pendingCount }} === 0" x-cloak class="py-12 text-center text-sm text-gray-500 dark:text-gray-400">No plans are awaiting review.</div>
                    <div x-show="planFilter === 'approved' && {{ $planGroups['approved']->count() }} === 0" x-cloak class="py-12 text-center text-sm text-gray-500 dark:text-gray-400">No approved event plans yet.</div>
                    <div x-show="planFilter === 'rejected' && {{ $planGroups['rejected']->count() }} === 0" x-cloak class="py-12 text-center text-sm text-gray-500 dark:text-gray-400">No event plans need revision.</div>
                    <div x-show="planFilter === 'junked' && {{ $planGroups['junked']->count() }} === 0" x-cloak class="py-12 text-center text-sm text-gray-500 dark:text-gray-400">No archived event plans.</div>
                @endif
            </div>
        </section>

    </div>
@endsection
