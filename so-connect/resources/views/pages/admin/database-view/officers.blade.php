@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Officers" />

    @php $exportQuery = array_filter(['organization_id' => $selectedOrgId]); @endphp

    <div class="space-y-6">
        {{-- Officers per organization --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <div>
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Officers</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Presidents and officers per organization.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.database-view.index') }}"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]">
                        Back to organizations
                    </a>
                    <a href="{{ route('admin.database-view.officers.export.json', $exportQuery) }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        JSON
                    </a>
                    <a href="{{ route('admin.database-view.officers.export.print', $exportQuery) }}" target="_blank"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]">
                        Print
                    </a>
                    <a href="{{ route('admin.database-view.officers.export.xlsx', $exportQuery) }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-green-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-green-600">
                        Excel
                    </a>
                </div>
            </div>

            {{-- Filter --}}
            <form method="GET" action="{{ route('admin.database-view.officers') }}"
                class="flex flex-wrap items-end gap-3 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <div class="min-w-[220px] flex-1">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Organization</label>
                    <select name="organization_id"
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90">
                        <option value="">All organizations</option>
                        @foreach ($allOrganizations as $org)
                            <option value="{{ $org['organization_id'] }}" @selected($selectedOrgId == $org['organization_id'])>
                                {{ $org['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit"
                        class="h-11 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white transition hover:bg-brand-600">
                        Apply
                    </button>
                    <a href="{{ route('admin.database-view.officers') }}"
                        class="flex h-11 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.06]">
                        Show all
                    </a>
                </div>
            </form>

            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($organizations as $org)
                    @php
                        $officers = $officersByOrg[$org['organization_id']] ?? null;
                        $officerTotal = $officers?->total() ?? 0;
                    @endphp
                    <div id="org-{{ $org['organization_id'] }}" class="scroll-mt-24 px-6 py-5">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $org['name'] }}</h4>
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $officerTotal }} {{ Str::plural('officer', $officerTotal) }}</span>
                        </div>
                        <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-gray-800">
                            <table class="w-full min-w-[640px] text-left text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                                        <th class="px-4 py-2.5 text-xs font-semibold text-gray-500 dark:text-gray-400">Name</th>
                                        <th class="px-4 py-2.5 text-xs font-semibold text-gray-500 dark:text-gray-400">Email</th>
                                        <th class="px-4 py-2.5 text-xs font-semibold text-gray-500 dark:text-gray-400">Role</th>
                                        <th class="px-4 py-2.5 text-xs font-semibold text-gray-500 dark:text-gray-400">Position</th>
                                        <th class="px-4 py-2.5 text-xs font-semibold text-gray-500 dark:text-gray-400">Member Since</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse ($officers ?? [] as $officer)
                                        <tr>
                                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-white/90">{{ $officer['name'] }}</td>
                                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $officer['email'] }}</td>
                                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $officer['role'] }}</td>
                                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $officer['position'] }}</td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $officer['member_since'] }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                                                No officers for this organization.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($officers && $officers->hasPages())
                            <div class="mt-3">
                                {{ $officers->links() }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">No organizations found.</div>
                @endforelse
            </div>

            @if ($organizations->hasPages())
                <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">
                    {{ $organizations->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
