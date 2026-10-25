@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Organization Members" />

    @php
        $selectedOrganization = $organizations->firstWhere('organization_id', $selectedOrganizationId);
        $roleFilters = [
            'all' => 'All members',
            'president' => 'Presidents',
            'officer' => 'Officers',
            'member' => 'Members',
        ];
    @endphp

    <div class="space-y-6">
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#173f31] via-[#205640] to-[#2d6a4f] px-6 py-7 text-white shadow-sm sm:px-8">
            <div class="pointer-events-none absolute -right-12 -top-20 h-56 w-56 rounded-full bg-lime-300/10 blur-2xl"></div>
            <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-lime-200">Member directory</p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">Everyone in your organization</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-emerald-50/85">View current presidents, officers, and members using the organization records approved in SO Connect.</p>
                </div>
                @if ($selectedOrganization)
                    <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur-sm">
                        <p class="text-xs uppercase tracking-wide text-emerald-100">Viewing organization</p>
                        <p class="mt-1 font-semibold">{{ $selectedOrganization->organization_name }}</p>
                    </div>
                @endif
            </div>
        </section>

        @if ($organizations->isEmpty())
            <section class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center dark:border-gray-700 dark:bg-white/[0.03]">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-300">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </div>
                <h2 class="mt-4 font-semibold text-gray-900 dark:text-white">No organization assigned</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">You must be an organization officer or president to open a member directory.</p>
            </section>
        @else
            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach ([
                    ['key' => 'all', 'label' => 'Total members', 'color' => 'text-gray-900 dark:text-white'],
                    ['key' => 'president', 'label' => 'Presidents', 'color' => 'text-brand-600 dark:text-brand-400'],
                    ['key' => 'officer', 'label' => 'Officers', 'color' => 'text-blue-600 dark:text-blue-400'],
                    ['key' => 'member', 'label' => 'Members', 'color' => 'text-success-600 dark:text-success-400'],
                ] as $metric)
                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-5">
                        <p class="text-2xl font-bold {{ $metric['color'] }}">{{ $memberCounts[$metric['key']] }}</p>
                        <p class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                    </div>
                @endforeach
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-5">
                <form action="{{ route('manage-organization') }}" method="get" class="grid gap-3 lg:grid-cols-[minmax(14rem,1fr)_minmax(14rem,1.4fr)_12rem_auto] lg:items-end">
                    <div>
                        <label for="organization_id" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Organization</label>
                        <select id="organization_id" name="organization_id" onchange="this.form.submit()" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-3 text-sm text-gray-800 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            @foreach ($organizations as $organization)
                                <option value="{{ $organization->organization_id }}" @selected((int) $organization->organization_id === (int) $selectedOrganizationId)>
                                    {{ $organization->organization_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="q" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Search members</label>
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" stroke-linecap="round" /></svg>
                            <input id="q" name="q" value="{{ $queryText }}" type="search" placeholder="Name, email, student ID, or course"
                                class="h-11 w-full rounded-xl border border-gray-300 bg-white pl-10 pr-3 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                        </div>
                    </div>

                    <div>
                        <label for="role" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Role</label>
                        <select id="role" name="role" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-3 text-sm text-gray-800 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            @foreach ($roleFilters as $key => $label)
                                <option value="{{ $key }}" @selected($selectedRole === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-brand-600 px-5 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/30">Apply filters</button>
                </form>
            </section>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-col gap-2 border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <h2 class="font-semibold text-gray-900 dark:text-white">Member list</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Showing {{ $members->count() }} of {{ $memberCounts['all'] }} organization members</p>
                    </div>
                    @if ($queryText !== '' || $selectedRole !== 'all')
                        <a href="{{ route('manage-organization', ['organization_id' => $selectedOrganizationId]) }}" class="text-sm font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">Clear filters</a>
                    @endif
                </div>

                @if ($members->isEmpty())
                    <div class="px-6 py-14 text-center">
                        <h3 class="font-semibold text-gray-900 dark:text-white">No matching members</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try a different name, role, student ID, or course.</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-800 lg:hidden">
                        @foreach ($members as $member)
                            <article class="p-5">
                                <div class="flex items-start gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">{{ $member->initials ?: 'M' }}</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ $member->display_name }}</p>
                                        <p class="truncate text-sm text-gray-500 dark:text-gray-400">{{ $member->user_email ?? 'No email recorded' }}</p>
                                        <div class="mt-3 flex flex-wrap gap-2 text-xs text-gray-500 dark:text-gray-400">
                                            <span class="rounded-full bg-gray-100 px-2.5 py-1 font-semibold dark:bg-gray-800">{{ $member->membership_role }}</span>
                                            @if ($member->position)<span>{{ $member->position }}</span>@endif
                                            @if ($member->course_year)<span>{{ $member->course_year }}</span>@endif
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <div class="hidden overflow-x-auto lg:block">
                        <table class="min-w-full text-left">
                            <thead class="bg-gray-50/80 dark:bg-gray-900/50">
                                <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-6 py-3">Member</th>
                                    <th class="px-5 py-3">Student details</th>
                                    <th class="px-5 py-3">Role</th>
                                    <th class="px-5 py-3">Member since</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($members as $member)
                                    <tr class="transition hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">{{ $member->initials ?: 'M' }}</span>
                                                <div class="min-w-0">
                                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $member->display_name }}</p>
                                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $member->user_email ?? 'No email recorded' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                            <p>{{ $member->student_id ?: 'No student ID recorded' }}</p>
                                            <p class="mt-0.5 text-xs text-gray-400">{{ collect([$member->course, $member->course_year])->filter()->implode(' · ') ?: 'No course recorded' }}</p>
                                        </td>
                                        <td class="px-5 py-4">
                                            @php
                                                $roleClasses = match ($member->role_key) {
                                                    'president' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300',
                                                    'officer' => 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
                                                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                                                };
                                            @endphp
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $roleClasses }}">{{ $member->membership_role }}</span>
                                            @if ($member->position)<p class="mt-1 text-xs text-gray-400">{{ $member->position }}</p>@endif
                                        </td>
                                        <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                            {{ $member->member_since ? \Illuminate\Support\Carbon::parse($member->member_since)->format('M d, Y') : 'Not recorded' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif
    </div>
@endsection
