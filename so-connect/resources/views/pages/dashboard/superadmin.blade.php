@extends('layouts.app')

@php
    $totalPending   = $pendingProfileRequests + $pendingOfficerRequests;
    $wpTotal        = max($workplansActive + $workplansFinalized + $workplansArchived, 1);
    $wpActivePct    = round($workplansActive    / $wpTotal * 100);
    $wpFinalizedPct = round($workplansFinalized / $wpTotal * 100);
    $wpArchivedPct  = round($workplansArchived  / $wpTotal * 100);
@endphp

@section('content')

{{-- ── HEADER ─────────────────────────────────────────────────────────────── --}}
<div class="relative mb-6 overflow-hidden rounded-2xl border border-brand-100 bg-gradient-to-r from-brand-50 via-white to-white px-6 py-5 dark:border-brand-900/30 dark:from-brand-950/20 dark:via-gray-900/0 dark:to-transparent">
    {{-- dot grid texture --}}
    <div class="pointer-events-none absolute inset-0 select-none opacity-[0.035] dark:opacity-[0.05]"
         style="background-image: radial-gradient(circle at 1px 1px, currentColor 1px, transparent 0); background-size: 20px 20px;"></div>
    <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            {{-- pulsing live dot --}}
            <span class="relative flex h-2.5 w-2.5 shrink-0">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-success-400 opacity-60"></span>
                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-success-500"></span>
            </span>
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-white">System Dashboard</h1>
                <p class="mt-0.5 text-[11px] font-medium uppercase tracking-widest text-gray-400 dark:text-gray-500">
                    Live &middot; {{ now()->format('l, F j, Y') }}
                </p>
            </div>
        </div>
        <a href="{{ route('superadmin.profile-requests') }}"
           class="group inline-flex items-center gap-2 self-start rounded-xl border border-gray-200 bg-white/80 px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm backdrop-blur-sm transition-all hover:border-brand-300 hover:shadow-md hover:text-brand-700 dark:border-gray-700/80 dark:bg-gray-900/50 dark:text-gray-300 dark:hover:border-brand-600 dark:hover:text-brand-400 sm:self-auto">
            <svg class="h-4 w-4 text-gray-400 transition group-hover:text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            Profile Requests
            @if ($totalPending > 0)
                <span class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-error-500 px-1.5 text-[10px] font-bold leading-none text-white">
                    {{ $totalPending }}
                </span>
            @endif
        </a>
    </div>
</div>

{{-- ── MAIN GRID ───────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-12 gap-4 md:gap-6">
    <div class="col-span-12">
        <div class="rounded-2xl border border-brand-100 bg-gradient-to-r from-brand-50 to-white p-5 shadow-sm dark:border-brand-900/30 dark:from-brand-950/20 dark:to-gray-900">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-brand-600 dark:text-brand-400">Executive reporting</p>
                        <span class="inline-flex items-center gap-2 rounded-full bg-success-50 px-3 py-1 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">
                            <span class="h-2 w-2 animate-pulse rounded-full bg-success-500"></span>
                            Live
                        </span>
                    </div>
                    <h2 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Generate and export the admin dashboard report for leadership review and monitor live audit activity.</h2>
                </div>
                <a href="{{ route('dashboard-reports.index') }}" class="inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700">Open report</a>
            </div>
        </div>
    </div>

    <div class="col-span-12">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 pb-4 dark:border-gray-700">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Live Audit Activity</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Recent system actions refresh automatically.</p>
                </div>
                <span class="inline-flex items-center gap-2 rounded-full bg-success-50 px-3 py-1 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">
                    <span class="h-2 w-2 animate-pulse rounded-full bg-success-500"></span>
                    Live
                </span>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Actor</th>
                        <th class="px-4 py-3">Event</th>
                        <th class="px-4 py-3">Timestamp</th>
                    </tr>
                    </thead>
                    <tbody id="superadmin-dashboard-audit-rows">
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ══ KPI CARDS ══════════════════════════════════════════════════════════ --}}

    {{-- Total Users --}}
    <div class="col-span-12 sm:col-span-6 xl:col-span-3"
         x-data="{ count: 0 }"
         x-init="
             const target = {{ $totalUsers }};
             if (target === 0) return;
             const start  = performance.now();
             const tick   = (now) => {
                 const t = Math.min((now - start) / 900, 1);
                 count   = Math.round((1 - Math.pow(1 - t, 3)) * target);
                 if (t < 1) requestAnimationFrame(tick);
             };
             requestAnimationFrame(tick);
         ">
        <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 transition-shadow hover:shadow-md dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <div class="absolute inset-x-0 top-0 h-0.5 rounded-t-2xl bg-gradient-to-r from-brand-400 to-brand-600"></div>
            <div class="flex items-start justify-between">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 ring-1 ring-brand-100/80 dark:bg-brand-500/10 dark:ring-brand-500/20">
                    <svg class="h-5 w-5 text-brand-600 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <span class="rounded-full bg-brand-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">Users</span>
            </div>
            <div class="mt-4">
                <p class="font-mono text-3xl font-bold tabular-nums tracking-tight text-gray-900 dark:text-white"
                   x-text="count.toLocaleString()">0</p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Registered accounts &middot; excl. superadmin</p>
            </div>
        </div>
    </div>

    {{-- Organizations --}}
    <div class="col-span-12 sm:col-span-6 xl:col-span-3"
         x-data="{ count: 0 }"
         x-init="
             const target = {{ $totalOrganizations }};
             if (target === 0) return;
             const start  = performance.now();
             const tick   = (now) => {
                 const t = Math.min((now - start) / 900, 1);
                 count   = Math.round((1 - Math.pow(1 - t, 3)) * target);
                 if (t < 1) requestAnimationFrame(tick);
             };
             requestAnimationFrame(tick);
         ">
        <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 transition-shadow hover:shadow-md dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <div class="absolute inset-x-0 top-0 h-0.5 rounded-t-2xl bg-gradient-to-r from-success-400 to-success-600"></div>
            <div class="flex items-start justify-between">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-success-50 ring-1 ring-success-100/80 dark:bg-success-500/10 dark:ring-success-500/20">
                    <svg class="h-5 w-5 text-success-600 dark:text-success-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <span class="rounded-full bg-success-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-success-700 dark:bg-success-500/15 dark:text-success-400">Orgs</span>
            </div>
            <div class="mt-4">
                <p class="font-mono text-3xl font-bold tabular-nums tracking-tight text-gray-900 dark:text-white"
                   x-text="count.toLocaleString()">0</p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Student organizations &middot; platform total</p>
            </div>
        </div>
    </div>

    {{-- Pending Profiles --}}
    <div class="col-span-12 sm:col-span-6 xl:col-span-3"
         x-data="{ count: 0 }"
         x-init="
             const target = {{ $pendingProfileRequests }};
             if (target === 0) return;
             const start  = performance.now();
             const tick   = (now) => {
                 const t = Math.min((now - start) / 900, 1);
                 count   = Math.round((1 - Math.pow(1 - t, 3)) * target);
                 if (t < 1) requestAnimationFrame(tick);
             };
             requestAnimationFrame(tick);
         ">
        <a href="{{ route('superadmin.profile-requests') }}"
           class="group relative block overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 transition-shadow hover:shadow-md dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <div class="absolute inset-x-0 top-0 h-0.5 rounded-t-2xl bg-gradient-to-r from-warning-400 to-warning-600"></div>
            <div class="flex items-start justify-between">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-warning-50 ring-1 ring-warning-100/80 dark:bg-warning-500/10 dark:ring-warning-500/20">
                    <svg class="h-5 w-5 text-warning-600 dark:text-warning-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <span class="inline-flex items-center gap-1 rounded-full bg-warning-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-warning-700 transition group-hover:bg-warning-100 dark:bg-warning-500/15 dark:text-warning-400 dark:group-hover:bg-warning-500/25">
                    Review &rarr;
                </span>
            </div>
            <div class="mt-4">
                <p class="font-mono text-3xl font-bold tabular-nums tracking-tight text-gray-900 dark:text-white"
                   x-text="count.toLocaleString()">0</p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Pending profile match requests</p>
            </div>
        </a>
    </div>

    {{-- Pending Officers --}}
    <div class="col-span-12 sm:col-span-6 xl:col-span-3"
         x-data="{ count: 0 }"
         x-init="
             const target = {{ $pendingOfficerRequests }};
             if (target === 0) return;
             const start  = performance.now();
             const tick   = (now) => {
                 const t = Math.min((now - start) / 900, 1);
                 count   = Math.round((1 - Math.pow(1 - t, 3)) * target);
                 if (t < 1) requestAnimationFrame(tick);
             };
             requestAnimationFrame(tick);
         ">
        <a href="{{ route('superadmin.profile-requests') }}"
           class="group relative block overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 transition-shadow hover:shadow-md dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <div class="absolute inset-x-0 top-0 h-0.5 rounded-t-2xl bg-gradient-to-r from-error-400 to-error-600"></div>
            <div class="flex items-start justify-between">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-error-50 ring-1 ring-error-100/80 dark:bg-error-500/10 dark:ring-error-500/20">
                    <svg class="h-5 w-5 text-error-600 dark:text-error-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                </div>
                <span class="inline-flex items-center gap-1 rounded-full bg-error-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-error-700 transition group-hover:bg-error-100 dark:bg-error-500/15 dark:text-error-400 dark:group-hover:bg-error-500/25">
                    Review &rarr;
                </span>
            </div>
            <div class="mt-4">
                <p class="font-mono text-3xl font-bold tabular-nums tracking-tight text-gray-900 dark:text-white"
                   x-text="count.toLocaleString()">0</p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Pending officer account requests</p>
            </div>
        </a>
    </div>

    {{-- ══ ROW 2: User Growth Chart + Workplan Status ══════════════════════════ --}}

    {{-- User Growth Bar Chart --}}
    <div class="col-span-12 xl:col-span-7">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <div class="mb-5 flex items-start justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">User Registrations</h3>
                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">New accounts &middot; last 12 months</p>
                </div>
                <span class="rounded-lg border border-brand-100 bg-brand-50 px-2.5 py-1 text-[11px] font-medium text-brand-700 dark:border-brand-800/50 dark:bg-brand-500/10 dark:text-brand-400">
                    12 months
                </span>
            </div>
            <div id="chartUserGrowth" class="h-[220px] w-full"></div>
        </div>
    </div>

    {{-- Workplan Status --}}
    <div class="col-span-12 xl:col-span-5">
        <div class="flex h-full flex-col rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6"
             x-data="{ animated: false }"
             x-init="setTimeout(() => animated = true, 250)">
            <div class="mb-5 flex items-start justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Workplan Status</h3>
                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ $wpTotal }} total &middot; all organizations</p>
                </div>
            </div>

            <div class="flex-1 space-y-5">
                {{-- Active --}}
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-brand-500"></span>
                            <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Active</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-semibold tabular-nums text-gray-700 dark:text-gray-300">{{ $workplansActive }}</span>
                            <span class="text-[10px] text-gray-400 dark:text-gray-500">{{ $wpActivePct }}%</span>
                        </div>
                    </div>
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                        <div class="h-full rounded-full bg-brand-500"
                             style="transition: width 1000ms cubic-bezier(0.4,0,0.2,1); transition-delay: 0ms"
                             :style="{ width: animated ? '{{ $wpActivePct }}%' : '0%' }"></div>
                    </div>
                </div>

                {{-- Finalized --}}
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-success-500"></span>
                            <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Finalized</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-semibold tabular-nums text-gray-700 dark:text-gray-300">{{ $workplansFinalized }}</span>
                            <span class="text-[10px] text-gray-400 dark:text-gray-500">{{ $wpFinalizedPct }}%</span>
                        </div>
                    </div>
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                        <div class="h-full rounded-full bg-success-500"
                             style="transition: width 1000ms cubic-bezier(0.4,0,0.2,1); transition-delay: 150ms"
                             :style="{ width: animated ? '{{ $wpFinalizedPct }}%' : '0%' }"></div>
                    </div>
                </div>

                {{-- Archived --}}
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-gray-400 dark:bg-gray-500"></span>
                            <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Archived</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-semibold tabular-nums text-gray-700 dark:text-gray-300">{{ $workplansArchived }}</span>
                            <span class="text-[10px] text-gray-400 dark:text-gray-500">{{ $wpArchivedPct }}%</span>
                        </div>
                    </div>
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                        <div class="h-full rounded-full bg-gray-400 dark:bg-gray-500"
                             style="transition: width 1000ms cubic-bezier(0.4,0,0.2,1); transition-delay: 300ms"
                             :style="{ width: animated ? '{{ $wpArchivedPct }}%' : '0%' }"></div>
                    </div>
                </div>
            </div>

            {{-- Mini stat boxes --}}
            <div class="mt-6 grid grid-cols-3 gap-3 border-t border-gray-100 pt-5 dark:border-gray-800">
                <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-3 dark:border-gray-800 dark:bg-white/[0.02]">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Active</p>
                    <p class="mt-1.5 font-mono text-xl font-bold tabular-nums text-brand-600 dark:text-brand-400">{{ $workplansActive }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-3 dark:border-gray-800 dark:bg-white/[0.02]">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Done</p>
                    <p class="mt-1.5 font-mono text-xl font-bold tabular-nums text-success-600 dark:text-success-400">{{ $workplansFinalized }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-3 dark:border-gray-800 dark:bg-white/[0.02]">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Archived</p>
                    <p class="mt-1.5 font-mono text-xl font-bold tabular-nums text-gray-500 dark:text-gray-400">{{ $workplansArchived }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ ROW 3: System Activity Grouped Bar ══════════════════════════════════ --}}
    <div class="col-span-12">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">System Activity</h3>
                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Requests submitted vs. approvals resolved &middot; last 6 months</p>
                </div>
                <div class="flex items-center gap-5 text-xs text-gray-500 dark:text-gray-400">
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block h-2.5 w-2.5 rounded-sm bg-brand-500"></span>
                        Requests
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block h-2.5 w-2.5 rounded-sm bg-success-500"></span>
                        Approvals
                    </div>
                </div>
            </div>
            <div id="chartSystemActivity" class="h-[240px] w-full"></div>
        </div>
    </div>

    {{-- ══ ROW 4: Recent Activity Table ════════════════════════════════════════ --}}
    <div class="col-span-12">
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800 md:px-6">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Recent Activity</h3>
                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Latest 15 system requests &middot; all types</p>
                </div>
                <a href="{{ route('superadmin.profile-requests') }}"
                   class="text-[11px] font-semibold uppercase tracking-wide text-brand-600 transition hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">
                    View all &rarr;
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-gray-50 dark:border-gray-800/80">
                            <th class="px-5 py-3 text-left text-[10px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 md:px-6">#</th>
                            <th class="px-5 py-3 text-left text-[10px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 md:px-6">Type</th>
                            <th class="px-5 py-3 text-left text-[10px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 md:px-6">Requester</th>
                            <th class="hidden px-5 py-3 text-left text-[10px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 md:table-cell md:px-6">Organization</th>
                            <th class="hidden px-5 py-3 text-left text-[10px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 lg:table-cell md:px-6">Date</th>
                            <th class="px-5 py-3 text-left text-[10px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 md:px-6">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentActivity as $row)
                            <tr class="border-b border-gray-50 transition-colors hover:bg-gray-50/70 dark:border-gray-800/60 dark:hover:bg-white/[0.02] last:border-b-0">
                                <td class="px-5 py-3.5 md:px-6">
                                    <span class="font-mono text-xs text-gray-400 dark:text-gray-500">#{{ $row->request_id }}</span>
                                </td>
                                <td class="px-5 py-3.5 md:px-6">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $row->action_label }}</span>
                                </td>
                                <td class="px-5 py-3.5 md:px-6">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ $row->requester_name }}</span>
                                </td>
                                <td class="hidden px-5 py-3.5 md:table-cell md:px-6">
                                    <span class="text-sm text-gray-500 dark:text-gray-500">{{ $row->organization_name ?: '—' }}</span>
                                </td>
                                <td class="hidden px-5 py-3.5 lg:table-cell md:px-6">
                                    <span class="font-mono text-xs text-gray-400 dark:text-gray-500">
                                        {{ \Illuminate\Support\Carbon::parse($row->requested_at)->format('M d, Y') }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 md:px-6">
                                    @if ($row->status === 'pending')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-warning-50 px-2.5 py-1 text-xs font-medium text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">
                                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-warning-500"></span>
                                            Pending
                                        </span>
                                    @elseif ($row->status === 'approved')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-success-50 px-2.5 py-1 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">
                                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-success-500"></span>
                                            Approved
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-error-50 px-2.5 py-1 text-xs font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400">
                                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-error-500"></span>
                                            Rejected
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center md:px-6">
                                    <p class="text-sm text-gray-400 dark:text-gray-500">No system activity recorded yet.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const adminRows = document.getElementById('admin-dashboard-audit-rows');
    const superAdminRows = document.getElementById('superadmin-dashboard-audit-rows');
    const endpoint = '{{ route('dashboard-reports.audit-logs.recent') }}';

    async function loadDashboardAuditRows() {
        try {
            const response = await fetch(endpoint, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                throw new Error('Unable to load audit logs');
            }

            const entries = await response.json();
            const markup = (entries && entries.length > 0)
                ? entries.map((entry) => `
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="px-4 py-3">${entry.name || entry.user_email || '—'}</td>
                        <td class="px-4 py-3">${entry.interaction || '—'}</td>
                        <td class="px-4 py-3">${entry.logged_at || '—'}</td>
                    </tr>
                `).join('')
                : '<tr class="border-t border-gray-200 dark:border-gray-700"><td colspan="3" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No audit activity recorded yet.</td></tr>';

            if (adminRows) {
                adminRows.innerHTML = markup;
            }
            if (superAdminRows) {
                superAdminRows.innerHTML = markup;
            }
        } catch (error) {
            const fallback = '<tr class="border-t border-gray-200 dark:border-gray-700"><td colspan="3" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Unable to load live audit activity.</td></tr>';
            if (adminRows) {
                adminRows.innerHTML = fallback;
            }
            if (superAdminRows) {
                superAdminRows.innerHTML = fallback;
            }
        }
    }

    loadDashboardAuditRows();
    setInterval(loadDashboardAuditRows, 15000);
    const isDark     = document.documentElement.classList.contains('dark');
    const gridColor  = isDark ? 'rgba(255,255,255,0.05)' : '#f3f4f6';
    const labelColor = isDark ? '#6b7280' : '#9ca3af';
    const fontFamily = 'Outfit, sans-serif';

    const growthLabels  = {{ Js::from($userGrowthLabels) }};
    const growthData    = {{ Js::from($userGrowthData) }};
    const actLabels     = {{ Js::from($activityLabels) }};
    const actRequests   = {{ Js::from($activityRequests) }};
    const actApprovals  = {{ Js::from($activityApprovals) }};

    // ── Chart 1: User Growth ─────────────────────────────────────────────────
    const growthEl = document.getElementById('chartUserGrowth');
    if (growthEl) {
        new ApexCharts(growthEl, {
            series: [{ name: 'New Users', data: growthData }],
            chart: {
                type: 'bar',
                height: 220,
                fontFamily,
                toolbar: { show: false },
                background: 'transparent',
                animations: {
                    enabled: true,
                    speed: 700,
                    animateGradually: { enabled: true, delay: 50 },
                },
            },
            colors: ['#465fff'],
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '52%',
                    borderRadius: 5,
                    borderRadiusApplication: 'end',
                },
            },
            dataLabels: { enabled: false },
            xaxis: {
                categories: growthLabels,
                labels: {
                    rotate: -30,
                    style: {
                        colors: Array(growthLabels.length).fill(labelColor),
                        fontSize: '10px',
                        fontFamily,
                    },
                },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                min: 0,
                labels: {
                    style: { colors: [labelColor], fontSize: '10px', fontFamily },
                    formatter: (v) => Math.round(v),
                },
            },
            grid: {
                borderColor: gridColor,
                strokeDashArray: 3,
                yaxis: { lines: { show: true } },
                xaxis: { lines: { show: false } },
            },
            tooltip: { theme: isDark ? 'dark' : 'light', style: { fontFamily } },
            fill: {
                type: 'gradient',
                gradient: {
                    type: 'vertical',
                    shadeIntensity: 0.2,
                    opacityFrom: 1,
                    opacityTo: 0.65,
                    stops: [0, 100],
                },
            },
        }).render();
    }

    // ── Chart 2: System Activity ─────────────────────────────────────────────
    const actEl = document.getElementById('chartSystemActivity');
    if (actEl) {
        new ApexCharts(actEl, {
            series: [
                { name: 'Requests Submitted', data: actRequests },
                { name: 'Approvals Resolved',  data: actApprovals },
            ],
            chart: {
                type: 'bar',
                height: 240,
                fontFamily,
                toolbar: { show: false },
                background: 'transparent',
                animations: {
                    enabled: true,
                    speed: 700,
                    animateGradually: { enabled: true, delay: 80 },
                },
            },
            colors: ['#465fff', '#17b26a'],
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '55%',
                    borderRadius: 4,
                    borderRadiusApplication: 'end',
                },
            },
            dataLabels: { enabled: false },
            legend: { show: false },
            xaxis: {
                categories: actLabels,
                labels: {
                    style: {
                        colors: Array(actLabels.length).fill(labelColor),
                        fontSize: '10px',
                        fontFamily,
                    },
                },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                min: 0,
                labels: {
                    style: { colors: [labelColor], fontSize: '10px', fontFamily },
                    formatter: (v) => Math.round(v),
                },
            },
            grid: {
                borderColor: gridColor,
                strokeDashArray: 3,
                yaxis: { lines: { show: true } },
                xaxis: { lines: { show: false } },
            },
            tooltip: {
                theme: isDark ? 'dark' : 'light',
                style: { fontFamily },
                shared: true,
                intersect: false,
            },
            fill: { opacity: [0.9, 0.85] },
        }).render();
    }
});
</script>
@endpush
