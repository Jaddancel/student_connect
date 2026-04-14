@extends('layouts.app')

@section('content')
    <div class="grid grid-cols-12 gap-4 md:gap-6">
        {{-- <div class="col-span-12 space-y-6 xl:col-span-7">
            <x-ecommerce.ecommerce-metrics />
            <x-ecommerce.monthly-sale />
        </div>
        <div class="col-span-12 xl:col-span-5">
            <x-ecommerce.monthly-target />
        </div> --}}

        <div class="col-span-12">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Upcoming Events</h3>
                    <a href="{{ route('calendar') }}"
                        class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400">
                        View Calendar
                    </a>
                </div>

                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                    Showing events from your organizations in the next 30 days.
                </p>

                @if ($upcomingEvents->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">No upcoming events found for your organizations.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($upcomingEvents as $event)
                            <div class="rounded-xl border border-gray-100 px-4 py-3 dark:border-gray-800">
                                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800 dark:text-white/90">
                                            {{ $event->event_name }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $event->organization_name }}
                                        </p>
                                    </div>
                                    <div class="text-left md:text-right">
                                        <p class="text-sm text-gray-700 dark:text-gray-300">
                                            {{ \Illuminate\Support\Carbon::parse($event->start_time)->format('M d, Y h:i A') }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            @if ($event->event_location)
                                                {{ $event->event_location }}
                                            @else
                                                TBA
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- <div class="col-span-12">
            <x-ecommerce.statistics-chart />
        </div>

        <div class="col-span-12 xl:col-span-5">
            <x-ecommerce.customer-demographic />
        </div>

        <div class="col-span-12 xl:col-span-7">
            <x-ecommerce.recent-orders />
        </div> --}}
    </div>
@endsection