@props(['plan', 'personNames' => [], 'orgNames' => [], 'status'])

@php
    $orgName = $orgNames[(int) $plan->organization_id] ?? 'Unknown Organization';
    $creatorName = '';
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
@endphp

<div class="rounded-xl border border-gray-100 px-4 py-4 dark:border-gray-800" x-data="{ showCreateForm: false }">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center gap-2 mb-1">
                <p class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $plan->title }}</p>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusBadge }}">
                    {{ ucfirst($status) }}
                </span>
                @if ($hasEvent)
                    <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">
                        Event Created
                    </span>
                @endif
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ $orgName }} &middot; Target: {{ $plan->target_date?->format('M d, Y') ?? '—' }}
            </p>

            @if ($plan->resources_needed)
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $plan->resources_needed }}</p>
            @endif

            @if ($resolvedPersons->isNotEmpty())
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Responsible: {{ $resolvedPersons->implode(', ') }}
                </p>
            @endif
        </div>

        <div class="flex flex-wrap gap-2 shrink-0">
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
    </div>

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
