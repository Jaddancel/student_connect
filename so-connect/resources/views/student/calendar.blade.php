<x-dashboard-layout>

    <x-slot name="title">Calendar</x-slot>

    <div class="container">
        <h2 class="text-2xl text-black pb-6 font-bold">Calendar</h2>

        @if ($events->isEmpty())
            <div class="card p-10 bg-base-200">
                <p class="text-gray-500">No events found.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($events as $event)
                    <div class="card p-6 bg-base-200 shadow-sm">
                        <h3 class="text-xl font-semibold">{{ $event->detail->event_name }}</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            📅 {{ $event->detail->event_start_date->format('M d, Y g:i A') }}
                            &mdash;
                            {{ $event->detail->event_end_date->format('M d, Y g:i A') }}
                        </p>
                        @if ($event->detail->event_location)
                            <p class="mt-1">📍 {{ $event->detail->event_location }}</p>
                        @endif
                        @if ($event->detail->event_description_text)
                            <p class="mt-2 text-gray-700">{{ $event->detail->event_description_text }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-dashboard-layout>