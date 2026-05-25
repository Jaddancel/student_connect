@props(['workplans' => [], 'activeSemester' => null, 'orgNames' => [], 'personNames' => []])

<div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
    <h3 class="mb-1 text-base font-semibold text-gray-800 dark:text-white/90">Workplans</h3>

    @if (! $activeSemester)
        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
            No upcoming semester has been configured. Contact an admin to set up semesters.
        </p>
    @elseif (empty($workplans))
        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No organizations found.</p>
    @else
        <div class="mt-4 space-y-6">
            @foreach ($workplans as $orgId => $data)
                @php
                    $wp = $data['workplan'];
                    $semester = $data['semester'];
                    $wplans = $data['plans'];
                    $canFinalize = $data['can_finalize'];
                @endphp

                <div class="rounded-xl border border-gray-100 dark:border-gray-800">
                    {{-- Header --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                        <div>
                            <p class="text-sm font-semibold text-gray-800 dark:text-white/90">
                                {{ $orgNames[$orgId] ?? 'Unknown Organization' }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $semester->name }} &middot;
                                Preparation: {{ $semester->activePeriodStart()->format('M d') }} – {{ $semester->activePeriodEnd()->format('M d, Y') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($wp->status === 'active')
                                <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">Active</span>
                            @elseif ($wp->status === 'finalized')
                                <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">Finalized</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">Archived</span>
                            @endif
                        </div>
                    </div>

                    {{-- Plans table --}}
                    <div class="px-4 py-3">
                        @if ($wplans->isEmpty())
                            <p class="py-2 text-sm text-gray-400 dark:text-gray-500">No approved plans for this semester yet.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-100 dark:border-gray-800">
                                            <th class="py-2 pr-4 text-xs font-semibold text-gray-500 dark:text-gray-400">Activity</th>
                                            <th class="py-2 pr-4 text-xs font-semibold text-gray-500 dark:text-gray-400">Target Date</th>
                                            <th class="py-2 pr-4 text-xs font-semibold text-gray-500 dark:text-gray-400">Resources</th>
                                            <th class="py-2 text-xs font-semibold text-gray-500 dark:text-gray-400">Persons Responsible</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50 dark:divide-gray-800/60">
                                        @foreach ($wplans as $plan)
                                            <tr>
                                                <td class="py-2 pr-4 font-medium text-gray-800 dark:text-white/80">{{ $plan->title }}</td>
                                                <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Carbon::parse($plan->target_date)->format('M d, Y') }}</td>
                                                <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">{{ $plan->resources_needed ?: '—' }}</td>
                                                <td class="py-2 text-gray-500 dark:text-gray-400">
                                                    @php
                                                        $names = collect($plan->persons_responsible ?? [])
                                                            ->map(fn($id) => $personNames[$id] ?? null)
                                                            ->filter()
                                                            ->values();
                                                    @endphp
                                                    {{ $names->isNotEmpty() ? $names->implode(', ') : '—' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    {{-- Footer actions --}}
                    @if ($wp->status === 'active' && $canFinalize)
                        <div class="border-t border-gray-100 px-4 py-3 dark:border-gray-800" x-data="{ confirming: false }">
                            <template x-if="!confirming">
                                <button type="button" @click="confirming = true"
                                    class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
                                    Finalize Workplan
                                </button>
                            </template>
                            <template x-if="confirming">
                                <div class="flex items-center gap-3">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Confirm finalization? This cannot be undone.</p>
                                    <form method="POST" action="{{ route('workplans.finalize', $wp->workplan_id) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                            class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
                                            Yes, Finalize
                                        </button>
                                    </form>
                                    <button type="button" @click="confirming = false"
                                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-800">
                                        Cancel
                                    </button>
                                </div>
                            </template>
                        </div>
                    @elseif ($wp->status === 'finalized')
                        <div class="border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                            <a href="{{ route('workplan.review', $wp->workplan_id) }}"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-success-500 px-4 py-2 text-sm font-medium text-white hover:bg-success-600">
                                Submit Workplan Request
                            </a>
                        </div>
                    @elseif ($wp->status === 'archived')
                        <div class="border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                            <p class="text-xs text-gray-400 dark:text-gray-500">This semester has started — workplan is archived (read-only).</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
