@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Database View" />

    <div class="space-y-6">
        {{-- Organizations --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <div>
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Organizations</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Click an organization to jump to its officers.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.database-view.orgs.export.json') }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        JSON
                    </a>
                    <a href="{{ route('admin.database-view.orgs.export.print') }}" target="_blank"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]">
                        Print
                    </a>
                    <a href="{{ route('admin.database-view.orgs.export.xlsx') }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-green-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-green-600">
                        Excel
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Organization</th>
                            <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Officers</th>
                            <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($organizations as $org)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-100 text-xs font-semibold text-gray-500 dark:bg-white/[0.06] dark:text-gray-300">
                                            @if ($org['logo_url'])
                                                <img src="{{ $org['logo_url'] }}" alt="{{ $org['name'] }}" class="h-full w-full object-cover" />
                                            @else
                                                {{ $org['initials'] ?: Str::of($org['name'])->substr(0, 2)->upper() }}
                                            @endif
                                        </div>
                                        <span class="font-medium text-gray-800 dark:text-white/90">{{ $org['name'] }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $org['officer_count'] }}</td>
                                <td class="px-6 py-4 text-right">
                                    <a href="#org-{{ $org['organization_id'] }}"
                                        class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                                        View officers
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                                    No organizations found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Officers per organization --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <div>
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Officers</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Presidents and officers per organization.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.database-view.officers.export.json') }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        JSON
                    </a>
                    <a href="{{ route('admin.database-view.officers.export.print') }}" target="_blank"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]">
                        Print
                    </a>
                    <a href="{{ route('admin.database-view.officers.export.xlsx') }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-green-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-green-600">
                        Excel
                    </a>
                </div>
            </div>

            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($organizations as $org)
                    @php $officers = $officersByOrg[$org['organization_id']] ?? []; @endphp
                    <div id="org-{{ $org['organization_id'] }}" class="scroll-mt-24 px-6 py-5">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $org['name'] }}</h4>
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ count($officers) }} {{ Str::plural('officer', count($officers)) }}</span>
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
                                    @forelse ($officers as $officer)
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
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">No organizations found.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
