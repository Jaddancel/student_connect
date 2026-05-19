@extends('layouts.app')

@section('content')
    <div class="grid grid-cols-12 gap-4 md:gap-6">
        <div class="col-span-12">
            <div
                class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="h-1 w-full bg-gradient-to-r from-palette-lime via-palette-lime-light to-palette-lime-pale"></div>

                <div class="p-5 lg:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-palette-lime-pale dark:bg-palette-lime/10">
                                <svg class="h-4.5 w-4.5 text-gray-700 dark:text-palette-lime" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Directory of Student
                                    Leader</h3>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Open the student leader directory
                                    form from your dashboard.</p>
                            </div>
                        </div>

                        <a href="{{ route('student-leader-directory') }}"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-palette-lime bg-palette-lime-pale px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-palette-lime dark:border-palette-lime/30 dark:bg-palette-lime/10 dark:text-palette-lime dark:hover:bg-palette-lime/20">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M13.5 6H5.25A2.25 2.25 0 003 8.25v7.5A2.25 2.25 0 005.25 18h8.25m0-12l-4.5 4.5m4.5-4.5v4.5m0-4.5H18a2.25 2.25 0 012.25 2.25V15" />
                            </svg>
                            Open Form
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-span-12">
            <div
                class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">

                {{-- Lime gradient top stripe --}}
                <div class="h-1 w-full bg-gradient-to-r from-palette-lime via-palette-lime-light to-palette-lime-pale">
                </div>

                <div class="p-5 lg:p-6">

                    {{-- Header --}}
                    <div class="mb-5 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-palette-lime-pale dark:bg-palette-lime/10">
                                <svg class="h-4.5 w-4.5 text-gray-700 dark:text-palette-lime" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Upcoming Events</h3>
                                <p class="text-xs text-gray-400 dark:text-gray-500">From your organizations</p>
                            </div>
                        </div>
                        <a href="{{ route('calendar') }}"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-palette-lime bg-palette-lime-pale px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-palette-lime dark:border-palette-lime/30 dark:bg-palette-lime/10 dark:text-palette-lime dark:hover:bg-palette-lime/20">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            View Calendar
                        </a>
                    </div>

                    {{-- Divider --}}
                    <div class="mb-5 h-px bg-palette-lime-pale dark:bg-gray-800"></div>

                    @if ($upcomingEvents->isEmpty())
                        <div class="flex flex-col items-center justify-center py-12 text-center">
                            <div
                                class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-palette-lime-pale dark:bg-palette-lime/10">
                                <svg class="h-6 w-6 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">No upcoming events</p>
                            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Events from your organizations will
                                appear here</p>
                        </div>
                    @else
                        <div class="space-y-2.5">
                            @foreach ($upcomingEvents as $event)
                                <div
                                    class="group relative overflow-hidden rounded-xl border border-palette-neutral bg-palette-surface transition-all hover:border-palette-lime-light hover:shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.02] dark:hover:border-palette-lime/30">

                                    {{-- Absolute left lime bar --}}
                                    <div
                                        class="absolute inset-y-0 left-0 w-[3px] bg-palette-lime transition-all group-hover:w-1">
                                    </div>

                                    <div
                                        class="flex flex-col gap-3 py-3.5 pl-5 pr-4 md:flex-row md:items-center md:justify-between">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-gray-800 dark:text-white/90">
                                                {{ $event->event_name }}
                                            </p>
                                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                {{ $event->organization_name }}
                                            </p>
                                        </div>
                                        <div class="flex shrink-0 flex-col items-start gap-1 md:items-end">
                                            <span
                                                class="inline-flex items-center rounded-md bg-palette-lime-pale px-2 py-0.5 text-xs font-semibold text-gray-700 dark:bg-palette-lime/10 dark:text-palette-lime-light">
                                                {{ \Illuminate\Support\Carbon::parse($event->start_time)->format('M d, Y') }}
                                            </span>
                                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                                {{ \Illuminate\Support\Carbon::parse($event->start_time)->format('h:i A') }}
                                                &middot;
                                                {{ $event->event_location ?: 'TBA' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
