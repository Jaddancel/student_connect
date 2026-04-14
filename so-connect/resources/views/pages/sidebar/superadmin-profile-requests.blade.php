@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Profile Match Requests" />

    <div class="space-y-4">
        <div id="superadmin-profile-feedback" class="hidden rounded-lg px-4 py-3 text-sm"></div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Pending Profile Requests</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Review pending profile requests and attach either the suggested match or a manually searched
                        profile.
                    </p>
                </div>
                <span
                    class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    {{ $rows->count() }} total
                </span>
            </div>

            @if (session('status'))
                <div
                    class="mb-4 rounded-lg border border-success-300 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/40 dark:bg-success-500/10 dark:text-success-400">
                    {{ session('status') }}
                </div>
            @endif

            @if ($rows->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No pending profile match requests right now.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Request
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Submitted Name
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Suggested Match
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Status
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr class="border-b border-gray-100 align-top dark:border-gray-800"
                                    id="profile-request-row-{{ $row['request_id'] }}">
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <p class="font-medium">#{{ $row['request_id'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $row['requester_email'] }}
                                        </p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ \Illuminate\Support\Carbon::parse($row['requested_at'])->format('M d, Y h:i A') }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $row['submitted_name'] !== '' ? $row['submitted_name'] : 'Unknown Name' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        @if ($row['suggested_profile'])
                                            <p class="font-medium">{{ $row['suggested_profile']['name'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $row['suggested_profile']['occupation'] }} · Profile
                                                #{{ $row['suggested_profile']['profile_id'] }}
                                            </p>
                                        @else
                                            <span class="text-xs text-gray-500 dark:text-gray-400">No close match found.</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $row['status'] === 'approved' ? 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-400' : ($row['status'] === 'rejected' ? 'bg-error-100 text-error-700 dark:bg-error-500/15 dark:text-error-400' : 'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400') }}">
                                            {{ $row['status_label'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        @if ($row['can_decide'])
                                            <div x-data="profileRequestDecision({{ $row['request_id'] }}, {{ $row['suggested_profile_id'] > 0 ? $row['suggested_profile_id'] : 'null' }})"
                                                class="space-y-2">
                                                <button type="button"
                                                    class="inline-flex items-center rounded-lg border border-brand-300 px-3 py-2 text-xs font-medium text-brand-700 transition hover:bg-brand-50 dark:border-brand-500/30 dark:text-brand-400"
                                                    @click="openModal()">
                                                    Review Request
                                                </button>

                                                <div x-show="showModal" x-cloak
                                                    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 px-4 py-6"
                                                    @click.self="closeModal()" @keydown.escape.window="closeModal()">
                                                    <div class="w-full max-w-2xl rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-900"
                                                        x-transition>
                                                        <div class="mb-4 flex items-start justify-between gap-3">
                                                            <div>
                                                                <h4 class="text-base font-semibold text-gray-800 dark:text-white/90">Review Profile Request #{{ $row['request_id'] }}</h4>
                                                                <p class="text-xs text-gray-500 dark:text-gray-400">Search profiles and choose the best match before approving.</p>
                                                            </div>
                                                            <button type="button"
                                                                class="rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-600 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                                                                @click="closeModal()">
                                                                Close
                                                            </button>
                                                        </div>

                                                        @if ($row['suggested_profile'])
                                                            <button type="button"
                                                                class="mb-3 inline-flex items-center rounded-lg border border-brand-300 px-2.5 py-1.5 text-xs font-medium text-brand-700 transition hover:bg-brand-50 dark:border-brand-500/30 dark:text-brand-400"
                                                                @click="setSelectedProfile({{ $row['suggested_profile']['profile_id'] }}, '{{ addslashes($row['suggested_profile']['name']) }}')">
                                                                Use Suggested Match: {{ $row['suggested_profile']['name'] }}
                                                            </button>
                                                        @endif

                                                        <div class="mb-3 flex flex-col gap-2 sm:flex-row">
                                                            <input type="text" x-model="query"
                                                                @keydown.enter.prevent="searchProfiles()"
                                                                placeholder="Search profiles by name"
                                                                class="dark:bg-dark-900 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90" />
                                                            <button type="button"
                                                                class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-brand-700 disabled:opacity-60"
                                                                :disabled="searching" @click="searchProfiles()"
                                                                x-text="searching ? 'Searching...' : 'Search'"></button>
                                                        </div>

                                                        <p x-show="searchError" x-text="searchError"
                                                            class="mb-2 text-xs text-error-600 dark:text-error-400"></p>

                                                        <div class="max-h-48 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-2 dark:border-gray-700">
                                                            <template x-if="results.length === 0">
                                                                <p class="px-2 py-1 text-xs text-gray-500 dark:text-gray-400">No profiles found.</p>
                                                            </template>

                                                            <template x-for="result in results" :key="result.profile_id">
                                                                <button type="button"
                                                                    class="flex w-full items-center justify-between rounded-md px-2 py-2 text-left text-xs text-gray-800 hover:bg-gray-100 dark:text-gray-100 dark:hover:bg-gray-800"
                                                                    @click="setSelectedProfile(result.profile_id, result.name)">
                                                                    <span x-text="result.name"></span>
                                                                    <span class="text-gray-500 dark:text-gray-400" x-text="' #' + result.profile_id"></span>
                                                                </button>
                                                            </template>
                                                        </div>

                                                        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                                            Selected:
                                                            <span class="font-medium text-gray-700 dark:text-gray-300"
                                                                x-text="selectedLabel"></span>
                                                        </p>

                                                        <div class="mt-4 flex items-center gap-2">
                                                            <button type="button"
                                                                class="inline-flex items-center rounded-lg bg-success-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-success-700"
                                                                @click="submitDecision('approve')">
                                                                Approve
                                                            </button>
                                                            <button type="button"
                                                                class="inline-flex items-center rounded-lg bg-error-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-error-700"
                                                                @click="submitDecision('reject')">
                                                                Reject
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-500 dark:text-gray-400">No action required</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const feedback = document.getElementById('superadmin-profile-feedback');
        const seedProfiles = @json($seedProfiles);

        const setFeedback = (message, type = 'success') => {
            if (!feedback) {
                return;
            }

            feedback.classList.remove('hidden', 'border', 'border-success-300', 'border-error-300',
                'bg-success-50', 'bg-error-50', 'text-success-700', 'text-error-700');

            if (type === 'error') {
                feedback.classList.add('border', 'border-error-300', 'bg-error-50', 'text-error-700');
            } else {
                feedback.classList.add('border', 'border-success-300', 'bg-success-50', 'text-success-700');
            }

            feedback.textContent = message;
        };

        window.profileRequestDecision = (requestId, initialProfileId = null) => ({
            requestId,
            query: '',
            searching: false,
            showModal: false,
            searchError: '',
            results: [...seedProfiles],
            selectedProfileId: initialProfileId,
            selectedLabel: initialProfileId ? `Profile #${initialProfileId}` : 'None',

            openModal() {
                this.showModal = true;
                this.searchError = '';
                this.results = [...seedProfiles];
            },

            closeModal() {
                this.showModal = false;
                this.searchError = '';
            },

            setSelectedProfile(profileId, label) {
                this.selectedProfileId = profileId;
                this.selectedLabel = label || `Profile #${profileId}`;
            },

            async searchProfiles() {
                this.searching = true;
                this.searchError = '';

                const searchParams = new URLSearchParams({
                    q: this.query,
                    limit: '15',
                });

                try {
                    const response = await fetch(`/superadmin/profiles/search?${searchParams.toString()}`, {
                        headers: {
                            Accept: 'application/json',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Failed to search profiles.');
                    }

                    const payload = await response.json();
                    this.results = Array.isArray(payload?.data) ? payload.data : [];
                } catch (error) {
                    this.results = [];
                    this.searchError = error instanceof Error ? error.message : 'Failed to search profiles.';
                } finally {
                    this.searching = false;
                }
            },

            async submitDecision(decision) {
                const row = document.getElementById(`profile-request-row-${this.requestId}`);
                row?.classList.add('opacity-60');

                try {
                    const response = await fetch(`/superadmin/profile-requests/${this.requestId}/decision`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            decision,
                            profile_id: this.selectedProfileId,
                        }),
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(payload?.message || 'Unable to submit decision.');
                    }

                    setFeedback(payload?.message || 'Decision submitted successfully.');
                    this.closeModal();
                    window.location.reload();
                } catch (error) {
                    setFeedback(error instanceof Error ? error.message : 'Unable to submit decision.', 'error');
                    row?.classList.remove('opacity-60');
                }
            },
        });
    </script>
@endpush