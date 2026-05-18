@props(['plan', 'personNames' => [], 'orgNames' => [], 'status'])

@php
    $orgName = $orgNames[(int) $plan->organization_id] ?? 'Unknown Organization';
    $resolvedPersons = collect($plan->persons_responsible ?? [])
        ->map(fn ($id) => $personNames[$id] ?? 'User #' . $id)
        ->filter()
        ->values();
    $hasEvent = $plan->event_id !== null;

    $statusBadge = match ($status) {
        'pending'  => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
        'approved' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
        'rejected' => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
        default    => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    };

    $accentLine = match ($status) {
        'pending'  => 'bg-warning-400',
        'approved' => 'bg-success-400',
        'rejected' => 'bg-error-400',
        default    => 'bg-gray-300 dark:bg-gray-600',
    };
@endphp

<div
    x-data="{
        open: false,
        showCreateForm: false,
        showReviseForm: false,
        reviseOfficers: [],
        reviseSelected: @json(array_values(array_map('intval', $plan->persons_responsible ?? []))),
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
    class="group relative overflow-hidden rounded-xl border border-gray-100 bg-white transition-shadow duration-200 hover:shadow-sm dark:border-gray-800 dark:bg-white/[0.02]"
>
    {{-- Status accent line --}}
    <div class="absolute left-0 top-0 h-full w-0.5 {{ $accentLine }}"></div>

    {{-- Collapsed header — always visible, clickable --}}
    <button
        type="button"
        @click="open = !open"
        class="flex w-full items-center gap-3 px-5 py-3.5 text-left"
        :aria-expanded="open"
    >
        {{-- Chevron --}}
        <span class="flex h-5 w-5 shrink-0 items-center justify-center text-gray-400 transition-transform duration-200 dark:text-gray-500"
              :class="open ? 'rotate-90' : ''">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6"/>
            </svg>
        </span>

        {{-- Title + badges --}}
        <span class="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1">
            <span class="truncate text-sm font-semibold text-gray-800 dark:text-white/90">{{ $plan->title }}</span>
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusBadge }}">
                {{ ucfirst($status) }}
            </span>
            @if ($hasEvent)
                <span class="inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">
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
    <div
        x-show="open"
        x-collapse
        x-cloak
    >
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
                        <dd class="mt-0.5 text-sm text-gray-700 dark:text-gray-300">{{ $resolvedPersons->implode(', ') }}</dd>
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
                @if ($status === 'approved' && ! $hasEvent)
                    <button type="button" @click="showCreateForm = !showCreateForm"
                        class="inline-flex items-center rounded-lg border border-brand-300 bg-brand-50 px-3 py-1.5 text-xs font-medium text-brand-700 hover:bg-brand-100 dark:border-brand-600 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/20">
                        Create Event
                    </button>
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
                <div x-show="showReviseForm" x-cloak class="mt-4 rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-700 dark:bg-warning-500/10">
                    <h4 class="mb-3 text-sm font-semibold text-warning-700 dark:text-warning-400">Edit &amp; Resubmit Plan</h4>
                    <form method="POST" action="{{ route('event-plans.revise', $plan->event_plan_id) }}" class="space-y-3">
                        @csrf
                        @method('PATCH')
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Title</label>
                                <input type="text" name="title" value="{{ $plan->title }}" required
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Target Date</label>
                                <input type="date" name="target_date" value="{{ $plan->target_date?->format('Y-m-d') }}" required
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Resources Needed <span class="text-error-500">*</span></label>
                                <textarea name="resources_needed" rows="2"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90">{{ $plan->resources_needed }}</textarea>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                    Persons Responsible <span class="text-error-500">*</span>
                                </label>
                                <div class="min-h-[60px] max-h-36 overflow-y-auto rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-900">
                                    <template x-if="reviseLoading">
                                        <p class="text-xs text-gray-400 italic">Loading officers…</p>
                                    </template>
                                    <template x-if="!reviseLoading && reviseOfficers.length === 0">
                                        <p class="text-xs text-gray-400 italic">No officers found for this organization.</p>
                                    </template>
                                    <template x-for="officer in reviseOfficers" :key="officer.user_id">
                                        <label class="flex cursor-pointer items-center gap-2 py-1">
                                            <input type="checkbox" :value="officer.user_id" x-model="reviseSelected"
                                                class="h-3.5 w-3.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                            <span class="text-xs text-gray-700 dark:text-gray-300" x-text="officer.name"></span>
                                        </label>
                                    </template>
                                </div>
                                <template x-for="id in reviseSelected" :key="id">
                                    <input type="hidden" name="persons_responsible[]" :value="id" />
                                </template>
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
            @if ($status === 'approved' && ! $hasEvent)
                <div x-show="showCreateForm" x-cloak class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                    <h4 class="mb-3 text-sm font-semibold text-gray-700 dark:text-white/80">Create Event from Plan</h4>
                    <form method="POST" action="{{ route('event-plans.create-event', $plan->event_plan_id) }}" class="space-y-3">
                        @csrf
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Event Name</label>
                                <input type="text" name="name" value="{{ $plan->title }}" required
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Location</label>
                                <input type="text" name="location" required
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Start Date &amp; Time</label>
                                <input type="datetime-local" name="start_time" required
                                    value="{{ $plan->target_date?->format('Y-m-d') }}T09:00"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">End Date &amp; Time</label>
                                <input type="datetime-local" name="end_time" required
                                    value="{{ $plan->target_date?->format('Y-m-d') }}T17:00"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Description <span class="font-normal text-gray-400">(optional)</span></label>
                                <textarea name="desc_text" rows="2"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90">{{ $plan->resources_needed }}</textarea>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 justify-end">
                            <button type="button" @click="showCreateForm = false"
                                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                Cancel
                            </button>
                            <button type="submit"
                                class="rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-600">
                                Create Event
                            </button>
                        </div>
                    </form>
                </div>
            @endif

        </div>
    </div>
</div>
