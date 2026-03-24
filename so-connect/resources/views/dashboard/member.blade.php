@php
    $memberEvents = $memberDashboardEvents ?? collect();
    $memberPendingRequests = $memberPendingMembershipRequests ?? collect();
@endphp

<section class="space-y-5" id="member-events-widget">
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body p-0">
            <div class="grid grid-cols-1 lg:grid-cols-[auto_minmax(0,1fr)]">
                <div class="border-b border-base-300 p-5 lg:border-r lg:border-b-0">
                    <div class="mb-4">
                        <h2 class="card-title">Organization Event Calendar</h2>
                        <p class="text-sm opacity-70">Days with at least one event are highlighted.</p>
                    </div>

                    <calendar-date id="member-event-calendar" class="member-event-calendar" months="1">
                        <button slot="previous" class="btn btn-sm btn-ghost" aria-label="Previous month">&lt;</button>
                        <button slot="next" class="btn btn-sm btn-ghost" aria-label="Next month">&gt;</button>
                        <calendar-month></calendar-month>
                    </calendar-date>
                </div>

                <div class="p-5">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold">Events</h3>
                            <p id="member-event-list-subtitle" class="text-sm opacity-70">Showing all upcoming events
                                for your organization(s).</p>
                        </div>
                        <button id="member-event-clear-filter" class="btn btn-xs btn-outline" type="button">Show
                            all</button>
                    </div>

                    <ul id="member-event-list" class="space-y-3"></ul>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="flex flex-row gap-5">
    <div class="card bg-base-100 p-5">
        <h3 class="card-title">Membership Requests</h3>
        <div class="card-body">
            <ul class="space-y-3">
                @forelse ($memberPendingRequests as $request)
                    <li class="rounded-box border border-base-300 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold leading-tight">{{ $request['requester_name'] }}</p>
                                <p class="mt-1 text-xs uppercase tracking-wide opacity-70">
                                    {{ $request['organization_name'] }}</p>
                            </div>
                            <span class="badge badge-warning badge-sm">Pending</span>
                        </div>
                        <p class="mt-2 text-sm opacity-80">
                            Requested {{ optional($request['requested_at'])->diffForHumans() ?? 'recently' }}
                        </p>
                    </li>
                @empty
                    <li class="rounded-box border border-base-300 bg-base-200/50 p-4 text-sm opacity-80">
                        No pending membership requests.
                    </li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="card bg-base-100 p-5">
        <h3 class="card-title">Document Requests</h3>
        <div class="card-body">
            There are no Document Requests.
        </div>
    </div>
    <div class="card bg-base-100 p-5">

    </div>
</div>

<style>
    .member-event-calendar::part(container) {
        width: fit-content;
        max-width: 100%;
    }

    .member-event-calendar::part(months) {
        width: fit-content;
    }

    .member-event-calendar::part(table) {
        width: auto;
    }

    .member-event-calendar calendar-month::part(has-event) {
        background-color: #bbf7d0 !important;
        color: #14532d !important;
        font-weight: 700;
        border-radius: 0.5rem;
        box-shadow: inset 0 0 0 1px #4ade80 !important;
    }

    .member-event-calendar calendar-month::part(has-event):hover {
        background-color: #86efac !important;
    }

    .member-event-calendar calendar-month::part(has-event):focus-visible {
        background-color: #4ade80 !important;
        color: #052e16 !important;
    }
</style>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const root = document.getElementById('member-events-widget');
            if (!root) {
                return;
            }

            if (window.customElements && !window.customElements.get('calendar-date')) {
                await Promise.race([
                    window.customElements.whenDefined('calendar-date'),
                    new Promise((resolve) => setTimeout(resolve, 2500)),
                ]);
            }

            const listEl = document.getElementById('member-event-list');
            const subtitleEl = document.getElementById('member-event-list-subtitle');
            const calendarEl = document.getElementById('member-event-calendar');
            const clearFilterBtn = document.getElementById('member-event-clear-filter');

            if (!listEl || !subtitleEl || !calendarEl || !clearFilterBtn) {
                return;
            }

            const events = @json($memberEvents, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            const eventsByDate = events.reduce((map, event) => {
                if (!event.date) {
                    return map;
                }

                if (!map.has(event.date)) {
                    map.set(event.date, []);
                }

                map.get(event.date).push(event);
                return map;
            }, new Map());

            const dateTimeFormatter = new Intl.DateTimeFormat(undefined, {
                dateStyle: 'medium',
                timeStyle: 'short',
            });

            const dateFormatter = new Intl.DateTimeFormat(undefined, {
                dateStyle: 'full',
            });

            const escapeHtml = (value) => {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/\"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            };

            let selectedDate = '';

            calendarEl.getDayParts = (date) => {
                const dateKey = date.toISOString().slice(0, 10);
                return eventsByDate.has(dateKey) ? 'has-event' : '';
            };

            const renderList = (dateKey = '') => {
                const filteredEvents = dateKey ? (eventsByDate.get(dateKey) || []) : events;

                if (!filteredEvents.length) {
                    listEl.innerHTML = `
                                            <li class="rounded-box border border-base-300 bg-base-200/50 p-4 text-sm opacity-80">
                                                No events found for this selection.
                                            </li>
                                        `;

                    subtitleEl.textContent = dateKey
                        ? `No events on ${dateFormatter.format(new Date(`${dateKey}T00:00:00`))}.`
                        : 'No upcoming events assigned to your organization(s).';
                    return;
                }

                subtitleEl.textContent = dateKey
                    ? `Showing events on ${dateFormatter.format(new Date(`${dateKey}T00:00:00`))}.`
                    : 'Showing all upcoming events for your organization(s).';

                listEl.innerHTML = filteredEvents
                    .map((event) => {
                        const start = event.start ? new Date(event.start) : null;
                        const end = event.end ? new Date(event.end) : null;
                        const schedule = start
                            ? `${dateTimeFormatter.format(start)}${end ? ` - ${dateTimeFormatter.format(end)}` : ''}`
                            : 'Schedule TBD';
                        const title = escapeHtml(event.title || 'Untitled Event');
                        const organization = escapeHtml(event.organization || 'Organization');
                        const location = escapeHtml(event.location || 'Location TBD');
                        const description = event.description ? `<p class="mt-2 text-sm opacity-80">${escapeHtml(event.description)}</p>` : '';

                        return `
                                                <li class="rounded-box border border-base-300 p-4">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div>
                                                            <p class="font-semibold leading-tight">${title}</p>
                                                            <p class="mt-1 text-xs uppercase tracking-wide opacity-70">${organization}</p>
                                                        </div>
                                                        <span class="badge badge-primary badge-sm">Event</span>
                                                    </div>
                                                    <p class="mt-3 text-sm">${escapeHtml(schedule)}</p>
                                                    <p class="mt-1 text-sm opacity-80">${location}</p>
                                                    ${description}
                                                </li>
                                            `;
                    })
                    .join('');
            };

            const trySelectInitialDate = () => {
                if (!events.length) {
                    renderList('');
                    return;
                }

                selectedDate = events[0].date || '';
                if (selectedDate) {
                    calendarEl.value = selectedDate;
                    renderList(selectedDate);
                    return;
                }

                renderList('');
            };

            calendarEl.addEventListener('change', () => {
                selectedDate = calendarEl.value || '';
                renderList(selectedDate);
            });

            clearFilterBtn.addEventListener('click', () => {
                selectedDate = '';
                renderList('');
            });

            trySelectInitialDate();
        });
    </script>
@endpush