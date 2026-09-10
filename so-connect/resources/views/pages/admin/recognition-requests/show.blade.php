@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Review Recognition Application" />

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

            {{-- Section I: Application Type --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">I. Application Type</h3>
                </div>
                <div class="grid grid-cols-1 gap-x-6 gap-y-4 px-6 py-5 text-sm sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-medium text-gray-400">Type</p>
                        @if (!empty($p['c1']))
                            <span class="mt-1 inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">New / Recognition (C1)</span>
                        @elseif (!empty($p['c2']))
                            <span class="mt-1 inline-flex items-center rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-medium text-purple-700 dark:bg-purple-500/15 dark:text-purple-400">Renewal (C2)</span>
                        @else
                            <p class="mt-1 text-gray-800 dark:text-white/90">—</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Date</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['date'] ?? '—' }}</p>
                    </div>
                </div>
            </div>

            {{-- Section II: Organization Info --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">II. Organization Information</h3>
                </div>
                <div class="grid grid-cols-1 gap-x-6 gap-y-4 px-6 py-5 text-sm sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-medium text-gray-400">Organization Name</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['nameoforganization'] ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">President</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['presidentname'] ?? '—' }}</p>
                    </div>
                    @if (!empty($p['objectives']))
                        <div class="sm:col-span-2">
                            <p class="text-xs font-medium text-gray-400">Objectives</p>
                            <p class="mt-1 whitespace-pre-wrap text-gray-800 dark:text-white/90">{{ $p['objectives'] }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Section III: Membership --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">III. Membership Count</h3>
                </div>
                <div class="grid grid-cols-2 gap-x-6 gap-y-4 px-6 py-5 text-sm sm:grid-cols-4">
                    <div>
                        <p class="text-xs font-medium text-gray-400">Freshman</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['freshman'] ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Sophomore</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['sophomore'] ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Junior</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['junior'] ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Total</p>
                        <p class="mt-1 font-semibold text-gray-800 dark:text-white/90">{{ $p['total'] ?? '—' }}</p>
                    </div>
                </div>
            </div>

            {{-- Section IV: Workplan Activities --}}
            @if (!empty($p['workplanActivity']))
                <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">IV. Workplan Activities</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Activity</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Date</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Resources</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ((array) $p['workplanActivity'] as $i => $activity)
                                    <tr>
                                        <td class="px-6 py-3 text-gray-800 dark:text-white/90">{{ $activity }}</td>
                                        <td class="px-6 py-3 text-gray-600 dark:text-gray-400">{{ $p['workplanDate'][$i] ?? '—' }}</td>
                                        <td class="px-6 py-3 text-gray-600 dark:text-gray-400">{{ $p['workplanResources'][$i] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Section V: Advisers & Signatories --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">V. Advisers & Signatories</h3>
                </div>
                <div class="grid grid-cols-1 gap-x-6 gap-y-6 px-6 py-5 text-sm sm:grid-cols-2">
                    @if (!empty($p['nameOfAdviserRow']))
                        <div class="sm:col-span-2">
                            <p class="text-xs font-medium text-gray-400">Advisers</p>
                            @foreach ((array) $p['nameOfAdviserRow'] as $adviser)
                                <p class="mt-1 text-gray-800 dark:text-white/90">{{ $adviser }}</p>
                            @endforeach
                        </div>
                    @endif
                    <div>
                        <p class="text-xs font-medium text-gray-400">President</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['nameOfPresident'] ?? '—' }}</p>
                        @if (!empty($p['signaturePresident']))
                            <x-admin.zoomable-image :src="asset('storage/'.$p['signaturePresident'])" alt="President Signature"
                                 class="mt-2 h-16 object-contain rounded border border-gray-200 dark:border-gray-700" />
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                Submission data not found.
            </div>
        @endif

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
                    <form method="POST" action="{{ route('admin.recognition-requests.decide', $actionRequest->request_id) }}">
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
                            <form method="POST" action="{{ route('admin.recognition-requests.decide', $actionRequest->request_id) }}" class="space-y-3">
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
            <a href="{{ route('admin.recognition-requests.index') }}" class="text-sm text-brand-500 hover:underline">
                ← Back to list
            </a>
        </div>
    </div>
@endsection
