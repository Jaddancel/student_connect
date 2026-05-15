@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Request Promotion" />

    <div class="space-y-6">

        {{-- Header --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Request Promotion</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Submit a request to be promoted within one of your organizations.
                Officer requests go to your organization's President; President requests go to an Admin.
            </p>

            @if(session('success'))
                <div class="mt-4 rounded-lg border border-success-300 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/40 dark:bg-success-500/10 dark:text-success-400">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('status'))
                <div class="mt-4 rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/40 dark:bg-warning-500/10 dark:text-warning-400">
                    {{ session('status') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mt-4 rounded-lg border border-error-300 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/40 dark:bg-error-500/10 dark:text-error-400">
                    {{ $errors->first() }}
                </div>
            @endif
        </div>

        {{-- Request Form --}}
        @if($orgs->isEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    You are not a member of any organization, or you are already at the highest role in all your organizations.
                </p>
            </div>
        @else
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6"
                 x-data="{
                     selectedOrg: '',
                     orgs: {{ $orgs->keyBy('organization_id')->map(fn($o) => ['name' => $o->organization_name, 'current_role' => $o->current_role])->toJson() }},
                     get currentRole() { return this.orgs[this.selectedOrg]?.current_role ?? '' },
                     get targetRole() {
                         const r = this.currentRole;
                         if (r === 'member') return 'Officer';
                         if (r === 'officer') return 'President';
                         return '';
                     }
                 }">
                <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">New Request</h3>

                <form action="{{ route('member.promotion-request.store') }}" method="POST" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <label for="organization_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Organization <span class="text-error-500">*</span>
                            </label>
                            <select id="organization_id" name="organization_id" x-model="selectedOrg"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                <option value="">Select an organization</option>
                                @foreach($orgs as $org)
                                    <option value="{{ $org->organization_id }}">
                                        {{ $org->organization_name }} (currently: {{ ucfirst($org->current_role) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Requesting For</label>
                            <div class="flex h-11 items-center rounded-lg border border-gray-200 bg-gray-50 px-4 text-sm dark:border-gray-700 dark:bg-gray-900/30">
                                <span x-show="targetRole !== ''" class="font-medium text-brand-600 dark:text-brand-400" x-text="targetRole"></span>
                                <span x-show="targetRole === ''" class="text-gray-400 dark:text-gray-500">Select an org first</span>
                            </div>
                        </div>
                    </div>

                    <div x-show="selectedOrg !== ''" x-transition>
                        <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-400">
                            <span x-show="currentRole === 'member'">
                                Your request for <strong>Officer</strong> will be reviewed by the President of the selected organization.
                            </span>
                            <span x-show="currentRole === 'officer'">
                                Your request for <strong>President</strong> will be reviewed by an Administrator.
                            </span>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" x-bind:disabled="selectedOrg === ''"
                            class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50">
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>
        @endif

        {{-- Own Requests History --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">My Promotion Requests</h3>

            @if($ownRequests->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">You have not submitted any promotion requests yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Organization</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Promotion</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Submitted</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($ownRequests as $req)
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $req['organization_name'] }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        {{ ucfirst($req['current_role']) }} → {{ ucfirst($req['requested_role']) }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                                        {{ \Illuminate\Support\Carbon::parse($req['requested_at'])->format('M d, Y') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($req['status'] === 'approved')
                                            <span class="inline-flex items-center rounded-full bg-success-100 px-2.5 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">Approved</span>
                                        @elseif($req['status'] === 'rejected')
                                            <span class="inline-flex items-center rounded-full bg-error-100 px-2.5 py-0.5 text-xs font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400">Rejected</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-warning-100 px-2.5 py-0.5 text-xs font-medium text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">Pending</span>
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
