@props([
    'canRequestEvent' => null,
    'eventRequestOrganizations' => null,
    'lockedOrgIds' => [],
    'presidentName' => null,
    'presidentContact' => null,
    'allSemesters' => null,
    'workplanStatuses' => [],
])

@php
    $resolvedCanRequestEvent = $canRequestEvent;
    $resolvedOrganizations = collect($eventRequestOrganizations ?? []);
    $resolvedPresidentName = $presidentName ?? '';
    $resolvedPresidentContact = $presidentContact ?? '';

    $authUser = auth()->user();

    if ($authUser) {
        $isOfficerOrPresident = $authUser
            ->officers()
            ->whereIn('role', ['officer', 'president'])
            ->exists();

        if (!is_bool($resolvedCanRequestEvent)) {
            $resolvedCanRequestEvent = (int) $authUser->user_type === 2 || $isOfficerOrPresident;
        }

        if ($resolvedCanRequestEvent && $resolvedOrganizations->isEmpty()) {
            $resolvedOrganizations = \App\Models\Organization::query()
                ->join('organization_officers as oo', 'oo.organization', '=', 'organizations.organization_id')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'organizations.detail')
                ->where('oo.user', (int) $authUser->getKey())
                ->orderBy('od.name')
                ->get([
                    'organizations.organization_id',
                    \Illuminate\Support\Facades\DB::raw("COALESCE(od.name, 'Unknown Organization') as name"),
                ])
                ->unique('organization_id')
                ->values();
        }

        if ($resolvedPresidentName === '' || $resolvedPresidentContact === '') {
            $profileRow = \Illuminate\Support\Facades\DB::table('users as u')
                ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
                ->where('u.user_id', (int) $authUser->getKey())
                ->select(['p.first_name', 'p.middle_name', 'p.last_name', 'p.contact_number'])
                ->first();

            if ($resolvedPresidentName === '' && $profileRow) {
                $resolvedPresidentName = trim(
                    implode(
                        ' ',
                        array_filter([$profileRow->first_name, $profileRow->middle_name, $profileRow->last_name]),
                    ),
                );
            }

            if ($resolvedPresidentContact === '') {
                $resolvedPresidentContact = $profileRow?->contact_number ?? '';
            }
        }
    }

    $resolvedCanRequestEvent = (bool) $resolvedCanRequestEvent;

    $allOrgsGrouped = \Illuminate\Support\Facades\DB::table('organizations as o')
        ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
        ->where('o.organization_type', '!=', 5)
        ->orderBy('o.organization_type')
        ->orderBy('od.name')
        ->get(['o.organization_id', 'o.organization_type', \Illuminate\Support\Facades\DB::raw("COALESCE(od.name, 'Unknown Organization') as name")])
        ->groupBy('organization_type')
        ->map(fn ($group) => $group->values())
        ->toArray();

    $orgTypeLabels = [1 => 'Socio-Civic', 2 => 'Religious', 3 => 'Fraternities-Sororities', 4 => 'Special Interest', 6 => 'Student Government'];
@endphp

<div>
    {{-- Page-level success alert (shown after successful event plan submission) --}}
    <div id="calendar-success-alert"
        class="mb-4 hidden items-center justify-between gap-3 rounded-xl border border-success-200 bg-success-50 px-4 py-3 dark:border-success-500/30 dark:bg-success-500/10">
        <div class="flex items-center gap-2">
            <svg class="h-4 w-4 shrink-0 text-success-600 dark:text-success-400" fill="none" viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd"
                    d="M12 3.5C7.30558 3.5 3.5 7.30558 3.5 12C3.5 16.6944 7.30558 20.5 12 20.5C16.6944 20.5 20.5 16.6944 20.5 12C20.5 7.30558 16.6944 3.5 12 3.5ZM2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12ZM16.5303 9.46967C16.8232 9.76256 16.8232 10.2374 16.5303 10.5303L11.5303 15.5303C11.2374 15.8232 10.7626 15.8232 10.4697 15.5303L7.96967 13.0303C7.67678 12.7374 7.67678 12.2626 7.96967 11.9697C8.26256 11.6768 8.73744 11.6768 9.03033 11.9697L11 13.9393L15.4697 9.46967C15.7626 9.17678 16.2374 9.17678 16.5303 9.46967Z"
                    fill="currentColor" />
            </svg>
            <p id="calendar-success-message" class="text-sm font-medium text-success-700 dark:text-success-400"></p>
        </div>
        <button id="calendar-success-dismiss" type="button"
            class="shrink-0 text-success-500 hover:text-success-700 dark:text-success-400 dark:hover:text-success-200">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd"
                    d="M6.04289 16.5418C5.65237 16.9323 5.65237 17.5655 6.04289 17.956C6.43342 18.3465 7.06658 18.3465 7.45711 17.956L11.9987 13.4144L16.5408 17.9565C16.9313 18.347 17.5645 18.347 17.955 17.9565C18.3455 17.566 18.3455 16.9328 17.955 16.5423L13.4129 12.0002L17.955 7.45808C18.3455 7.06756 18.3455 6.43439 17.955 6.04387C17.5645 5.65335 16.9313 5.65335 16.5408 6.04387L11.9987 10.586L7.45711 6.04439C7.06658 5.65386 6.43342 5.65386 6.04289 6.04439C5.65237 6.43491 5.65237 7.06808 6.04289 7.4586L10.5845 12.0002L6.04289 16.5418Z"
                    fill="currentColor" />
            </svg>
        </button>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="custom-calendar">
            @php
                $semesterData = collect($allSemesters ?? [])->map(fn($s) => [
                    'id' => (int) $s->semester_id,
                    'starts_at' => $s->starts_at instanceof \Illuminate\Support\Carbon
                        ? $s->starts_at->toDateString()
                        : \Illuminate\Support\Carbon::parse($s->starts_at)->toDateString(),
                    'vacation_days' => (int) $s->vacation_days,
                ])->values();
            @endphp
            <div id="calendar" class="min-h-screen" data-can-request-event="{{ $resolvedCanRequestEvent ? '1' : '0' }}"
                data-event-request-endpoint="{{ route('api.events.requests.store') }}"
                data-direct-request-endpoint="{{ route('api.events.direct-request.store') }}"
                data-officers-endpoint="/api/organizations/{id}/officers"
                data-president-name="{{ e($resolvedPresidentName) }}"
                data-president-contact="{{ e($resolvedPresidentContact) }}"
                data-locked-org-ids="{{ json_encode(array_values(array_map('intval', $lockedOrgIds))) }}"
                data-semesters="{{ json_encode($semesterData->all()) }}"
                data-workplan-statuses="{{ json_encode($workplanStatuses ?? []) }}"
                data-today="{{ now()->toDateString() }}"></div>
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

            @if ($resolvedCanRequestEvent)
                <button type="button" id="open-event-plan-btn" data-create-mode="event-plan"
                    class="bg-brand-500 hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed inline-flex w-full items-center justify-center rounded-lg px-4 py-2.5 text-sm font-medium text-white transition-colors">
                    Create Event Plan
                </button>
            @endif
        </div>
    </div>

    {{-- Event Plan Drawer --}}
    @if ($resolvedCanRequestEvent)
        <div id="eventPlanDrawer"
            class="fixed inset-0 z-99999 pointer-events-none opacity-0 transition-opacity duration-300">
            <button type="button" data-event-plan-drawer-backdrop
                class="absolute inset-0 bg-gray-400/50 backdrop-blur-[24px]"></button>

            <div data-event-plan-drawer-panel
                class="relative ml-auto flex h-full w-full max-w-[980px] translate-x-full transform flex-col bg-white shadow-2xl transition-transform duration-300 dark:bg-gray-900">
                <div
                    class="flex items-start justify-between border-b border-gray-200 px-5 py-4 sm:px-6 dark:border-gray-800">
                    <div>
                        <h5 id="event-plan-drawer-title" class="text-xl font-semibold text-gray-800 dark:text-white/90">Create Event Plan</h5>
                        <p id="event-plan-drawer-subtitle" class="mt-1 text-sm text-gray-500 dark:text-gray-400">Submit an event plan for your workplan.</p>
                    </div>
                    <button type="button"
                        class="event-plan-close rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/[0.05] dark:hover:text-gray-300">
                        <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                d="M6.04289 16.5418C5.65237 16.9323 5.65237 17.5655 6.04289 17.956C6.43342 18.3465 7.06658 18.3465 7.45711 17.956L11.9987 13.4144L16.5408 17.9565C16.9313 18.347 17.5645 18.347 17.955 17.9565C18.3455 17.566 18.3455 16.9328 17.955 16.5423L13.4129 12.0002L17.955 7.45808C18.3455 7.06756 18.3455 6.43439 17.955 6.04387C17.5645 5.65335 16.9313 5.65335 16.5408 6.04387L11.9987 10.586L7.45711 6.04439C7.06658 5.65386 6.43342 5.65386 6.04289 6.04439C5.65237 6.43491 5.65237 7.06808 6.04289 7.4586L10.5845 12.0002L6.04289 16.5418Z"
                                fill="" />
                        </svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-5 sm:px-6">
                    <form id="eventPlanDrawerForm" action="{{ route('api.events.requests.store') }}" method="POST"
                        class="space-y-6" x-data="{
                            formMode: 'event-plan',
                            facilities: {{ Js::from(old('university_facilities', ['', ''])) }},
                            addFacility() {
                                if (this.facilities.length < 10) this.facilities.push('');
                            },
                            removeFacility(index) {
                                if (this.facilities.length > 1) this.facilities.splice(index, 1);
                            },
                            advisers: {{ Js::from(old('faculty_advisers', [''])) }},
                            addAdviser() {
                                this.advisers.push('');
                            },
                            removeAdviser(index) {
                                if (this.advisers.length > 1) this.advisers.splice(index, 1);
                            },
                            activityTypes: {{ Js::from(old('activity_types', [])) }},
                            activityTypeOther: {{ Js::from(old('activity_type_other', '')) }},
                            activityType: {{ Js::from(old('activity_type', '')) }},
                            seminarLevel: {{ Js::from(old('seminar_level', '')) }},
                            areaScope: {{ Js::from(old('area_scope', '')) }},
                            areaScopeOther: {{ Js::from(old('area_scope_other', '')) }},
                            sponsor: {{ Js::from(old('sponsor', '')) }},
                            sponsorOther: {{ Js::from(old('sponsor_other', '')) }},
                            cosponsorCount: 0,
                            relatedToOrg: {{ old('related_to_organization') ? 'true' : 'false' }},
                            extensionServices: {{ Js::from(old('extension_services', '')) }},
                        }" @calendar-form-mode.window="formMode = $event.detail.mode">
                        @csrf

                        <div id="event-plan-feedback" class="hidden rounded-lg border px-3 py-2 text-sm"></div>

                        <div
                            class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                            <h6
                                class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                                Organization Details</h6>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="plan-organization"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Organization</label>
                                    @if ($resolvedOrganizations->count() > 1)
                                        <select id="plan-organization" name="organization_id"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            <option value="">Select organization</option>
                                            @foreach ($resolvedOrganizations as $organization)
                                                <option value="{{ $organization->organization_id }}">
                                                    {{ $organization->name }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text"
                                            value="{{ $resolvedOrganizations->first()->name ?? 'Organization' }}"
                                            readonly
                                            class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-200 bg-gray-100 px-4 py-2.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300" />
                                        <input type="hidden" name="organization_id"
                                            value="{{ $resolvedOrganizations->first()->organization_id ?? '' }}" />
                                    @endif
                                </div>

                                <div>
                                    <label for="plan-title"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Activity
                                        / Title</label>
                                    <input id="plan-title" name="title" type="text"
                                        value="{{ old('title') }}" placeholder="Enter activity or event title"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                </div>

                                <div>
                                    <label for="plan-target-date"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Target
                                        Date</label>
                                    <input id="plan-target-date" name="target_date" type="date"
                                        value="{{ old('target_date') }}"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                </div>

                                <div x-show="formMode === 'event-plan'">
                                    <label for="plan-resources"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Resources
                                        Needed <span class="text-error-500">*</span></label>
                                    <textarea id="plan-resources" name="resources_needed" rows="3"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                        placeholder="List the resources, equipment, or materials needed">{{ old('resources_needed') }}</textarea>
                                </div>

                                <div class="sm:col-span-2" x-show="formMode === 'event-plan'">
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Persons
                                        Responsible <span class="text-error-500">*</span></label>
                                    <div id="persons-responsible-container"
                                        class="min-h-[40px] rounded-lg border border-gray-200 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
                                        <p class="text-xs text-gray-400 dark:text-gray-500 italic">Select an
                                            organization first.</p>
                                    </div>
                                </div>

                                <div>
                                    <label for="event-location"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Event
                                        Location</label>
                                    <input id="event-location" name="event_location" type="text"
                                        value="{{ old('event_location') }}" placeholder="Location or venue"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="event-start-time"
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Start
                                            Time</label>
                                        <input id="event-start-time" name="event_start_time" type="datetime-local"
                                            value="{{ old('event_start_time') }}"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                    </div>
                                    <div>
                                        <label for="event-end-time"
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">End
                                            Time</label>
                                        <input id="event-end-time" name="event_end_time" type="datetime-local"
                                            value="{{ old('event_end_time') }}"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                    </div>
                                </div>

                                <div class="sm:col-span-2" x-show="formMode === 'event-plan'">
                                    <label for="event-description"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Event
                                        Description</label>
                                    <textarea id="event-description" name="event_description" rows="3"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                        placeholder="Brief event description">{{ old('event_description') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                            <h6
                                class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                                Activity Request Details</h6>

                            <div class="space-y-5">
                                <div>
                                    <label for="purpose-of-activity"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Purpose
                                        of Activity <span class="text-error-500">*</span></label>
                                    <textarea id="purpose-of-activity" name="purpose_of_activity" rows="3"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                        placeholder="Describe the purpose of the activity">{{ old('purpose_of_activity') }}</textarea>
                                </div>

                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">University
                                        Facilities / Equipment to be Used</label>
                                    <div class="space-y-2">
                                        <template x-for="(item, index) in facilities" :key="index">
                                            <div class="flex items-center gap-2">
                                                <input type="text" :name="'university_facilities[' + index + ']'"
                                                    x-model="facilities[index]"
                                                    placeholder="e.g. Projector, Sound System, Chairs"
                                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                                <button type="button" @click="removeFacility(index)"
                                                    x-show="facilities.length > 1"
                                                    class="flex-shrink-0 rounded-lg border border-error-200 p-2 text-error-500 transition hover:bg-error-50 dark:border-error-500/30 dark:hover:bg-error-500/10">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                    <button type="button" @click="addFacility()" x-show="facilities.length < 10"
                                        class="mt-2 flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 4v16m8-8H4" />
                                        </svg>
                                        Add Facility / Equipment
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                            <h6
                                class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                                President Information</h6>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="president-name"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">President
                                        Name</label>
                                    <input id="president-name" name="president_name" type="text" readonly
                                        value="{{ old('president_name', $resolvedPresidentName) }}"
                                        class="h-11 w-full rounded-lg border border-gray-300 bg-gray-100 px-4 py-2.5 text-sm text-gray-800 cursor-not-allowed dark:border-gray-700 dark:bg-gray-800/50 dark:text-white/90" />
                                </div>
                                <div>
                                    <label for="president-contact"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Contact
                                        Number</label>
                                    <input id="president-contact" name="president_contact" type="text" readonly
                                        value="{{ old('president_contact', $resolvedPresidentContact) }}"
                                        class="h-11 w-full rounded-lg border border-gray-300 bg-gray-100 px-4 py-2.5 text-sm text-gray-800 cursor-not-allowed dark:border-gray-700 dark:bg-gray-800/50 dark:text-white/90" />
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                            <h6
                                class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                                Faculty Adviser/s</h6>
                            <p class="mb-4 pl-3 text-xs text-gray-500 dark:text-gray-400">Advisers will be listed in
                                the generated document and approval flow.</p>

                            <div class="space-y-2">
                                <template x-for="(adviser, index) in advisers" :key="index">
                                    <div class="flex items-center gap-2">
                                        <input type="text" :name="'faculty_advisers[' + index + ']'"
                                            x-model="advisers[index]"
                                            :placeholder="'Adviser ' + (index + 1) + ' full name'"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                        <button type="button" @click="removeAdviser(index)"
                                            x-show="advisers.length > 1"
                                            class="flex-shrink-0 rounded-lg border border-error-200 p-2 text-error-500 transition hover:bg-error-50 dark:border-error-500/30 dark:hover:bg-error-500/10">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                            <button type="button" @click="addAdviser()"
                                class="mt-2 flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                Add Adviser
                            </button>
                        </div>

                        <div
                            class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                            <h6
                                class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                                Event Type</h6>

                            <div class="space-y-6">
                                {{-- Event-plan mode: single-select radio --}}
                                <div x-show="formMode === 'event-plan'">
                                    <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-400">Select one <span class="text-error-500">*</span></p>
                                    <div class="space-y-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900/30">
                                        @foreach (['Seminar', 'Clean Up Drive', 'Conference', 'Workshop', 'Preparation', 'Meeting'] as $type)
                                            <label class="flex cursor-pointer items-center gap-3">
                                                <input type="radio" name="activity_type" value="{{ $type }}"
                                                    x-model="activityType"
                                                    class="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $type }}</span>
                                            </label>
                                        @endforeach
                                        <div class="space-y-2">
                                            <label class="flex cursor-pointer items-center gap-3">
                                                <input type="radio" name="activity_type" value="others"
                                                    x-model="activityType"
                                                    class="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                                <span class="text-sm text-gray-700 dark:text-gray-300">Others</span>
                                            </label>
                                            <div x-show="activityType === 'others'" x-transition class="pl-7">
                                                <input type="text" name="activity_type_other"
                                                    value="{{ old('activity_type_other') }}"
                                                    :required="activityType === 'others'"
                                                    placeholder="Specify other activity type"
                                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                            </div>
                                        </div>
                                        <div x-show="activityType === 'Seminar'" x-transition
                                            class="mt-2 rounded-lg border border-brand-200 bg-brand-50/50 p-3 dark:border-brand-700/50 dark:bg-brand-500/5">
                                            <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-400">Seminar Level <span class="text-error-500">*</span></p>
                                            <div class="flex gap-6">
                                                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                    <input type="radio" name="seminar_level" value="College"
                                                        x-model="seminarLevel" :required="activityType === 'Seminar'"
                                                        class="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                                    College Level
                                                </label>
                                                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                    <input type="radio" name="seminar_level" value="University"
                                                        x-model="seminarLevel" :required="activityType === 'Seminar'"
                                                        class="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                                    University Level
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Activity-request mode: multi-select checkboxes --}}
                                <div x-show="formMode === 'activity-request'">
                                    <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-400">Activity Type (select all that apply) <span class="text-error-500">*</span></p>
                                    <div class="space-y-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900/30">
                                        @foreach (['Seminar', 'Clean Up Drive', 'Donation', 'Conference', 'Workshop'] as $type)
                                            <label class="flex cursor-pointer items-center gap-3">
                                                <input type="checkbox" name="activity_types[]" value="{{ $type }}"
                                                    x-model="activityTypes"
                                                    class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $type }}</span>
                                            </label>
                                        @endforeach
                                        <div class="space-y-2">
                                            <label class="flex cursor-pointer items-center gap-3">
                                                <input type="checkbox" name="activity_types[]" value="others"
                                                    x-model="activityTypes"
                                                    class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                                <span class="text-sm text-gray-700 dark:text-gray-300">Others</span>
                                            </label>
                                            <div x-show="activityTypes.includes('others')" x-transition class="pl-7">
                                                <input type="text" name="activity_type_other" x-model="activityTypeOther"
                                                    placeholder="Specify other activity type"
                                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Related to Organization: event-plan only --}}
                                <div x-show="formMode === 'event-plan'">
                                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-400">
                                        <input type="checkbox" name="related_to_organization" value="1"
                                            x-model="relatedToOrg"
                                            class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                        Related to Organization?
                                        <span class="relative group cursor-default ml-1">
                                            <svg class="h-4 w-4 text-gray-400 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z" />
                                            </svg>
                                            <span class="pointer-events-none absolute left-1/2 bottom-full mb-1.5 -translate-x-1/2 w-56 rounded-lg bg-gray-900 px-3 py-2 text-xs text-white opacity-0 group-hover:opacity-100 transition-opacity dark:bg-gray-700 z-20 text-center">
                                                Related to the organizations' scope of topic, course.
                                            </span>
                                        </span>
                                    </label>
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="area-scope"
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Area
                                            Scope</label>
                                        <select id="area-scope" name="area_scope" x-model="areaScope" required
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                            <option value="">Select area scope</option>
                                            <option value="none" @selected(old('area_scope') === 'none')>None</option>
                                            <option value="Local" @selected(old('area_scope') === 'Local')>Local</option>
                                            <option value="Provincial" @selected(old('area_scope') === 'Provincial')>Provincial</option>
                                            <option value="Regional" @selected(old('area_scope') === 'Regional')>Regional</option>
                                            <option value="National" @selected(old('area_scope') === 'National')>National</option>
                                            <option value="International" @selected(old('area_scope') === 'International')>International</option>
                                            <option value="others" @selected(old('area_scope') === 'others')>Others</option>
                                        </select>
                                        <div x-show="areaScope === 'others'" x-transition class="mt-2">
                                            <input type="text" name="area_scope_other"
                                                value="{{ old('area_scope_other') }}"
                                                placeholder="Specify area scope"
                                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                        </div>
                                    </div>

                                    <div>
                                        <label for="sponsor"
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Sponsor</label>
                                        <select id="sponsor" name="sponsor" x-model="sponsor" required
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                            <option value="">Select sponsor</option>
                                            <option value="none" @selected(old('sponsor') === 'none')>None</option>
                                            <option value="N/A" @selected(old('sponsor') === 'N/A')>N/A</option>
                                            <option value="SSC" @selected(old('sponsor') === 'SSC')>SSC</option>
                                            <option value="Admin" @selected(old('sponsor') === 'Admin')>Admin</option>
                                            <option value="others" @selected(old('sponsor') === 'others')>Others</option>
                                            <option value="co-sponsors" @selected(old('sponsor') === 'co-sponsors')>Co-sponsors</option>
                                        </select>
                                        <div x-show="sponsor === 'others'" x-transition class="mt-2">
                                            <input type="text" name="sponsor_other"
                                                value="{{ old('sponsor_other') }}" placeholder="Specify sponsor"
                                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                        </div>
                                        <div x-show="sponsor === 'co-sponsors' && formMode === 'event-plan'" x-transition class="mt-2">
                                            <input type="hidden" name="cosponsor_count" :value="cosponsorCount" />
                                            <div class="max-h-48 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-900/30 space-y-2">
                                                @foreach ($allOrgsGrouped as $typeId => $orgs)
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 pt-1">{{ $orgTypeLabels[$typeId] ?? 'Other' }}</p>
                                                    @foreach ($orgs as $org)
                                                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                                            <input type="checkbox" value="{{ $org->organization_id }}"
                                                                @change="cosponsorCount = $el.closest('.max-h-48').querySelectorAll('input[type=checkbox]:checked').length"
                                                                class="h-3.5 w-3.5 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                                            {{ $org->name }}
                                                        </label>
                                                    @endforeach
                                                @endforeach
                                            </div>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-text="'Selected: ' + cosponsorCount + ' organization' + (cosponsorCount !== 1 ? 's' : '')"></p>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-400">Extension
                                        Services</p>
                                    <div class="flex flex-wrap gap-4">
                                        <label class="flex cursor-pointer items-center gap-2">
                                            <input type="radio" name="extension_services" value="yes"
                                                x-model="extensionServices" @checked(old('extension_services') === 'yes')
                                                required class="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Yes</span>
                                        </label>
                                        <label class="flex cursor-pointer items-center gap-2">
                                            <input type="radio" name="extension_services" value="no"
                                                x-model="extensionServices" @checked(old('extension_services') === 'no')
                                                class="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">No</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 pt-2 sm:justify-end">
                            <button type="button"
                                class="event-plan-close flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 sm:w-auto dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                                Cancel
                            </button>
                            <button type="submit"
                                class="bg-brand-500 hover:bg-brand-600 flex w-full justify-center rounded-lg px-4 py-2.5 text-sm font-medium text-white sm:w-auto"
                                x-text="formMode === 'activity-request' ? 'Submit Activity Request' : 'Submit Event Plan'">
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
