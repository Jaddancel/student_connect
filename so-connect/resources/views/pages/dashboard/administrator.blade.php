@extends('layouts.app')

@section('content')
    <div class="grid grid-cols-12 gap-4 md:gap-6">
        <div class="col-span-12">
            <div class="rounded-2xl border border-brand-100 bg-gradient-to-r from-brand-50 to-white p-5 shadow-sm dark:border-brand-900/30 dark:from-brand-950/20 dark:to-gray-900">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-brand-600 dark:text-brand-400">Executive reporting</p>
                        <h2 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Download a concise dashboard report as CSV, Excel, or PDF and monitor live audit activity.</h2>
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
                        <tbody id="admin-dashboard-audit-rows">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-span-12">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <x-dashboard.type-switcher />

                <div class="w-full xl:w-auto">
                    <label for="dashboard-window-filter" class="sr-only">Filter dashboard timeframe</label>
                    <select id="dashboard-window-filter"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 outline-none transition focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 xl:min-w-[180px]"
                        x-model="$store.dashboardWindow.key"
                        x-on:change="$store.dashboardWindow.setWindow($event.target.value)">
                        <option value="day">Last 24 hours</option>
                        <option value="month">1 month</option>
                        <option value="all">Overall</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="col-span-12" x-show="$store.dashboardType.current.code === 7">
            <x-dashboard.role-by-the-number />
        </div>

        <div class="col-span-12 space-y-6 xl:col-span-7" x-show="$store.dashboardType.current.code !== 7">
            <x-dashboard.metrics-by-type />
            <x-dashboard.list-by-type :canDecide="$canDecide ?? true" />
        </div>
        <div class="col-span-12 space-y-6 xl:col-span-5" x-show="$store.dashboardType.current.code !== 7">
            <x-dashboard.compare-by-type />
            <x-dashboard.a-d-chart-by-type />
        </div>

        <div class="col-span-12 space-y-6 xl:col-span-7" x-show="$store.dashboardType.current.code === 7">
            <x-dashboard.list-by-type :canDecide="$canDecide ?? true" />
        </div>
        <div class="col-span-12 space-y-6 xl:col-span-5" x-show="$store.dashboardType.current.code === 7">
            <x-dashboard.a-d-chart-by-type />
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = document.getElementById('admin-dashboard-audit-rows');
    const endpoint = '{{ route('dashboard-reports.audit-logs.recent') }}';

    if (!rows) {
        return;
    }

    async function loadRows() {
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

            rows.innerHTML = markup;
        } catch (error) {
            rows.innerHTML = '<tr class="border-t border-gray-200 dark:border-gray-700"><td colspan="3" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Unable to load live audit activity.</td></tr>';
        }
    }

    loadRows();
    setInterval(loadRows, 15000);
});
</script>
@endpush
