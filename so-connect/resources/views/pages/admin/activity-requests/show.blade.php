@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Review Activity Request" />

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

            {{-- Section I: Activity Details --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">I. Activity Details</h3>
                </div>
                <div class="grid grid-cols-1 gap-x-6 gap-y-4 px-6 py-5 text-sm sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <p class="text-xs font-medium text-gray-400">Project / Activity</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['projectActivity'] ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Date</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">
                            {{ isset($p['date']) ? \Illuminate\Support\Carbon::parse($p['date'])->format('F d, Y') : '—' }}
                            @if (!empty($p['dayOfTheWeek']))
                                <span class="text-gray-500">({{ $p['dayOfTheWeek'] }})</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Time</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['time'] ?? '—' }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-xs font-medium text-gray-400">Place / Venue</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['placeAndVenue'] ?? '—' }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-xs font-medium text-gray-400">Purpose / Objective</p>
                        <p class="mt-1 whitespace-pre-wrap text-gray-800 dark:text-white/90">{{ $p['purposed'] ?? '—' }}</p>
                    </div>
                </div>
            </div>

            {{-- Section II: Activity Type & Scope --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">II. Activity Type & Scope</h3>
                </div>
                <div class="grid grid-cols-1 gap-x-6 gap-y-4 px-6 py-5 text-sm sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-medium text-gray-400">Activity Type(s)</p>
                        @php
                            $types = (array) ($p['activityTypes'] ?? []);
                            $typeOther = $p['activityTypeOther'] ?? '';
                        @endphp
                        @if (!empty($types))
                            <ul class="mt-1 space-y-0.5">
                                @foreach ($types as $t)
                                    <li class="text-gray-800 dark:text-white/90">
                                        {{ $t === 'others' ? 'Others' . ($typeOther ? ': ' . $typeOther : '') : $t }}
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-1 text-gray-800 dark:text-white/90">—</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Area / Scope</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">
                            {{ $p['areaScope'] ?? '—' }}
                            @if (!empty($p['areaScopeOther']))
                                <span class="text-gray-500">({{ $p['areaScopeOther'] }})</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Sponsor</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">
                            {{ $p['sponsor'] ?? '—' }}
                            @if (!empty($p['sponsorOther']))
                                <span class="text-gray-500">({{ $p['sponsorOther'] }})</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Extension Services</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['extensionServices'] ?? '—' }}</p>
                    </div>
                </div>
            </div>

            {{-- Section III: Facilities --}}
            @php
                $facilities = (array) ($p['facilitiesOrEquipmentToBeUsedRow'] ?? []);
            @endphp
            @if (!empty($facilities))
                <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">III. Facilities / Equipment</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">#</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Facility / Equipment</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($facilities as $i => $facility)
                                    @if (!empty($facility))
                                        <tr>
                                            <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $i + 1 }}</td>
                                            <td class="px-6 py-3 text-gray-800 dark:text-white/90">{{ $facility }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Section IV: Signatories --}}
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">IV. Signatories</h3>
                </div>
                <div class="grid grid-cols-1 gap-x-6 gap-y-4 px-6 py-5 text-sm sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-medium text-gray-400">Organization President</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['presidentName'] ?? '—' }}</p>
                        @if (!empty($p['presidentContactNo']))
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $p['presidentContactNo'] }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">College Dean</p>
                        <p class="mt-1 text-gray-800 dark:text-white/90">{{ $p['collegeDean'] ?? '—' }}</p>
                    </div>
                    @php
                        $advisers = (array) ($p['adviserRow'] ?? []);
                    @endphp
                    @if (!empty($advisers))
                        <div class="sm:col-span-2">
                            <p class="text-xs font-medium text-gray-400">Faculty Adviser(s)</p>
                            <ul class="mt-1 space-y-0.5">
                                @foreach ($advisers as $adviser)
                                    @if (!empty($adviser))
                                        <li class="text-gray-800 dark:text-white/90">{{ $adviser }}</li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    @endif
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
                    <form method="POST" action="{{ route('admin.activity-requests.decide', $actionRequest->request_id) }}">
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
                            <form method="POST" action="{{ route('admin.activity-requests.decide', $actionRequest->request_id) }}" class="space-y-3">
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
            <a href="{{ route('admin.activity-requests.index') }}" class="text-sm text-brand-500 hover:underline">
                ← Back to list
            </a>
        </div>
    </div>
@endsection
