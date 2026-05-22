@props(['plan', 'personNames' => [], 'orgNames' => [], 'status', 'hasPendingEventRequest' => false])

@php
    $orgName = $orgNames[(int) $plan->organization_id] ?? 'Unknown Organization';
    $resolvedPresidentName = '';
    $resolvedPresidentContact = '';
    $authUser = auth()->user();

    if ($authUser) {
        $profileRow = \Illuminate\Support\Facades\DB::table('users as u')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->where('u.user_id', (int) $authUser->getKey())
            ->select(['p.first_name', 'p.middle_name', 'p.last_name', 'p.contact_number'])
            ->first();

        if ($profileRow) {
            $resolvedPresidentName = trim(
                implode(' ', array_filter([$profileRow->first_name, $profileRow->middle_name, $profileRow->last_name])),
            );
            $resolvedPresidentContact = $profileRow->contact_number ?? '';
        }
    }

    $resolvedPersons = collect($plan->persons_responsible ?? [])
        ->map(fn($id) => $personNames[$id] ?? 'User #' . $id)
        ->filter()
        ->values();
    $hasEvent = $plan->event_id !== null;

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

    $statusBadge = match ($status) {
        'pending' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
        'approved' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
        'rejected' => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
        default => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    };

    $accentLine = match ($status) {
        'pending' => 'bg-warning-400',
        'approved' => 'bg-success-400',
        'rejected' => 'bg-error-400',
        default => 'bg-gray-300 dark:bg-gray-600',
    };
    $reviseSelected = array_values(array_map('intval', $plan->persons_responsible ?? []));
@endphp

<div x-data="{
    open: false,
    showCreateForm: false,
    showReviseForm: false,
    reviseOfficers: [],
    reviseSelected: {{ Js::from($reviseSelected) }},
    reviseLoading: false,
    async openReviseForm() {
        this.showReviseForm = !this.showReviseForm;
        if (this.showReviseForm && this.reviseOfficers.length === 0) {
            this.reviseLoading = true;
            try {
                const r = await fetch('/api/organizations/{{ (int) $plan->organization_id }}/officers');
                this.reviseOfficers = await r.json();
            } finally {
                this.reviseLoading = false;
            }
        }
    }
}"
    class="group relative overflow-hidden rounded-xl border border-gray-100 bg-white transition-shadow duration-200 hover:shadow-sm dark:border-gray-800 dark:bg-white/[0.02]">
    {{-- Status accent line --}}
    <div class="absolute left-0 top-0 h-full w-0.5 {{ $accentLine }}"></div>

    {{-- Collapsed header — always visible, clickable --}}
    <button type="button" @click="open = !open" class="flex w-full items-center gap-3 px-5 py-3.5 text-left"
        :aria-expanded="open">
        {{-- Chevron --}}
        <span
            class="flex h-5 w-5 shrink-0 items-center justify-center text-gray-400 transition-transform duration-200 dark:text-gray-500"
            :class="open ? 'rotate-90' : ''">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6" />
            </svg>
        </span>

        {{-- Title + badges --}}
        <span class="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1">
            <span class="truncate text-sm font-semibold text-gray-800 dark:text-white/90">{{ $plan->title }}</span>
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusBadge }}">
                {{ ucfirst($status) }}
            </span>
            @if ($hasEvent)
                <span
                    class="inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">
                    Event Created
                </span>
            @endif
        </span>

        {{-- Meta — org & date, hidden on small screens --}}
        <span class="hidden shrink-0 text-right text-xs text-gray-400 dark:text-gray-500 sm:block">
            <span class="block">{{ $orgName }}</span>
            <span class="block">{{ $plan->target_date?->format('M d, Y') ?? '—' }}</span>
        </span>
    </button>

    {{-- Expanded detail panel --}}
    <div x-show="open" x-collapse x-cloak>
        <div class="border-t border-gray-100 px-5 pb-4 pt-3 dark:border-gray-800">

            {{-- Mobile meta --}}
            <p class="mb-3 text-xs text-gray-500 dark:text-gray-400 sm:hidden">
                {{ $orgName }} &middot; Target: {{ $plan->target_date?->format('M d, Y') ?? '—' }}
            </p>

            {{-- Details grid --}}
            <dl class="mb-4 grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
                @if ($resolvedPersons->isNotEmpty())
                    <div>
                        <dt class="text-xs font-medium text-gray-400 dark:text-gray-500">Persons Responsible</dt>
                        <dd class="mt-0.5 text-sm text-gray-700 dark:text-gray-300">
                            {{ $resolvedPersons->implode(', ') }}</dd>
                    </div>
                @endif

                @if ($plan->resources_needed)
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium text-gray-400 dark:text-gray-500">Resources Needed</dt>
                        <dd class="mt-0.5 text-sm text-gray-700 dark:text-gray-300">{{ $plan->resources_needed }}</dd>
                    </div>
                @endif
            </dl>

            {{-- Actions --}}
            <div class="flex flex-wrap gap-2">
                @if ($status === 'approved' && !$hasEvent && !$hasPendingEventRequest)
                    <button type="button" @click="showCreateForm = !showCreateForm"
                        class="inline-flex items-center rounded-lg border border-brand-300 bg-brand-50 px-3 py-1.5 text-xs font-medium text-brand-700 hover:bg-brand-100 dark:border-brand-600 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/20">
                        Create Event
                    </button>
                @endif

                @if ($status === 'approved' && !$hasEvent && $hasPendingEventRequest)
                    <span
                        class="inline-flex items-center rounded-lg border border-warning-300 bg-warning-50 px-3 py-1.5 text-xs font-medium text-warning-700 dark:border-warning-600 dark:bg-warning-500/10 dark:text-warning-400">
                        Event Request Pending Approval
                    </span>
                @endif

                @if ($hasEvent)
                    <a href="{{ route('calendar') }}"
                        class="inline-flex items-center rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                        View Calendar
                    </a>
                @endif

                @if ($status === 'rejected')
                    <button type="button" @click="openReviseForm()"
                        class="inline-flex items-center rounded-lg border border-warning-300 bg-warning-50 px-3 py-1.5 text-xs font-medium text-warning-700 hover:bg-warning-100 dark:border-warning-600 dark:bg-warning-500/10 dark:text-warning-400 dark:hover:bg-warning-500/20">
                        Edit &amp; Resubmit
                    </button>
                @endif

                @if (in_array($status, ['pending', 'approved']))
                    <form method="POST" action="{{ route('event-plans.junk', $plan->event_plan_id) }}"
                        onsubmit="return confirm('Are you sure you want to junk this event plan?')">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                            class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-500 hover:bg-gray-50 hover:text-error-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:text-error-400">
                            Junk
                        </button>
                    </form>
                @endif
            </div>

            {{-- Inline Revise & Resubmit Form --}}
            @if ($status === 'rejected')
                <div x-show="showReviseForm" x-cloak
                    class="mt-4 rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-700 dark:bg-warning-500/10">
                    <h4 class="mb-3 text-sm font-semibold text-warning-700 dark:text-warning-400">Edit &amp; Resubmit
                        Plan</h4>
                    @php
                        $reviseFacilities     = old('university_facilities', array_values($plan->university_facilities ?? ['', '']));
                        $reviseAdvisers       = old('faculty_advisers', array_values($plan->faculty_advisers ?? ['']));
                        $reviseActivityType        = old('activity_type', is_array($plan->activity_types) ? ($plan->activity_types[0] ?? '') : ($plan->activity_types ?? ''));
                        $reviseActivityOther       = old('activity_type_other', $plan->activity_types_other ?? '');
                        $reviseSeminarLevel        = old('seminar_level', $plan->seminar_level ?? '');
                        $reviseAreaScope           = old('area_scope', $plan->area_scope ?? '');
                        $reviseAreaScopeOther      = old('area_scope_other', $plan->area_scope_other ?? '');
                        $reviseSponsor             = old('sponsor', $plan->sponsor ?? '');
                        $reviseSponsorOther        = old('sponsor_other', $plan->sponsor_other ?? '');
                        $reviseCosponsorCount      = old('cosponsor_count', $plan->cosponsor_count ?? 0);
                        $reviseRelatedToOrg        = old('related_to_organization', $plan->related_to_organization ?? false);
                        $reviseExtension           = old('extension_services', $plan->extension_services ? 'yes' : 'no');
                    @endphp
                    <form method="POST" action="{{ route('event-plans.revise', $plan->event_plan_id) }}"
                        class="space-y-3" x-data="{
                            facilities: {{ Js::from($reviseFacilities) }},
                            addFacility() {
                                if (this.facilities.length < 10) this.facilities.push('');
                            },
                            removeFacility(index) {
                                if (this.facilities.length > 1) this.facilities.splice(index, 1);
                            },
                            advisers: {{ Js::from($reviseAdvisers) }},
                            addAdviser() {
                                this.advisers.push('');
                            },
                            removeAdviser(index) {
                                if (this.advisers.length > 1) this.advisers.splice(index, 1);
                            },
                            activityType: {{ Js::from($reviseActivityType) }},
                            activityTypeOther: {{ Js::from($reviseActivityOther) }},
                            seminarLevel: {{ Js::from($reviseSeminarLevel) }},
                            areaScope: {{ Js::from($reviseAreaScope) }},
                            areaScopeOther: {{ Js::from($reviseAreaScopeOther) }},
                            sponsor: {{ Js::from($reviseSponsor) }},
                            sponsorOther: {{ Js::from($reviseSponsorOther) }},
                            cosponsorCount: {{ (int) $reviseCosponsorCount }},
                            relatedToOrg: {{ $reviseRelatedToOrg ? 'true' : 'false' }},
                            extensionServices: {{ Js::from($reviseExtension) }},
                        }">
                        @csrf
                        @method('PATCH')
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Title</label>
                                <input type="text" name="title" value="{{ old('title', $plan->title) }}" required
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Target
                                    Date</label>
                                <input type="date" name="target_date"
                                    value="{{ old('target_date', $plan->target_date?->format('Y-m-d')) }}" required
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Resources
                                    Needed <span class="text-error-500">*</span></label>
                                <textarea name="resources_needed" rows="2"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90">{{ old('resources_needed', $plan->resources_needed) }}</textarea>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                    Persons Responsible <span class="text-error-500">*</span>
                                </label>
                                <div
                                    class="min-h-[60px] max-h-36 overflow-y-auto rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-900">
                                    <template x-if="reviseLoading">
                                        <p class="text-xs text-gray-400 italic">Loading officers…</p>
                                    </template>
                                    <template x-if="!reviseLoading && reviseOfficers.length === 0">
                                        <p class="text-xs text-gray-400 italic">No officers found for this organization.
                                        </p>
                                    </template>
                                    <template x-for="officer in reviseOfficers" :key="officer.user_id">
                                        <label class="flex cursor-pointer items-center gap-2 py-1">
                                            <input type="checkbox" :value="officer.user_id" x-model="reviseSelected"
                                                class="h-3.5 w-3.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                            <span class="text-xs text-gray-700 dark:text-gray-300"
                                                x-text="officer.name"></span>
                                        </label>
                                    </template>
                                </div>
                                <template x-for="id in reviseSelected" :key="id">
                                    <input type="hidden" name="persons_responsible[]" :value="id" />
                                </template>
                            </div>
                        </div>
                        <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            Activity Request Details</p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Purpose
                                    of Activity <span class="text-error-500">*</span></label>
                                <textarea name="purpose_of_activity" rows="2" required
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90"
                                    placeholder="Describe the purpose of the activity">{{ old('purpose_of_activity', $plan->purpose_of_activity ?? '') }}</textarea>
                            </div>
                            <div class="sm:col-span-2">
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">University
                                    Facilities / Equipment to be Used <span class="text-error-500">*</span></label>
                                <div class="space-y-2">
                                    <template x-for="(item, index) in facilities" :key="index">
                                        <div class="flex items-center gap-2">
                                            <input type="text" :name="'university_facilities[' + index + ']'"
                                                x-model="facilities[index]" required
                                                class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90"
                                                placeholder="e.g. Projector, Sound System, Chairs" />
                                            <button type="button" @click="removeFacility(index)"
                                                x-show="facilities.length > 1"
                                                class="rounded-lg border border-error-200 p-2 text-error-500 hover:bg-error-50 dark:border-error-500/30 dark:hover:bg-error-500/10">
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
                                    class="mt-2 text-xs font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">
                                    Add Facility / Equipment
                                </button>
                            </div>
                        </div>

                        <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            President &amp; Advisers</p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">President
                                    Name <span class="text-error-500">*</span></label>
                                <input type="text" name="president_name" required readonly
                                    value="{{ old('president_name', $resolvedPresidentName) }}"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-gray-100 px-3 text-sm text-gray-800 cursor-not-allowed dark:border-gray-600 dark:bg-gray-800/50 dark:text-white/90" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Contact
                                    Number <span class="text-error-500">*</span></label>
                                <input type="text" name="president_contact" required readonly
                                    value="{{ old('president_contact', $resolvedPresidentContact) }}"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-gray-100 px-3 text-sm text-gray-800 cursor-not-allowed dark:border-gray-600 dark:bg-gray-800/50 dark:text-white/90" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Faculty
                                    Advisers <span class="text-error-500">*</span></label>
                                <div class="space-y-2">
                                    <template x-for="(adviser, index) in advisers" :key="index">
                                        <div class="flex items-center gap-2">
                                            <input type="text" :name="'faculty_advisers[' + index + ']'"
                                                x-model="advisers[index]" required
                                                class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90"
                                                :placeholder="'Adviser ' + (index + 1) + ' full name'" />
                                            <button type="button" @click="removeAdviser(index)"
                                                x-show="advisers.length > 1"
                                                class="rounded-lg border border-error-200 p-2 text-error-500 hover:bg-error-50 dark:border-error-500/30 dark:hover:bg-error-500/10">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                                <button type="button" @click="addAdviser()"
                                    class="mt-2 text-xs font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">
                                    Add Adviser
                                </button>
                            </div>
                        </div>

                        <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            Event Type</p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2 space-y-2 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/30">
                                <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Select one <span class="text-error-500">*</span></p>
                                @foreach (['Seminar', 'Clean Up Drive', 'Conference', 'Workshop', 'Preparation', 'Meeting'] as $type)
                                    <label class="flex cursor-pointer items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                                        <input type="radio" name="activity_type" value="{{ $type }}"
                                            x-model="activityType" required
                                            class="h-3.5 w-3.5 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                        <span>{{ $type }}</span>
                                    </label>
                                @endforeach
                                <label class="flex cursor-pointer items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                                    <input type="radio" name="activity_type" value="others"
                                        x-model="activityType" required
                                        class="h-3.5 w-3.5 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                    <span>Others</span>
                                </label>
                                <div x-show="activityType === 'others'" x-transition class="pl-5">
                                    <input type="text" name="activity_type_other"
                                        value="{{ old('activity_type_other', $plan->activity_types_other ?? '') }}"
                                        :required="activityType === 'others'"
                                        class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90"
                                        placeholder="Specify other activity type" />
                                </div>
                                <div x-show="activityType === 'Seminar'" x-transition
                                    class="mt-2 rounded-lg border border-brand-200 bg-brand-50/50 p-3 dark:border-brand-700/50 dark:bg-brand-500/5">
                                    <p class="mb-2 text-xs font-medium text-gray-600 dark:text-gray-400">Seminar Level <span class="text-error-500">*</span></p>
                                    <div class="flex gap-4">
                                        <label class="flex cursor-pointer items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                                            <input type="radio" name="seminar_level" value="College"
                                                x-model="seminarLevel"
                                                :required="activityType === 'Seminar'"
                                                class="h-3.5 w-3.5 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                            College Level
                                        </label>
                                        <label class="flex cursor-pointer items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                                            <input type="radio" name="seminar_level" value="University"
                                                x-model="seminarLevel"
                                                :required="activityType === 'Seminar'"
                                                class="h-3.5 w-3.5 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                            University Level
                                        </label>
                                    </div>
                                </div>
                            </div>
                            {{-- Related to Organization --}}
                            <div class="sm:col-span-2">
                                <label class="flex items-center gap-2 text-xs font-medium text-gray-600 dark:text-gray-400">
                                    <input type="checkbox" name="related_to_organization" value="1"
                                        x-model="relatedToOrg"
                                        class="h-3.5 w-3.5 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                    Related to Organization?
                                    <span class="relative group cursor-default ml-0.5">
                                        <svg class="h-3.5 w-3.5 text-gray-400 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z" />
                                        </svg>
                                        <span class="pointer-events-none absolute left-1/2 bottom-full mb-1.5 -translate-x-1/2 w-52 rounded-lg bg-gray-900 px-3 py-2 text-xs text-white opacity-0 group-hover:opacity-100 transition-opacity dark:bg-gray-700 z-20 text-center">
                                            Related to the organizations' scope of topic, course.
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Area
                                    Scope</label>
                                <select name="area_scope" x-model="areaScope"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90">
                                    <option value="">Select area scope</option>
                                    <option value="none" @selected(old('area_scope', $plan->area_scope ?? '') === 'none')>None</option>
                                    <option value="Local" @selected(old('area_scope', $plan->area_scope ?? '') === 'Local')>Local</option>
                                    <option value="Provincial" @selected(old('area_scope', $plan->area_scope ?? '') === 'Provincial')>Provincial</option>
                                    <option value="Regional" @selected(old('area_scope', $plan->area_scope ?? '') === 'Regional')>Regional</option>
                                    <option value="National" @selected(old('area_scope', $plan->area_scope ?? '') === 'National')>National</option>
                                    <option value="International" @selected(old('area_scope', $plan->area_scope ?? '') === 'International')>International</option>
                                    <option value="others" @selected(old('area_scope', $plan->area_scope ?? '') === 'others')>Others</option>
                                </select>
                                <div x-show="areaScope === 'others'" class="mt-2">
                                    <input type="text" name="area_scope_other"
                                        value="{{ old('area_scope_other', $plan->area_scope_other ?? '') }}"
                                        class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90"
                                        placeholder="Specify area scope" />
                                </div>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Sponsor</label>
                                <select name="sponsor" x-model="sponsor"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90">
                                    <option value="">Select sponsor</option>
                                    <option value="none" @selected(old('sponsor', $plan->sponsor ?? '') === 'none')>None</option>
                                    <option value="N/A" @selected(old('sponsor', $plan->sponsor ?? '') === 'N/A')>N/A</option>
                                    <option value="SSC" @selected(old('sponsor', $plan->sponsor ?? '') === 'SSC')>SSC</option>
                                    <option value="Admin" @selected(old('sponsor', $plan->sponsor ?? '') === 'Admin')>Admin</option>
                                    <option value="others" @selected(old('sponsor', $plan->sponsor ?? '') === 'others')>Others</option>
                                    <option value="co-sponsors" @selected(old('sponsor', $plan->sponsor ?? '') === 'co-sponsors')>Co-sponsors</option>
                                </select>
                                <div x-show="sponsor === 'others'" class="mt-2">
                                    <input type="text" name="sponsor_other"
                                        value="{{ old('sponsor_other', $plan->sponsor_other ?? '') }}"
                                        class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90"
                                        placeholder="Specify sponsor" />
                                </div>
                                <div x-show="sponsor === 'co-sponsors'" x-transition class="mt-2">
                                    <input type="hidden" name="cosponsor_count" :value="cosponsorCount" />
                                    <div class="max-h-40 overflow-y-auto rounded-lg border border-gray-200 bg-white p-2 dark:border-gray-600 dark:bg-gray-900 space-y-1.5">
                                        @foreach ($allOrgsGrouped as $typeId => $orgs)
                                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 pt-1">{{ $orgTypeLabels[$typeId] ?? 'Other' }}</p>
                                            @foreach ($orgs as $org)
                                                <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300 cursor-pointer">
                                                    <input type="checkbox" value="{{ $org->organization_id }}"
                                                        @change="cosponsorCount = $el.closest('.max-h-40').querySelectorAll('input[type=checkbox]:checked').length"
                                                        class="h-3 w-3 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                                    {{ $org->name }}
                                                </label>
                                            @endforeach
                                        @endforeach
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-text="'Selected: ' + cosponsorCount + ' org' + (cosponsorCount !== 1 ? 's' : '')"></p>
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Extension
                                    Services</label>
                                <div class="flex gap-4">
                                    <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                                        <input type="radio" name="extension_services" value="yes"
                                            x-model="extensionServices" @checked(old('extension_services', $plan->extension_services ? 'yes' : 'no') === 'yes')
                                            class="h-3.5 w-3.5 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                        Yes
                                    </label>
                                    <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                                        <input type="radio" name="extension_services" value="no"
                                            x-model="extensionServices" @checked(old('extension_services', $plan->extension_services ? 'yes' : 'no') === 'no')
                                            class="h-3.5 w-3.5 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                        No
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 justify-end">
                            <button type="button" @click="showReviseForm = false"
                                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                Cancel
                            </button>
                            <button type="submit"
                                class="rounded-lg bg-warning-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-warning-600">
                                Resubmit for Approval
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            {{-- Inline Create Event Form --}}
            @if ($status === 'approved' && !$hasEvent && !$hasPendingEventRequest)
                @php
                    $createSelected       = array_values(array_map('intval', $plan->persons_responsible ?? []));
                    $createFacilities     = old('university_facilities', array_values($plan->university_facilities ?? ['', '']));
                    $createAdvisers       = old('faculty_advisers', array_values($plan->faculty_advisers ?? ['']));
                    $createActivityType    = old('activity_type', is_array($plan->activity_types) ? ($plan->activity_types[0] ?? '') : ($plan->activity_types ?? ''));
                    $createActivityOther  = old('activity_type_other', $plan->activity_types_other ?? '');
                    $createSeminarLevel   = old('seminar_level', $plan->seminar_level ?? '');
                    $createAreaScope      = old('area_scope', $plan->area_scope ?? '');
                    $createAreaScopeOther = old('area_scope_other', $plan->area_scope_other ?? '');
                    $createSponsor        = old('sponsor', $plan->sponsor ?? '');
                    $createSponsorOther   = old('sponsor_other', $plan->sponsor_other ?? '');
                    $createCosponsorCount = old('cosponsor_count', $plan->cosponsor_count ?? 0);
                    $createRelatedToOrg   = old('related_to_organization', $plan->related_to_organization ?? false);
                    $createExtension      = old('extension_services', $plan->extension_services ? 'yes' : 'no');
                @endphp
                <div x-show="showCreateForm" x-cloak x-data="{
                    createOfficers: [],
                    createSelected: {{ Js::from($createSelected) }},
                    createLoading: false,
                    facilities: {{ Js::from($createFacilities) }},
                    addFacility() {
                        if (this.facilities.length < 10) this.facilities.push('');
                    },
                    removeFacility(index) {
                        if (this.facilities.length > 1) this.facilities.splice(index, 1);
                    },
                    advisers: {{ Js::from($createAdvisers) }},
                    addAdviser() {
                        this.advisers.push('');
                    },
                    removeAdviser(index) {
                        if (this.advisers.length > 1) this.advisers.splice(index, 1);
                    },
                    activityType: {{ Js::from($createActivityType) }},
                    activityTypeOther: {{ Js::from($createActivityOther) }},
                    seminarLevel: {{ Js::from($createSeminarLevel) }},
                    areaScope: {{ Js::from($createAreaScope) }},
                    areaScopeOther: {{ Js::from($createAreaScopeOther) }},
                    sponsor: {{ Js::from($createSponsor) }},
                    sponsorOther: {{ Js::from($createSponsorOther) }},
                    cosponsorCount: {{ (int) $createCosponsorCount }},
                    relatedToOrg: {{ $createRelatedToOrg ? 'true' : 'false' }},
                    extensionServices: {{ Js::from($createExtension) }},
                    async loadOfficers() {
                        if (this.createOfficers.length > 0) return;
                        this.createLoading = true;
                        try {
                            const r = await fetch('/api/organizations/{{ (int) $plan->organization_id }}/officers');
                            this.createOfficers = await r.json();
                        } finally {
                            this.createLoading = false;
                        }
                    }
                }" x-init="$watch('showCreateForm', v => v && loadOfficers())"
                    class="mt-4 rounded-xl border border-brand-200 bg-brand-50/40 p-4 dark:border-brand-700 dark:bg-brand-500/5">
                    <h4 class="mb-1 text-sm font-semibold text-gray-700 dark:text-white/80">Submit Event Creation
                        Request</h4>
                    <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">This request will be sent to the admin for
                        approval before the event appears on the calendar.</p>
                    <form method="POST" action="{{ route('event-plans.create-event', $plan->event_plan_id) }}"
                        class="space-y-3">
                        @csrf

                        {{-- Plan fields --}}
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Event
                            Plan Details</p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Title
                                    <span class="text-error-500">*</span></label>
                                <input type="text" name="title" value="{{ $plan->title }}" required
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Target
                                    Date <span class="text-error-500">*</span></label>
                                <input type="date" name="target_date"
                                    value="{{ $plan->target_date?->format('Y-m-d') }}" required
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                            <div class="sm:col-span-2">
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Resources
                                    Needed <span class="text-error-500">*</span></label>
                                <textarea name="resources_needed" rows="2" required
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90">{{ $plan->resources_needed }}</textarea>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Persons
                                    Responsible <span class="text-error-500">*</span></label>
                                <div
                                    class="min-h-[48px] max-h-32 overflow-y-auto rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-900">
                                    <template x-if="createLoading">
                                        <p class="text-xs text-gray-400 italic">Loading officers…</p>
                                    </template>
                                    <template x-if="!createLoading && createOfficers.length === 0">
                                        <p class="text-xs text-gray-400 italic">No officers found.</p>
                                    </template>
                                    <template x-for="officer in createOfficers" :key="officer.user_id">
                                        <label class="flex cursor-pointer items-center gap-2 py-1">
                                            <input type="checkbox" :value="officer.user_id" x-model="createSelected"
                                                class="h-3.5 w-3.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                            <span class="text-xs text-gray-700 dark:text-gray-300"
                                                x-text="officer.name"></span>
                                        </label>
                                    </template>
                                </div>
                                <template x-for="id in createSelected" :key="id">
                                    <input type="hidden" name="persons_responsible[]" :value="id" />
                                </template>
                            </div>
                        </div>

                        {{-- Event-specific fields --}}
                        <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            Event Details</p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Location
                                    <span class="text-error-500">*</span></label>
                                <input type="text" name="event_location" required
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90"
                                    placeholder="e.g. Main Hall, Room 201" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Start
                                    Date &amp; Time <span class="text-error-500">*</span></label>
                                <input type="datetime-local" name="event_start_time" required
                                    value="{{ $plan->target_date?->format('Y-m-d') }}T09:00"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">End Date
                                    &amp; Time <span class="text-error-500">*</span></label>
                                <input type="datetime-local" name="event_end_time" required
                                    value="{{ $plan->target_date?->format('Y-m-d') }}T17:00"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                            <div class="sm:col-span-2">
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Description
                                    <span class="text-error-500">*</span></label>
                                <textarea name="event_description" rows="2" required
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90"
                                    placeholder="Brief description of the event"></textarea>
                            </div>
                        </div>

                        <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            Activity Request Details</p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Purpose
                                    of Activity <span class="text-error-500">*</span></label>
                                <textarea name="purpose_of_activity" rows="2" required
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90"
                                    placeholder="Describe the purpose of the activity">{{ old('purpose_of_activity', $plan->purpose_of_activity ?? '') }}</textarea>
                            </div>
                            <div class="sm:col-span-2">
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">University
                                    Facilities / Equipment to be Used <span class="text-error-500">*</span></label>
                                <div class="space-y-2">
                                    <template x-for="(item, index) in facilities" :key="index">
                                        <div class="flex items-center gap-2">
                                            <input type="text" :name="'university_facilities[' + index + ']'"
                                                x-model="facilities[index]" required
                                                class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90"
                                                placeholder="e.g. Projector, Sound System, Chairs" />
                                            <button type="button" @click="removeFacility(index)"
                                                x-show="facilities.length > 1"
                                                class="rounded-lg border border-error-200 p-2 text-error-500 hover:bg-error-50 dark:border-error-500/30 dark:hover:bg-error-500/10">
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
                                    class="mt-2 text-xs font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">
                                    Add Facility / Equipment
                                </button>
                            </div>
                        </div>

                        <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            President &amp; Advisers</p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">President
                                    Name <span class="text-error-500">*</span></label>
                                <input type="text" name="president_name" required readonly
                                    value="{{ old('president_name', $resolvedPresidentName) }}"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-gray-100 px-3 text-sm text-gray-800 cursor-not-allowed dark:border-gray-600 dark:bg-gray-800/50 dark:text-white/90" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Contact
                                    Number <span class="text-error-500">*</span></label>
                                <input type="text" name="president_contact" required readonly
                                    value="{{ old('president_contact', $resolvedPresidentContact) }}"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-gray-100 px-3 text-sm text-gray-800 cursor-not-allowed dark:border-gray-600 dark:bg-gray-800/50 dark:text-white/90" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Faculty
                                    Advisers <span class="text-error-500">*</span></label>
                                <div class="space-y-2">
                                    <template x-for="(adviser, index) in advisers" :key="index">
                                        <div class="flex items-center gap-2">
                                            <input type="text" :name="'faculty_advisers[' + index + ']'"
                                                x-model="advisers[index]" required
                                                class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90"
                                                :placeholder="'Adviser ' + (index + 1) + ' full name'" />
                                            <button type="button" @click="removeAdviser(index)"
                                                x-show="advisers.length > 1"
                                                class="rounded-lg border border-error-200 p-2 text-error-500 hover:bg-error-50 dark:border-error-500/30 dark:hover:bg-error-500/10">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                                <button type="button" @click="addAdviser()"
                                    class="mt-2 text-xs font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">
                                    Add Adviser
                                </button>
                            </div>
                        </div>

                        <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            Event Type</p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2 space-y-2 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/30">
                                <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Select one <span class="text-error-500">*</span></p>
                                @foreach (['Seminar', 'Clean Up Drive', 'Conference', 'Workshop', 'Preparation', 'Meeting'] as $type)
                                    <label class="flex cursor-pointer items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                                        <input type="radio" name="activity_type" value="{{ $type }}"
                                            x-model="activityType" required
                                            class="h-3.5 w-3.5 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                        <span>{{ $type }}</span>
                                    </label>
                                @endforeach
                                <label class="flex cursor-pointer items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                                    <input type="radio" name="activity_type" value="others"
                                        x-model="activityType" required
                                        class="h-3.5 w-3.5 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                    <span>Others</span>
                                </label>
                                <div x-show="activityType === 'others'" x-transition class="pl-5">
                                    <input type="text" name="activity_type_other"
                                        value="{{ old('activity_type_other', $plan->activity_types_other ?? '') }}"
                                        :required="activityType === 'others'"
                                        class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90"
                                        placeholder="Specify other activity type" />
                                </div>
                                <div x-show="activityType === 'Seminar'" x-transition
                                    class="mt-2 rounded-lg border border-brand-200 bg-brand-50/50 p-3 dark:border-brand-700/50 dark:bg-brand-500/5">
                                    <p class="mb-2 text-xs font-medium text-gray-600 dark:text-gray-400">Seminar Level <span class="text-error-500">*</span></p>
                                    <div class="flex gap-4">
                                        <label class="flex cursor-pointer items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                                            <input type="radio" name="seminar_level" value="College"
                                                x-model="seminarLevel"
                                                :required="activityType === 'Seminar'"
                                                class="h-3.5 w-3.5 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                            College Level
                                        </label>
                                        <label class="flex cursor-pointer items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                                            <input type="radio" name="seminar_level" value="University"
                                                x-model="seminarLevel"
                                                :required="activityType === 'Seminar'"
                                                class="h-3.5 w-3.5 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                            University Level
                                        </label>
                                    </div>
                                </div>
                            </div>
                            {{-- Related to Organization --}}
                            <div class="sm:col-span-2">
                                <label class="flex items-center gap-2 text-xs font-medium text-gray-600 dark:text-gray-400">
                                    <input type="checkbox" name="related_to_organization" value="1"
                                        x-model="relatedToOrg"
                                        class="h-3.5 w-3.5 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                    Related to Organization?
                                    <span class="relative group cursor-default ml-0.5">
                                        <svg class="h-3.5 w-3.5 text-gray-400 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z" />
                                        </svg>
                                        <span class="pointer-events-none absolute left-1/2 bottom-full mb-1.5 -translate-x-1/2 w-52 rounded-lg bg-gray-900 px-3 py-2 text-xs text-white opacity-0 group-hover:opacity-100 transition-opacity dark:bg-gray-700 z-20 text-center">
                                            Related to the organizations' scope of topic, course.
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Area
                                    Scope</label>
                                <select name="area_scope" x-model="areaScope"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90">
                                    <option value="">Select area scope</option>
                                    <option value="none" @selected(old('area_scope', $plan->area_scope ?? '') === 'none')>None</option>
                                    <option value="Local" @selected(old('area_scope', $plan->area_scope ?? '') === 'Local')>Local</option>
                                    <option value="Provincial" @selected(old('area_scope', $plan->area_scope ?? '') === 'Provincial')>Provincial</option>
                                    <option value="Regional" @selected(old('area_scope', $plan->area_scope ?? '') === 'Regional')>Regional</option>
                                    <option value="National" @selected(old('area_scope', $plan->area_scope ?? '') === 'National')>National</option>
                                    <option value="International" @selected(old('area_scope', $plan->area_scope ?? '') === 'International')>International</option>
                                    <option value="others" @selected(old('area_scope', $plan->area_scope ?? '') === 'others')>Others</option>
                                </select>
                                <div x-show="areaScope === 'others'" class="mt-2">
                                    <input type="text" name="area_scope_other"
                                        value="{{ old('area_scope_other', $plan->area_scope_other ?? '') }}"
                                        class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90"
                                        placeholder="Specify area scope" />
                                </div>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Sponsor</label>
                                <select name="sponsor" x-model="sponsor"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90">
                                    <option value="">Select sponsor</option>
                                    <option value="none" @selected(old('sponsor', $plan->sponsor ?? '') === 'none')>None</option>
                                    <option value="N/A" @selected(old('sponsor', $plan->sponsor ?? '') === 'N/A')>N/A</option>
                                    <option value="SSC" @selected(old('sponsor', $plan->sponsor ?? '') === 'SSC')>SSC</option>
                                    <option value="Admin" @selected(old('sponsor', $plan->sponsor ?? '') === 'Admin')>Admin</option>
                                    <option value="others" @selected(old('sponsor', $plan->sponsor ?? '') === 'others')>Others</option>
                                    <option value="co-sponsors" @selected(old('sponsor', $plan->sponsor ?? '') === 'co-sponsors')>Co-sponsors</option>
                                </select>
                                <div x-show="sponsor === 'others'" class="mt-2">
                                    <input type="text" name="sponsor_other"
                                        value="{{ old('sponsor_other', $plan->sponsor_other ?? '') }}"
                                        class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90"
                                        placeholder="Specify sponsor" />
                                </div>
                                <div x-show="sponsor === 'co-sponsors'" x-transition class="mt-2">
                                    <input type="hidden" name="cosponsor_count" :value="cosponsorCount" />
                                    <div class="max-h-40 overflow-y-auto rounded-lg border border-gray-200 bg-white p-2 dark:border-gray-600 dark:bg-gray-900 space-y-1.5">
                                        @foreach ($allOrgsGrouped as $typeId => $orgs)
                                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 pt-1">{{ $orgTypeLabels[$typeId] ?? 'Other' }}</p>
                                            @foreach ($orgs as $org)
                                                <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300 cursor-pointer">
                                                    <input type="checkbox" value="{{ $org->organization_id }}"
                                                        @change="cosponsorCount = $el.closest('.max-h-40').querySelectorAll('input[type=checkbox]:checked').length"
                                                        class="h-3 w-3 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                                    {{ $org->name }}
                                                </label>
                                            @endforeach
                                        @endforeach
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-text="'Selected: ' + cosponsorCount + ' org' + (cosponsorCount !== 1 ? 's' : '')"></p>
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Extension
                                    Services</label>
                                <div class="flex gap-4">
                                    <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                                        <input type="radio" name="extension_services" value="yes"
                                            x-model="extensionServices" @checked(old('extension_services', $plan->extension_services ? 'yes' : 'no') === 'yes')
                                            class="h-3.5 w-3.5 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                        Yes
                                    </label>
                                    <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                                        <input type="radio" name="extension_services" value="no"
                                            x-model="extensionServices" @checked(old('extension_services', $plan->extension_services ? 'yes' : 'no') === 'no')
                                            class="h-3.5 w-3.5 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                        No
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 justify-end">
                            <button type="button" @click="showCreateForm = false"
                                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                Cancel
                            </button>
                            <button type="submit"
                                class="rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-600">
                                Submit for Approval
                            </button>
                        </div>
                    </form>
                </div>
            @endif

        </div>
    </div>
</div>
