@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Executive Dashboard Report</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Administrative summary view for admins and superadmins with export options.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('dashboard-reports.export.csv') }}" class="inline-flex items-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Export CSV</a>
                <a href="{{ route('dashboard-reports.export.excel') }}" class="inline-flex items-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Export Excel</a>
                <a href="{{ route('dashboard-reports.export.pdf') }}" class="inline-flex items-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Export PDF</a>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Registered Users</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ $report['totals']['users'] }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Organizations</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ $report['totals']['organizations'] }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Pending Profile Requests</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ $report['totals']['pending_profile_requests'] }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Pending Officer Requests</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ $report['totals']['pending_officer_requests'] }}</p>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Activity</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-6 py-3">Request</th>
                        <th class="px-6 py-3">Requester</th>
                        <th class="px-6 py-3">Organization</th>
                        <th class="px-6 py-3">Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($report['recent_activity'] as $item)
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            <td class="px-6 py-3">{{ $item->action_label }}</td>
                            <td class="px-6 py-3">{{ $item->requester_name }}</td>
                            <td class="px-6 py-3">{{ $item->organization_name ?: '—' }}</td>
                            <td class="px-6 py-3">{{ ucfirst($item->status) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Live Audit Activity</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Recent admin and user audit events. Updates automatically every 15 seconds.</p>
                </div>
                <span id="audit-refresh-indicator" class="inline-flex items-center gap-2 rounded-full bg-success-50 px-3 py-1 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">
                    <span class="h-2 w-2 animate-pulse rounded-full bg-success-500"></span>
                    Live
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-6 py-3">Actor</th>
                        <th class="px-6 py-3">Event</th>
                        <th class="px-6 py-3">Timestamp</th>
                    </tr>
                    </thead>
                    <tbody id="dashboard-audit-rows">
                    @forelse ($recentAuditLogs as $entry)
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            <td class="px-6 py-3">{{ $entry['name'] }}</td>
                            <td class="px-6 py-3">{{ $entry['interaction'] }}</td>
                            <td class="px-6 py-3">{{ $entry['logged_at'] }}</td>
                        </tr>
                    @empty
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            <td colspan="3" class="px-6 py-6 text-center text-gray-500 dark:text-gray-400">No audit activity recorded yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = document.getElementById('dashboard-audit-rows');
    const indicator = document.getElementById('audit-refresh-indicator');
    const endpoint = '{{ route('dashboard-reports.audit-logs.recent') }}';

    if (!rows || !indicator) {
        return;
    }

    async function refreshAuditRows() {
        try {
            const response = await fetch(endpoint, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                throw new Error('Unable to load audit logs');
            }

            const entries = await response.json();
            if (!Array.isArray(entries) || entries.length === 0) {
                rows.innerHTML = '<tr class="border-t border-gray-200 dark:border-gray-700"><td colspan="3" class="px-6 py-6 text-center text-gray-500 dark:text-gray-400">No audit activity recorded yet.</td></tr>';
                return;
            }

            rows.innerHTML = entries.map((entry) => `
                <tr class="border-t border-gray-200 dark:border-gray-700">
                    <td class="px-6 py-3">${entry.name || entry.user_email || '—'}</td>
                    <td class="px-6 py-3">${entry.interaction || '—'}</td>
                    <td class="px-6 py-3">${entry.logged_at || '—'}</td>
                </tr>
            `).join('');
        } catch (error) {
            indicator.innerHTML = '<span class="h-2 w-2 rounded-full bg-warning-500"></span>Offline';
            indicator.className = 'inline-flex items-center gap-2 rounded-full bg-warning-50 px-3 py-1 text-xs font-medium text-warning-700 dark:bg-warning-500/10 dark:text-warning-400';
        }
    }

    refreshAuditRows();
    setInterval(refreshAuditRows, 15000);
});
</script>
@endpush
