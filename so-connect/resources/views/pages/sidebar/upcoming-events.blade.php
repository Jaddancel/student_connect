@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Upcoming Events" />

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Your Organization Event Calendar</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        @if (!empty($windowDays))
                            Events from organizations where you currently have membership in the next {{ $windowDays }} days.
                        @else
                            Events from organizations where you currently have membership.
                        @endif
                    </p>
                </div>
                <span
                    class="inline-flex items-center rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">
                    {{ $upcomingEvents->count() }} upcoming
                </span>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            @if ($upcomingEvents->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No upcoming events found for your organizations.
                </p>
            @else
                <div class="space-y-4">
                    @foreach ($upcomingEvents as $event)
                        <div class="rounded-xl border border-gray-100 px-4 py-4 dark:border-gray-800">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <p class="text-base font-semibold text-gray-800 dark:text-white/90">
                                        {{ $event->event_name }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $event->organization_name }}
                                    </p>
                                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                        {{ !empty($event->event_description) ? $event->event_description : 'No event description provided.' }}
                                    </p>
                                </div>
                                <div class="min-w-[210px] text-left md:text-right">
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ \Illuminate\Support\Carbon::parse($event->start_time)->format('M d, Y h:i A') }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        to {{ \Illuminate\Support\Carbon::parse($event->end_time)->format('M d, Y h:i A') }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ !empty($event->event_location) ? $event->event_location : 'Location TBA' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection