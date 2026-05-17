
@props([
    'canRequestEvent' => null,
    'eventRequestOrganizations' => null,
])

@php
    $resolvedCanRequestEvent = $canRequestEvent;
    $resolvedOrganizations = collect($eventRequestOrganizations ?? []);

    $authUser = auth()->user();

    if ($authUser) {
        $isOfficerOrPresident = $authUser
            ->memberships()
            ->whereHas('officers', function ($query) {
                $query->whereIn('role', ['officer', 'president']);
            })
            ->exists();

        if (! is_bool($resolvedCanRequestEvent)) {
            $resolvedCanRequestEvent = (int) $authUser->user_type === 2 || $isOfficerOrPresident;
        }

        if ($resolvedCanRequestEvent && $resolvedOrganizations->isEmpty()) {
            $resolvedOrganizations = \App\Models\Organization::query()
                ->join('members as m', 'm.organization', '=', 'organizations.organization_id')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'organizations.detail')
                ->where('m.user', (int) $authUser->getKey())
                ->orderBy('od.name')
                ->get([
                    'organizations.organization_id',
                    \Illuminate\Support\Facades\DB::raw("COALESCE(od.name, 'Unknown Organization') as name"),
                ])
                ->unique('organization_id')
                ->values();
        }
    }

    $resolvedCanRequestEvent = (bool) $resolvedCanRequestEvent;
@endphp

<div>
    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="custom-calendar">
            <div id="calendar" class="min-h-screen"
                data-can-request-event="{{ $resolvedCanRequestEvent ? '1' : '0' }}"
                data-event-request-endpoint="{{ route('api.events.requests.store') }}"
                data-officers-endpoint="/api/organizations/{id}/officers"></div>
        </div>
    </div>

    {{-- Day Summary Modal --}}
    <div class="fixed inset-0 items-center justify-center hidden p-3 overflow-y-auto sm:p-5 modal z-99999" id="daySummaryModal">
        <div class="modal-close-btn fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"></div>
        <div class="relative flex w-full max-w-[480px] flex-col overflow-hidden rounded-2xl bg-white p-5 sm:p-6 dark:bg-gray-900">
            <button class="day-summary-close transition-color absolute top-5 right-5 z-999 flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-600 sm:h-11 sm:w-11 dark:bg-white/[0.05] dark:text-gray-400 dark:hover:bg-white/[0.07] dark:hover:text-gray-300">
                <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5418C5.65237 16.9323 5.65237 17.5655 6.04289 17.956C6.43342 18.3465 7.06658 18.3465 7.45711 17.956L11.9987 13.4144L16.5408 17.9565C16.9313 18.347 17.5645 18.347 17.955 17.9565C18.3455 17.566 18.3455 16.9328 17.955 16.5423L13.4129 12.0002L17.955 7.45808C18.3455 7.06756 18.3455 6.43439 17.955 6.04387C17.5645 5.65335 16.9313 5.65335 16.5408 6.04387L11.9987 10.586L7.45711 6.04439C7.06658 5.65386 6.43342 5.65386 6.04289 6.04439C5.65237 6.43491 5.65237 7.06808 6.04289 7.4586L10.5845 12.0002L6.04289 16.5418Z" fill="" />
                </svg>
            </button>

            <h5 class="mb-1 text-xl font-semibold text-gray-800 dark:text-white/90" id="daySummaryDate"></h5>
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Events on this day</p>

            <div id="daySummaryEventList" class="mb-5 max-h-48 overflow-y-auto space-y-2">
                <p class="text-sm text-gray-400 dark:text-gray-500 italic">No events on this day.</p>
            </div>

            @if ($resolvedCanRequestEvent)
                <button type="button" id="open-event-plan-btn"
                    class="bg-brand-500 hover:bg-brand-600 inline-flex w-full items-center justify-center rounded-lg px-4 py-2.5 text-sm font-medium text-white transition-colors">
                    Create Event Plan
                </button>
            @endif
        </div>
    </div>

    {{-- Event Plan Modal --}}
    @if ($resolvedCanRequestEvent)
    <div class="fixed inset-0 items-center justify-center hidden p-3 overflow-y-auto sm:p-5 modal z-99999" id="eventPlanModal">
        <div class="modal-close-btn fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"></div>
        <div class="relative flex w-full max-w-[560px] max-h-[88vh] flex-col overflow-hidden rounded-2xl bg-white p-5 sm:p-6 dark:bg-gray-900">
            <button class="event-plan-close transition-color absolute top-5 right-5 z-999 flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-600 sm:h-11 sm:w-11 dark:bg-white/[0.05] dark:text-gray-400 dark:hover:bg-white/[0.07] dark:hover:text-gray-300">
                <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5418C5.65237 16.9323 5.65237 17.5655 6.04289 17.956C6.43342 18.3465 7.06658 18.3465 7.45711 17.956L11.9987 13.4144L16.5408 17.9565C16.9313 18.347 17.5645 18.347 17.955 17.9565C18.3455 17.566 18.3455 16.9328 17.955 16.5423L13.4129 12.0002L17.955 7.45808C18.3455 7.06756 18.3455 6.43439 17.955 6.04387C17.5645 5.65335 16.9313 5.65335 16.5408 6.04387L11.9987 10.586L7.45711 6.04439C7.06658 5.65386 6.43342 5.65386 6.04289 6.04439C5.65237 6.43491 5.65237 7.06808 6.04289 7.4586L10.5845 12.0002L6.04289 16.5418Z" fill="" />
                </svg>
            </button>

            <div class="flex flex-col px-1 overflow-y-auto custom-scrollbar">
                <div>
                    <h5 class="mb-2 text-xl font-semibold text-gray-800 dark:text-white/90">Create Event Plan</h5>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Submit an event plan for president approval.</p>
                </div>

                <div id="event-plan-feedback" class="mt-4 hidden rounded-lg border px-3 py-2 text-sm"></div>

                <div class="mt-5 space-y-5">
                    <div>
                        <label for="plan-organization" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Organization
                        </label>
                        <select id="plan-organization" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                            <option value="">Select organization</option>
                            @foreach ($resolvedOrganizations as $organization)
                                <option value="{{ $organization->organization_id }}">{{ $organization->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="plan-title" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Activity / Title
                        </label>
                        <input id="plan-title" type="text"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            placeholder="Enter activity or event title" />
                    </div>

                    <div>
                        <label for="plan-target-date" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Target Date
                        </label>
                        <input id="plan-target-date" type="date"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                    </div>

                    <div>
                        <label for="plan-resources" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Resources Needed
                            <span class="font-normal text-gray-400">(optional)</span>
                        </label>
                        <textarea id="plan-resources" rows="3"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            placeholder="List the resources, equipment, or materials needed"></textarea>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Persons Responsible
                            <span class="font-normal text-gray-400">(optional)</span>
                        </label>
                        <div id="persons-responsible-container" class="min-h-[40px] rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-xs text-gray-400 dark:text-gray-500 italic">Select an organization first.</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 mt-6 sm:justify-end">
                    <button type="button" class="event-plan-close flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 sm:w-auto dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                        Cancel
                    </button>
                    <button type="button" id="submit-event-plan-btn"
                        class="bg-brand-500 hover:bg-brand-600 flex w-full justify-center rounded-lg px-4 py-2.5 text-sm font-medium text-white sm:w-auto">
                        Submit Event Plan
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
