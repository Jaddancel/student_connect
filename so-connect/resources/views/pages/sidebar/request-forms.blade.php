@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Request Forms" />

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Role Change Request</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Submit promotion requests within organizations where you currently serve as president.
            </p>

            @if (session('success'))
                <div
                    class="mt-4 rounded-lg border border-success-300 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/40 dark:bg-success-500/10 dark:text-success-400">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('status'))
                <div
                    class="mt-4 rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/40 dark:bg-warning-500/10 dark:text-warning-400">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div
                    class="mt-4 rounded-lg border border-error-300 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/40 dark:bg-error-500/10 dark:text-error-400">
                    {{ $errors->first() }}
                </div>
            @endif

            @if ($organizations->isEmpty())
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    You are not assigned as president in any organization, so role-change requests are unavailable.
                </p>
            @else
                <form action="{{ route('request-forms') }}" method="get" class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="md:col-span-2">
                        <label for="organization_id"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Organization</label>
                        <select id="organization_id" name="organization_id"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                            @foreach ($organizations as $organization)
                                <option value="{{ $organization->organization_id }}" @selected((int) $selectedOrganizationId === (int) $organization->organization_id)>
                                    {{ $organization->organization_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                            Load Members
                        </button>
                    </div>
                </form>

                <form action="{{ route('request-forms.store') }}" method="post"
                    class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                    @csrf
                    <input type="hidden" name="organization_id" value="{{ $selectedOrganizationId }}" />

                    <div class="md:col-span-2">
                        <label for="target_user_id"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Member to Promote</label>
                        <select id="target_user_id" name="target_user_id"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                            <option value="">Select member</option>
                            @foreach ($members as $member)
                                @php
                                    $nextRole = $member->member_role === 'member' ? 'officer' : ($member->member_role === 'officer' ? 'president' : 'president');
                                    $fullName = trim(
                                        implode(
                                            ' ',
                                            array_filter([
                                                $member->first_name,
                                                $member->middle_name,
                                                $member->last_name,
                                            ]),
                                        ),
                                    );
                                @endphp

                                @if ($member->member_role !== 'president')
                                    <option value="{{ $member->user_id }}" @selected((int) old('target_user_id') === (int) $member->user_id)>
                                        {{ $fullName !== '' ? $fullName : $member->user_email }}
                                        ({{ ucfirst($member->member_role) }} -> {{ ucfirst($nextRole) }})
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-end">
                        <button type="submit"
                            class="w-full rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                            Submit Request
                        </button>
                    </div>
                </form>
            @endif
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h4 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Pending Role Change Requests</h4>

            @if ($pendingRequests->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No pending role-change requests for the selected organization.
                </p>
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
                                    Member
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Promotion
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Submitted
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pendingRequests as $pendingRequest)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        #{{ $pendingRequest['request_id'] }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $pendingRequest['member_name'] }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $pendingRequest['transition'] }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ \Illuminate\Support\Carbon::parse($pendingRequest['requested_at'])->format('M d, Y h:i A') }}
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