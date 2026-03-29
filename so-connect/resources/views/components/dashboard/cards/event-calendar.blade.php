@props([
    'events' => collect(),
    'title' => 'Organization Event Calendar',
    'subtitle' => 'Days with at least one event are highlighted.',
    'idPrefix' => 'member-event',
])

@php
    $widgetId = $idPrefix . '-widget';
    $calendarId = $idPrefix . '-calendar';
    $listId = $idPrefix . '-list';
    $subtitleId = $idPrefix . '-list-subtitle';
    $clearFilterId = $idPrefix . '-clear-filter';
    $calendarClass = $idPrefix . '-calendar';
@endphp

<section class="space-y-5" id="{{ $widgetId }}">
    <div {{ $attributes->class(['card bg-base-100 shadow-xl']) }}>
        <div class="card-body p-0">
            <div class="grid grid-cols-1 lg:grid-cols-[auto_minmax(0,1fr)]">
                <div class="border-b border-base-300 p-5 lg:border-r lg:border-b-0">
                    <div class="mb-4">
                        <h2 class="card-title">{{ $title }}</h2>
                        <p class="text-sm opacity-70">{{ $subtitle }}</p>
                    </div>

                    <calendar-date id="{{ $calendarId }}" class="{{ $calendarClass }}" months="1">
                        <button slot="previous" class="btn btn-sm btn-ghost" aria-label="Previous month">&lt;</button>
                        <button slot="next" class="btn btn-sm btn-ghost" aria-label="Next month">&gt;</button>
                        <calendar-month></calendar-month>
                    </calendar-date>
                </div>

                <div class="p-5">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold">Events</h3>
                            <p id="{{ $subtitleId }}" class="text-sm opacity-70">Showing all upcoming events
                                for your organization(s).</p>
                        </div>
                        <button id="{{ $clearFilterId }}" class="btn btn-xs btn-outline" type="button">Show
                            all</button>
                    </div>

                    <ul id="{{ $listId }}" class="space-y-3"></ul>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    .{{ $calendarClass }}::part(container) {
        width: fit-content;
        max-width: 100%;
    }

    .{{ $calendarClass }}::part(months) {
        width: fit-content;
    }

    .{{ $calendarClass }}::part(table) {
        width: auto;
    }

    .{{ $calendarClass }} calendar-month::part(has-event) {
        background-color: #bbf7d0 !important;
        color: #14532d !important;
        font-weight: 700;
        border-radius: 0.5rem;
        box-shadow: inset 0 0 0 1px #4ade80 !important;
    }

    .{{ $calendarClass }} calendar-month::part(has-event):hover {
        background-color: #86efac !important;
    }

    .{{ $calendarClass }} calendar-month::part(has-event):focus-visible {
        background-color: #4ade80 !important;
        color: #052e16 !important;
    }
</style>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const root = document.getElementById(@json($widgetId));
            if (!root) {
                return;
            }

            if (window.customElements && !window.customElements.get('calendar-date')) {
                await Promise.race([
                    window.customElements.whenDefined('calendar-date'),
                    new Promise((resolve) => setTimeout(resolve, 2500)),
                ]);
            }

            const listEl = document.getElementById(@json($listId));
            const subtitleEl = document.getElementById(@json($subtitleId));
            const calendarEl = document.getElementById(@json($calendarId));
            const clearFilterBtn = document.getElementById(@json($clearFilterId));

            if (!listEl || !subtitleEl || !calendarEl || !clearFilterBtn) {
                return;
            }

            const events = @json($events, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
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

                    subtitleEl.textContent = dateKey ?
                        `No events on ${dateFormatter.format(new Date(`${dateKey}T00:00:00`))}.` :
                        'No upcoming events assigned to your organization(s).';
                    return;
                }

                subtitleEl.textContent = dateKey ?
                    `Showing events on ${dateFormatter.format(new Date(`${dateKey}T00:00:00`))}.` :
                    'Showing all upcoming events for your organization(s).';

                listEl.innerHTML = filteredEvents
                    .map((event) => {
                        const start = event.start ? new Date(event.start) : null;
                        const end = event.end ? new Date(event.end) : null;
                        const schedule = start ?
                            `${dateTimeFormatter.format(start)}${end ? ` - ${dateTimeFormatter.format(end)}` : ''}` :
                            'Schedule TBD';
                        const title = escapeHtml(event.title || 'Untitled Event');
                        const organization = escapeHtml(event.organization || 'Organization');
                        const location = escapeHtml(event.location || 'Location TBD');
                        const description = event.description ?
                            `<p class="mt-2 text-sm opacity-80">${escapeHtml(event.description)}</p>` :
                            '';

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
