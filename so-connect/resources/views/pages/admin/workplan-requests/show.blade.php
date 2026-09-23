@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Review Workplan" />

    <div class="space-y-6 max-w-4xl">
        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Meta --}}
        <div class="rounded-2xl border border-gray-200 bg-white px-6 py-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Organization</p>
                    <p class="mt-1 text-gray-800 dark:text-white/90">{{ $orgName }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Submitted By</p>
                    <p class="mt-1 text-gray-800 dark:text-white/90">{{ $requesterName }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Submitted At</p>
                    <p class="mt-1 text-gray-800 dark:text-white/90">{{ \Illuminate\Support\Carbon::parse($actionRequest->requested_at)->format('M d, Y h:i A') }}</p>
                </div>
            </div>
        </div>

        @if ($submission)
            @php $p = $submissionPayload; @endphp

            {{-- Section I: Workplan Details --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">I. Workplan Details</h3>
                </div>
                <div class="grid grid-cols-1 gap-x-6 gap-y-4 px-6 py-5 text-sm sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-medium text-gray-400">Organization</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['organization'] ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">School Year / Semester</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['schoolyear'] ?? '—' }}</p>
                    </div>
                </div>
            </div>

            {{-- Section II: Planned Activities --}}
            @php
                $activities = (array) ($p['activities'] ?? []);
                $targets = (array) ($p['target'] ?? []);
                $resources = (array) ($p['resources'] ?? []);
                $people = (array) ($p['people'] ?? []);
            @endphp
            @if (!empty($activities))
                <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">II. Planned Activities</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Activity</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Target Date</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Resources Needed</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Persons Responsible</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($activities as $i => $activity)
                                    <tr>
                                        <td class="px-6 py-3 text-gray-800 dark:text-white/90">{{ $activity }}</td>
                                        <td class="px-6 py-3 text-gray-600 dark:text-gray-400">{{ $targets[$i] ?? '—' }}</td>
                                        <td class="px-6 py-3 text-gray-600 dark:text-gray-400">{{ $resources[$i] ?? '—' }}</td>
                                        <td class="px-6 py-3 text-gray-600 dark:text-gray-400">{{ $people[$i] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Section III: Attached Event Plans --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">III. Attached Event Plans</h3>
                </div>
                @if ($eventPlans->isEmpty())
                    <p class="px-6 py-5 text-sm text-gray-400 dark:text-gray-500">No event plans found for this workplan.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Activity / Title</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Target Date</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Status</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Resources Needed</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Persons Responsible</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($eventPlans as $plan)
                                    @php
                                        $names = collect($plan->persons_responsible ?? [])
                                            ->map(fn($id) => $personNames[$id] ?? null)
                                            ->filter()
                                            ->implode(', ');
                                    @endphp
                                    <tr>
                                        <td class="px-6 py-3 font-medium text-gray-800 dark:text-white/90">{{ $plan->title }}</td>
                                        <td class="px-6 py-3 text-gray-600 dark:text-gray-400">{{ \Illuminate\Support\Carbon::parse($plan->target_date)->format('M d, Y') }}</td>
                                        <td class="px-6 py-3">
                                            @if ($plan->status === 'approved')
                                                <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">Approved</span>
                                            @elseif ($plan->status === 'pending')
                                                <span class="inline-flex items-center rounded-full bg-warning-50 px-2 py-0.5 text-xs font-medium text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">Pending</span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">{{ ucfirst($plan->status) }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-3 text-gray-600 dark:text-gray-400">{{ $plan->resources_needed ?: '—' }}</td>
                                        <td class="px-6 py-3 text-gray-600 dark:text-gray-400">{{ $names ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Section IV: Signatories --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">IV. Signatories</h3>
                </div>
                <div class="grid grid-cols-1 gap-x-6 gap-y-6 px-6 py-5 text-sm sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-medium text-gray-400">Prepared By</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['name'] ?? '—' }}</p>
                        @if (!empty($p['signature']))
                            <x-admin.zoomable-image :src="asset('storage/'.$p['signature'])" alt="Signature" class="mt-2 h-16 object-contain rounded border border-gray-200 dark:border-gray-700" />
                        @endif
                    </div>
                    @if (!empty($p['advisername']))
                        <div>
                            <p class="text-xs font-medium text-gray-400">Noted By (Adviser)</p>
                            <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['advisername'] }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                Submission data not found.
            </div>
        @endif

        <x-admin.manual-source :payload="$submissionPayload" />

        {{-- Decision Panel --}}
        @if ($approval)
            <div class="rounded-2xl border border-gray-200 bg-white px-6 py-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm font-semibold text-gray-700 dark:text-white/90">Decision</p>
                <div class="mt-3 flex items-center gap-3">
                    @if ($approval->is_rejected)
                        <span class="inline-flex items-center rounded-full bg-error-50 px-3 py-1 text-sm font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400">Rejected</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-success-50 px-3 py-1 text-sm font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">Approved</span>
                    @endif
                    @if ($approval->rejection_reason)
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $approval->rejection_reason }}</span>
                    @endif
                </div>
            </div>
        @else
            <div x-data="{ rejectOpen: false }" class="rounded-2xl border border-gray-200 bg-white px-6 py-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="mb-4 text-sm font-semibold text-gray-700 dark:text-white/90">Decision</p>
                <div class="flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('admin.workplan-requests.decide', $actionRequest->request_id) }}">
                        @csrf
                        <input type="hidden" name="decision" value="approve" />
                        <button type="submit" class="rounded-lg bg-success-500 px-4 py-2 text-sm font-medium text-white hover:bg-success-600">
                            Approve
                        </button>
                    </form>

                    <div>
                        <button type="button" @click="rejectOpen = !rejectOpen"
                            class="rounded-lg border border-error-300 px-4 py-2 text-sm font-medium text-error-600 hover:bg-error-50 dark:border-error-600 dark:text-error-400 dark:hover:bg-error-900/20">
                            Reject
                        </button>
                        <div x-show="rejectOpen" x-cloak class="mt-3 w-full max-w-md">
                            <form method="POST" action="{{ route('admin.workplan-requests.decide', $actionRequest->request_id) }}" class="space-y-3">
                                @csrf
                                <input type="hidden" name="decision" value="reject" />
                                <textarea name="rejection_reason" rows="3" placeholder="Reason for rejection (optional)"
                                    class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-2 focus:ring-brand-500/10 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                                <button type="submit" class="rounded-lg bg-error-500 px-4 py-2 text-sm font-medium text-white hover:bg-error-600">
                                    Confirm Reject
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="pb-2">
            <a href="{{ route('admin.workplan-requests.index') }}" class="text-sm text-brand-500 hover:underline">
                ← Back to list
            </a>
        </div>
    </div>
@endsection
