@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Manage Organization" />

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Organization Member Directory</h3>

            @if ($organizations->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    You are not currently assigned as an officer or president in any organization.
                </p>
            @else
                <form action="{{ route('manage-organization') }}" method="get" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="md:col-span-2">
                        <label for="organization_id"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Select Organization
                        </label>
                        <select id="organization_id" name="organization_id"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90"
                            onchange="this.form.submit()">
                            @foreach ($organizations as $organization)
                                <option value="{{ $organization->organization_id }}" @selected((int) $selectedOrganizationId === (int) $organization->organization_id)>
                                    {{ $organization->organization_name }} ({{ ucfirst($organization->role_name) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-1 flex items-end">
                        <button type="submit"
                            class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 w-full rounded-lg px-4 py-3 text-sm font-medium text-white transition">
                            Load Members
                        </button>
                    </div>
                </form>
            @endif
        </div>

        @if (!$organizations->isEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h4 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Member List</h4>

                @if ($members->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        No members found for the selected organization.
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        Name
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        Email
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        Role
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        Member Since
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($members as $member)
                                    @php
                                        $name = trim(
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
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {{ $name !== '' ? $name : 'Unknown Member' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {{ $member->user_email ?? 'N/A' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {{ $member->membership_role }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {{ $member->member_since ? \Illuminate\Support\Carbon::parse($member->member_since)->format('M d, Y') : 'N/A' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection
