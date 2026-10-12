@extends('layouts.app')

@php
    $cards = [
        [
            'value' => $summary['registered_accounts'],
            'label' => 'Registered Accounts',
            'hint' => 'All accounts · excl. superadmin',
            'accent' => 'brand',
            'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        ],
        [
            'value' => $summary['active_organizations'],
            'label' => 'Active Organizations',
            'hint' => 'Accreditation not disabled',
            'accent' => 'success',
            'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        ],
        [
            'value' => $summary['documents_generated'],
            'label' => 'Documents Generated',
            'hint' => 'Successfully generated, all time',
            'accent' => 'warning',
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        ],
    ];
    $accentBar = [
        'brand' => 'from-brand-400 to-brand-600',
        'success' => 'from-success-400 to-success-600',
        'warning' => 'from-warning-400 to-warning-600',
        'blue-light' => 'from-blue-light-400 to-blue-light-600',
    ];
    $accentIcon = [
        'brand' => 'bg-brand-50 ring-brand-100/80 text-brand-600 dark:bg-brand-500/10 dark:ring-brand-500/20 dark:text-brand-400',
        'success' => 'bg-success-50 ring-success-100/80 text-success-600 dark:bg-success-500/10 dark:ring-success-500/20 dark:text-success-400',
        'warning' => 'bg-warning-50 ring-warning-100/80 text-warning-600 dark:bg-warning-500/10 dark:ring-warning-500/20 dark:text-warning-400',
        'blue-light' => 'bg-blue-light-50 ring-blue-light-100/80 text-blue-light-600 dark:bg-blue-light-500/10 dark:ring-blue-light-500/20 dark:text-blue-light-400',
    ];
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
                    Online &middot; {{ now()->timezone(config('app.display_timezone'))->format('l, F j, Y') }}
                </p>
            </div>
        </div>

        {{-- Uptime clock --}}
        <div x-data="uptimeClock({{ $uptimeSeconds ?? 'null' }})"
             class="flex items-center gap-3 self-start sm:self-auto"
             title="Time since the server was last started">
            <span class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Uptime</span>
            <template x-if="available">
                <div class="flex items-center gap-1.5" data-uptime-clock>
                    <span x-show="days > 0" x-cloak
                          class="mr-1 rounded-lg border border-brand-200 bg-brand-50 px-2 py-1.5 font-mono text-sm font-bold tabular-nums text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-300"
                          x-text="days + 'd'"></span>
                    <template x-for="(digit, index) in digits" :key="index">
                        <span class="contents">
                            <span x-show="index === 2 || index === 4" class="font-mono text-sm font-bold text-gray-300 dark:text-gray-600">:</span>
                            <span class="flex h-9 w-7 items-center justify-center rounded-lg border border-gray-200 bg-white/80 font-mono text-base font-bold tabular-nums text-gray-800 shadow-sm dark:border-gray-700/80 dark:bg-gray-900/50 dark:text-white/90"
                                  x-text="digit"></span>
                        </span>
                    </template>
                </div>
            </template>
            <span x-show="!available" class="font-mono text-sm text-gray-400 dark:text-gray-500">Unavailable</span>
        </div>
    </div>
</div>

<div class="grid grid-cols-12 gap-4 md:gap-6"
     x-data="systemDashboard({ metrics: {{ Js::from($metrics) }}, url: {{ Js::from(route('superadmin.dashboard.metrics')) }} })">

    {{-- ══ KPI CARDS ══════════════════════════════════════════════════════════ --}}
    @foreach ($cards as $card)
        <div class="col-span-12 sm:col-span-6 xl:col-span-3"
             x-data="{ count: 0 }"
             x-init="
                 const target = {{ (int) $card['value'] }};
                 if (target === 0) return;
                 const start = performance.now();
                 const tick = (now) => {
                     const t = Math.min((now - start) / 900, 1);
                     count = Math.round((1 - Math.pow(1 - t, 3)) * target);
                     if (t < 1) requestAnimationFrame(tick);
                 };
                 requestAnimationFrame(tick);
             ">
            <div class="relative h-full overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="absolute inset-x-0 top-0 h-0.5 rounded-t-2xl bg-gradient-to-r {{ $accentBar[$card['accent']] }}"></div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl ring-1 {{ $accentIcon[$card['accent']] }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}" />
                    </svg>
                </div>
                <div class="mt-4">
                    <p class="font-mono text-3xl font-bold tabular-nums tracking-tight text-gray-900 dark:text-white"
                       x-text="count.toLocaleString()">{{ number_format($card['value']) }}</p>
                    <p class="mt-1 text-sm font-medium text-gray-700 dark:text-gray-300">{{ $card['label'] }}</p>
                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ $card['hint'] }}</p>
                </div>
            </div>
        </div>
    @endforeach

    {{-- Storage Used --}}
    <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <div class="relative h-full overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <div class="absolute inset-x-0 top-0 h-0.5 rounded-t-2xl bg-gradient-to-r {{ $accentBar['blue-light'] }}"></div>
            <div class="flex h-11 w-11 items-center justify-center rounded-xl ring-1 {{ $accentIcon['blue-light'] }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                </svg>
            </div>
            <div class="mt-4">
                <p class="font-mono text-3xl font-bold tabular-nums tracking-tight text-gray-900 dark:text-white">{{ $summary['storage_label'] }}</p>
                <p class="mt-1 text-sm font-medium text-gray-700 dark:text-gray-300">Storage Used</p>
                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Uploads, documents &amp; backups</p>
            </div>
        </div>
    </div>

    {{-- ══ DATA VIEW ══════════════════════════════════════════════════════════ --}}
    <div class="col-span-12 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Data View</h2>
            <p class="text-xs text-gray-400 dark:text-gray-500">Period used by the charts below</p>
        </div>
        <div class="flex items-center gap-3">
            <svg x-show="loading" x-cloak class="h-4 w-4 animate-spin text-brand-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <label for="dashboard-period" class="sr-only">Data view period</label>
            <select id="dashboard-period" x-model="period" :disabled="loading"
                    class="h-10 min-w-[10rem] rounded-xl border border-gray-200 bg-white px-4 pr-9 text-sm font-medium text-gray-700 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                @foreach ($periodOptions as $value => $label)
                    <option value="{{ $value }}" @selected($value === $period)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <template x-if="error">
        <div class="col-span-12 rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400" x-text="error"></div>
    </template>

    {{-- ══ ACTIVITY ═══════════════════════════════════════════════════════════ --}}
    <div class="col-span-12">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90" x-text="activityTitle">Activity</h3>
                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                        Requests, document generations and downloads &middot;
                        <span x-text="metrics.activity.totals.all.toLocaleString()"></span> total
                        (<span x-text="metrics.activity.totals.requests.toLocaleString()"></span> requests,
                        <span x-text="metrics.activity.totals.generations.toLocaleString()"></span> generated,
                        <span x-text="metrics.activity.totals.downloads.toLocaleString()"></span> downloads)
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block h-3 w-0.5 bg-error-500"></span>
                        Semester start
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block h-3 w-3 rounded-sm border border-dashed border-error-500 bg-error-500/15"></span>
                        Semester preparation
                    </div>
                </div>
            </div>
            <div x-ref="activityChart" class="h-[300px] w-full" data-chart="activity"></div>
        </div>
    </div>

    {{-- ══ USER REGISTRATIONS ═════════════════════════════════════════════════ --}}
    <div class="col-span-12 xl:col-span-8">
        <div class="h-full rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90" x-text="registrationsTitle">User registrations</h3>
            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">New accounts created per period</p>
            <div x-ref="registrationsChart" class="mt-4 h-[260px] w-full" data-chart="registrations"></div>
        </div>
    </div>

    {{-- ══ ERROR RATE ═════════════════════════════════════════════════════════ --}}
    <div class="col-span-12 xl:col-span-4">
        <div class="flex h-full flex-col items-center justify-center rounded-2xl border border-gray-200 bg-white p-6 text-center dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Error rates in <span class="font-semibold text-gray-800 dark:text-white/90" x-text="errorRate.current_label"></span> are
            </p>
            <p class="my-5 font-mono text-5xl font-bold tabular-nums tracking-tight text-gray-900 dark:text-white" x-text="errorRateText">—</p>
            <p class="text-sm font-medium" :class="comparisonClass" x-text="comparisonText"></p>
            <p class="mt-4 text-[11px] text-gray-400 dark:text-gray-500">
                Server errors (5xx) out of
                <span x-text="errorRate.requests.toLocaleString()"></span> requests
            </p>
        </div>
    </div>

</div>
@endsection
