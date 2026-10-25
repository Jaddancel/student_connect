@props([
    'isSystemAdmin' => false,
    'calendarOrganizations' => [],
    'organizationTypes' => [],
])

<div>

    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="custom-calendar">
            @if ($isSystemAdmin)
                <div id="calendar-filters" class="relative z-10 px-4 pt-4 sm:px-6">
                    <label for="calendar-filter-search" class="sr-only">Search organizations and organization types</label>
                    <input id="calendar-filter-search" type="search" autocomplete="off"
                        aria-controls="calendar-filter-options" aria-expanded="false"
                        placeholder="Search organizations or types..."
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 sm:max-w-md">
                    <p id="calendar-filter-summary" class="mt-1 text-xs text-gray-500 dark:text-gray-400">No filters selected — showing all organizations</p>
                    <div id="calendar-filter-options" hidden
                        class="absolute left-4 right-4 mt-1 max-h-72 overflow-y-auto rounded-xl border border-gray-200 bg-white p-3 shadow-lg dark:border-gray-700 dark:bg-gray-900 sm:left-6 sm:right-auto sm:w-[28rem]">
                        <button type="button" id="calendar-filter-clear"
                            class="mb-3 text-xs font-medium text-brand-500 hover:text-brand-600">Clear filters</button>
                        <fieldset>
                            <legend class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Organization types</legend>
                            <div class="space-y-1">
                                @foreach ($organizationTypes as $typeId => $typeLabel)
                                    <label data-calendar-filter-option class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                                        <input type="checkbox" data-calendar-filter-kind="type" value="{{ $typeId }}" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                                        <span>{{ $typeLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <fieldset class="mt-3 border-t border-gray-100 pt-3 dark:border-gray-800">
                            <legend class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Organizations</legend>
                            <div class="space-y-1">
                                @foreach ($calendarOrganizations as $organization)
                                    <label data-calendar-filter-option class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                                        <input type="checkbox" data-calendar-filter-kind="organization" value="{{ $organization->organization_id }}" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                                        <span>{{ $organization->name }}</span>
                                        <span class="ml-auto text-xs text-gray-400">{{ $organizationTypes[$organization->organization_type] ?? 'Other' }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>
                </div>
            @endif
            <div id="calendar" class="min-h-screen" data-event-scope="current"
                data-system-admin="{{ $isSystemAdmin ? '1' : '0' }}"></div>
        </div>
    </div>

    {{-- Day Summary Modal --}}
    <div class="fixed inset-0 items-center justify-center hidden p-3 overflow-y-auto sm:p-5 modal z-99999"
        id="daySummaryModal">
        <div class="modal-close-btn fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"></div>
        <div
            class="relative flex w-full max-w-[480px] flex-col overflow-hidden rounded-2xl bg-white p-5 sm:p-6 dark:bg-gray-900">
            <button
                class="day-summary-close transition-color absolute top-5 right-5 z-999 flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-600 sm:h-11 sm:w-11 dark:bg-white/[0.05] dark:text-gray-400 dark:hover:bg-white/[0.07] dark:hover:text-gray-300">
                <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M6.04289 16.5418C5.65237 16.9323 5.65237 17.5655 6.04289 17.956C6.43342 18.3465 7.06658 18.3465 7.45711 17.956L11.9987 13.4144L16.5408 17.9565C16.9313 18.347 17.5645 18.347 17.955 17.9565C18.3455 17.566 18.3455 16.9328 17.955 16.5423L13.4129 12.0002L17.955 7.45808C18.3455 7.06756 18.3455 6.43439 17.955 6.04387C17.5645 5.65335 16.9313 5.65335 16.5408 6.04387L11.9987 10.586L7.45711 6.04439C7.06658 5.65386 6.43342 5.65386 6.04289 6.04439C5.65237 6.43491 5.65237 7.06808 6.04289 7.4586L10.5845 12.0002L6.04289 16.5418Z"
                        fill="" />
                </svg>
            </button>

            <h5 class="mb-1 text-xl font-semibold text-gray-800 dark:text-white/90" id="daySummaryDate"></h5>
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Events on this day</p>

            <div id="daySummaryEventList" class="mb-5 max-h-48 overflow-y-auto space-y-2">
                <p class="text-sm text-gray-400 dark:text-gray-500 italic">No events on this day.</p>
            </div>

        </div>
    </div>
</div>
