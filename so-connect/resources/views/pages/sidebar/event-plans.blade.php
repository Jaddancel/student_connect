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
    @endphp

    <section class="mb-6 flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:flex-row sm:items-center sm:justify-between lg:p-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-500 dark:text-brand-400">Event application tracking</p>
            <h1 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">Plan, submit, and monitor organization events</h1>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-400">Start one application for each proposed event. Submitted requests and OSSD decisions are tracked below.</p>
        </div>

        @if ($isStudentOfficer && $eventApplicationAvailable)
            <a href="{{ route('forms.render', $eventApplicationForm->route_name) }}"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-brand-500 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                <span aria-hidden="true">+</span>
                Apply for a New Event
            </a>
        @elseif ($isStudentOfficer)
            <p class="max-w-xs rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                Event applications are temporarily unavailable. OSSD must publish the event application form first.
            </p>
        @endif

    @if (session('success'))
        <div class="mb-5 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/20 dark:bg-success-500/10 dark:text-success-400">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-400">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="space-y-8">

        {{-- Workplan Section --}}
        <x-workplan-section
            :workplans="$workplans ?? []"
            :activeSemester="$activeSemester ?? null"
            :orgNames="$orgNames ?? []"
            :personNames="$personNames ?? []"
        />

        {{-- Activities awaiting the decision that admits them to the workplan --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Awaiting Admin Approval</h3>
                <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-500/15 dark:text-blue-400">
                    {{ $grouped['in_workplan']->count() }}
                </span>
            </div>

            @if ($grouped['in_workplan']->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No activities are awaiting admin approval.</p>
            @else
                <div class="space-y-1">
                    @foreach ($grouped['in_workplan'] as $plan)
                        <x-event-plan-card :plan="$plan" :personNames="$personNames" :orgNames="$orgNames" status="pending" />
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Event Item Requests Pending Admin Review --}}
        @if ($grouped['pending']->isNotEmpty())
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Pending Admin Review</h3>
                <span class="inline-flex items-center rounded-full bg-warning-50 px-2.5 py-0.5 text-xs font-medium text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">
                    {{ $grouped['pending']->count() }}
                </span>
            </div>
            <div class="space-y-1">
                @foreach ($grouped['pending'] as $plan)
                    <x-event-plan-card :plan="$plan" :personNames="$personNames" :orgNames="$orgNames" status="pending" />
                @endforeach
            </div>
        </div>
        @endif

        {{-- Approved Events --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Approved Events</h3>
                <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">
                    {{ $grouped['approved']->count() }}
                </span>
            </div>

            @if ($grouped['approved']->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No approved event plans.</p>
            @else
                <div class="space-y-1">
                    @foreach ($grouped['approved'] as $plan)
                        <x-event-plan-card
                            :plan="$plan"
                            :personNames="$personNames"
                            :orgNames="$orgNames"
                            status="approved"
                            :hasPendingEventRequest="in_array((int) $plan->event_plan_id, $pendingEventRequestParentIds ?? [], true)"
                        />
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Rejected Events --}}
        @if ($grouped['rejected']->isNotEmpty())
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Rejected Events</h3>
                <span class="inline-flex items-center rounded-full bg-error-50 px-2.5 py-0.5 text-xs font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400">
                    {{ $grouped['rejected']->count() }}
                </span>
            </div>
            <div class="space-y-1">
                @foreach ($grouped['rejected'] as $plan)
                    <x-event-plan-card :plan="$plan" :personNames="$personNames" :orgNames="$orgNames" status="rejected" />
                @endforeach
            </div>
        </div>
        @endif

        {{-- Junked Events --}}
        @if ($grouped['junked']->isNotEmpty())
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Junked Events</h3>
                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                    {{ $grouped['junked']->count() }}
                </span>
            </div>
            <div class="space-y-1">
                @foreach ($grouped['junked'] as $plan)
                    <x-event-plan-card :plan="$plan" :personNames="$personNames" :orgNames="$orgNames" status="junked" />
                @endforeach
            </div>
        </div>
        @endif

    </div>
@endsection
