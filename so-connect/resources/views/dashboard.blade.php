<x-dashboard-layout>
    @auth
        @php
            $typeCode = auth()->user()
                ->user_type_code;
        @endphp
        @if ($typeCode == 3)
            <x-slot name="title">Member Dashboard</x-slot>
        @else
            <x-slot name="title">Admin Dashboard</x-slot>
        @endif
    @endauth

    <div class="card bg-base-100 w-full shadow-sm">
        <div class="card-body">
            <h2 class="card-title pb-2">Upcoming Events</h2>
            <div class="flex flex-col lg:flex-row gap-4">
                <calendar-date id="event-calendar" min="{{ now()->format('Y-m-d') }}"
                    max="{{ now()->addMonths(6)->format('Y-m-d') }}" locale="en-GB"
                    class="cally w-full lg:w-1/4 bg-base-100 border border-base-300 shadow-lg rounded-box">
                    <svg aria-label="Previous" class="fill-current size-4" slot="previous"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M15.75 19.5 8.25 12l7.5-7.5"></path>
                    </svg>
                    <svg aria-label="Next" class="fill-current size-4" slot="next" xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24">
                        <path fill="currentColor" d="m8.25 4.5 7.5 7.5-7.5 7.5"></path>
                    </svg>
                    <calendar-month></calendar-month>
                </calendar-date>
                <div class="divider lg:divider-horizontal"></div>
                <div class="flex-1 overflow-y-auto max-h-96" id="event-list">
                    @if ($events->isEmpty())
                        <div id="no-events">
                            <h2 class="card-title pb-2">No upcoming events</h2>
                            <p>You have no upcoming events scheduled.</p>
                        </div>
                    @else
                        <div id="no-events-for-date" class="hidden">
                            <h2 class="card-title pb-2">No events on this day</h2>
                            <p>Select another date or <a href="#" id="show-all-link" class="link link-primary">show all
                                    upcoming events</a>.</p>
                        </div>
                        <div class="space-y-3" id="events-container">
                            @foreach ($events as $event)
                                <div class="event-card p-4 bg-base-200 rounded-lg"
                                    data-start="{{ $event->detail->event_start_date->format('Y-m-d') }}"
                                    data-end="{{ $event->detail->event_end_date->format('Y-m-d') }}">
                                    <h3 class="font-semibold text-lg">{{ $event->detail->event_name }}</h3>
                                    <p class="text-sm text-gray-500">
                                        {{ $event->detail->event_start_date->format('M d, Y g:i A') }}
                                        &mdash;
                                        {{ $event->detail->event_end_date->format('M d, Y g:i A') }}
                                    </p>
                                    @if ($event->detail->event_location)
                                        <p class="text-sm">📍 {{ $event->detail->event_location }}</p>
                                    @endif
                                    @if ($event->detail->event_description_text)
                                        <p class="text-sm mt-1">{{ $event->detail->event_description_text }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($events->isNotEmpty())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const eventDates = @json(
                    $events->flatMap(function ($event) {
                        $start = $event->detail->event_start_date->startOfDay();
                        $end = $event->detail->event_end_date->startOfDay();
                        $dates = [];
                        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                            $dates[] = $d->format('Y-m-d');
                        }
                        return $dates;
                    })->unique()->values()
                );

                const calendar = document.getElementById('event-calendar');
                const calendarMonth = calendar.querySelector('calendar-month');
                const cards = document.querySelectorAll('.event-card');
                const noEventsForDate = document.getElementById('no-events-for-date');
                const showAllLink = document.getElementById('show-all-link');
                let selectedDate = null;

                function injectStyles(shadowRoot) {
                    if (shadowRoot.querySelector('#event-dot-styles')) return;
                    const style = document.createElement('style');
                    style.id = 'event-dot-styles';
                    style.textContent = `
                                                                                        .has-event {
                                                                                            position: relative;
                                                                                        }
                                                                                        .has-event .event-dot {
                                                                                            position: absolute;
                                                                                            bottom: 2px;
                                                                                            left: 50%;
                                                                                            transform: translateX(-50%);
                                                                                            width: 6px;
                                                                                            height: 6px;
                                                                                            border-radius: 50%;
                                                                                            background-color: oklch(0.7 0.15 60);
                                                                                            pointer-events: none;
                                                                                        }
                                                                                    `;
                    shadowRoot.prepend(style);
                }

                function highlightEventDates() {
                    if (!calendarMonth || !calendarMonth.shadowRoot) return;
                    const shadowRoot = calendarMonth.shadowRoot;
                    injectStyles(shadowRoot);

                    const buttons = shadowRoot.querySelectorAll('td');
                    buttons.forEach(td => {
                        const btn = td.querySelector('button');
                        if (!btn) return;
                        const dateVal = btn.getAttribute('datetime') || btn.getAttribute('value') || btn.dataset.date;
                        if (dateVal && eventDates.includes(dateVal)) {
                            if (!td.classList.contains('has-event')) {
                                td.classList.add('has-event');
                                td.style.position = 'relative';
                                const dot = document.createElement('span');
                                dot.classList.add('event-dot');
                                td.appendChild(dot);
                            }
                        } else {
                            td.classList.remove('has-event');
                            const existingDot = td.querySelector('.event-dot');
                            if (existingDot) existingDot.remove();
                        }
                    });
                }

                function filterEvents(date) {
                    let visibleCount = 0;
                    cards.forEach(card => {
                        const start = card.dataset.start;
                        const end = card.dataset.end;
                        if (date >= start && date <= end) {
                            card.classList.remove('hidden');
                            visibleCount++;
                        } else {
                            card.classList.add('hidden');
                        }
                    });
                    if (noEventsForDate) {
                        noEventsForDate.classList.toggle('hidden', visibleCount > 0);
                    }
                }

                function showAllEvents() {
                    cards.forEach(card => card.classList.remove('hidden'));
                    if (noEventsForDate) noEventsForDate.classList.add('hidden');
                    selectedDate = null;
                    calendar.value = '';
                }

                if (showAllLink) {
                    showAllLink.addEventListener('click', function (e) {
                        e.preventDefault();
                        showAllEvents();
                    });
                }

                calendar.addEventListener('change', function (e) {
                    const date = e.target.value;
                    if (date === selectedDate) {
                        showAllEvents();
                    } else {
                        selectedDate = date;
                        filterEvents(date);
                    }
                });

                // Highlight after calendar renders, and re-highlight on month navigation
                const observer = new MutationObserver(() => setTimeout(highlightEventDates, 50));
                if (calendarMonth && calendarMonth.shadowRoot) {
                    observer.observe(calendarMonth.shadowRoot, { childList: true, subtree: true });
                }
                setTimeout(highlightEventDates, 200);
                setTimeout(highlightEventDates, 500);
            });
        </script>
    @endif

    <div class="flex flex-col md:flex-row gap-4">
        <div class="card bg-base-100 w-full md:w-1/4 shadow-sm">
            <div class="card-body">
                <h2 class="card-title pb-2">My Organizations</h2>
                <p class="pb-3">View and manage your organization memberships.</p>
                <div class="card-actions">
                    <a href="{{ route('my_organizations') }}" class="btn btn-primary">Go to My
                        Organizations</a>

                </div>
            </div>
        </div>

        {{-- <div class="card bg-base-100 w-full md:w-1/4 shadow-sm"> --}}
            {{-- <div class="card-body"> --}}
                {{-- <h2 class="card-title pb-2">Membership Requests</h2> --}}
                {{-- count the membership requests --}}
                {{-- <p class="pb-3">You have 0 pending membership requests.</p> --}}
                {{-- <div class="card-actions"> --}}
                    {{-- <a href="{{ route('admin.membership_requests') }}" class="btn btn-primary">Go to --}}
                        {{-- Membership --}}
                        {{-- Requests</a> --}}
                    {{-- </div> --}}
                {{-- </div> --}}
            {{-- </div> --}}

        {{-- <div class="card bg-base-100 w-full md:w-1/4 shadow-sm"> --}}
            {{-- <div class="card-body"> --}}
                {{-- <h2 class="card-title pb-2">Event Requests</h2> --}}
                {{-- count the event requests --}}
                {{-- <p class="pb-3">You have 0 pending event requests.</p> --}}
                {{-- <div class="card-actions"> --}}
                    {{-- <a href="{{ route('admin.event_requests') }}" class="btn btn-primary">Go to Event --}}
                        {{-- Requests</a> --}}
                    {{-- </div> --}}
                {{-- </div> --}}
            {{-- </div> --}}
    </div>
</x-dashboard-layout>