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

    // The "New Event" drawer now embeds the builder form bound to the new_event
    // system function. While no published form is bound, the create action is
    // disabled — the feature degrades to a friendly note instead of the drawer.
    $newEventForm = \App\Forms\SystemFunction::form(\App\Forms\SystemFunction::NEW_EVENT);
    $newEventEnabled = $newEventForm && $newEventForm->is_published && $newEventForm->route_name;
    $newEventRender = $newEventEnabled
        ? \App\Forms\FormRenderContext::build($newEventForm, request())
        : null;
@endphp

<div>

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
                data-locked-org-ids="{{ json_encode(array_values(array_map('intval', $lockedOrgIds))) }}"
                data-semesters="{{ json_encode($semesterData->all()) }}"
                data-workplan-statuses="{{ json_encode($workplanStatuses ?? []) }}"
                data-event-scope="current"
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
                @if ($newEventEnabled)
                    <button type="button" id="open-event-plan-btn" data-create-mode="event-plan"
                        class="bg-brand-500 hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed inline-flex w-full items-center justify-center rounded-lg px-4 py-2.5 text-sm font-medium text-white transition-colors">
                        Create Event Plan
                    </button>
                @else
                    <p class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-400">
                        Event requests are currently unavailable. An admin must publish a New Event form first.
                    </p>
                @endif
            @endif
        </div>
    </div>

    {{-- Event Plan Modal — embeds the builder form bound to the new_event
         system function. Shown only when a published form is bound. A failed
         submission redirects back here with flashed errors/old input; unlike
         the standalone /forms/{routeName} page, the drawer starts closed, so
         it must auto-open (see data-has-errors below) and surface the errors
         itself instead of relying on pages.form.render's alert block. --}}
    @if ($resolvedCanRequestEvent && $newEventRender)
        <div id="eventPlanDrawer" data-has-errors="{{ $errors->any() ? '1' : '0' }}"
            class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-4 pointer-events-none opacity-0 transition-opacity duration-300 sm:p-6">
            <button type="button" data-event-plan-drawer-backdrop
                class="absolute inset-0 bg-gray-400/50 backdrop-blur-[24px]"></button>

            <div data-event-plan-drawer-panel
                class="relative flex max-h-[90vh] w-full max-w-[720px] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-900">
                <div class="flex items-start justify-between border-b border-gray-200 px-5 py-4 sm:px-6 dark:border-gray-800">
                    <div>
                        <h5 class="text-xl font-semibold text-gray-800 dark:text-white/90">{{ $newEventForm->name }}</h5>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $newEventForm->description_text ?: 'Submit an event request for admin approval.' }}</p>
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
                    @if (session('success'))
                        <div class="mb-5 rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-5 rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    @include('components.form.builder-form', array_merge($newEventRender, [
                        'preview' => false,
                        'submitLabel' => 'Submit event request',
                    ]))
                </div>
            </div>
        </div>
    @endif
</div>
