@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Requests & Approvals Center" />

    @php
        $pendingIds = $requests->getCollection()->where('status', 'pending')->pluck('request_id')->map(fn ($id) => (string) $id)->values();
        $statusOptions = ['all' => 'All statuses', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
    @endphp

    <div class="space-y-6" x-data="adminUnifiedRequestSelection({ available: @js($pendingIds) })">
        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any() || session('bulk_failures'))
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                @if ($errors->any())<p class="font-medium">{{ $errors->first() }}</p>@endif
                @foreach ((array) session('bulk_failures', []) as $failure)<p>{{ $failure }}</p>@endforeach
            </div>
        @endif

        @if (session('bulk_warnings'))
            <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                @foreach ((array) session('bulk_warnings', []) as $warning)<p>{{ $warning }}</p>@endforeach
            </div>
        @endif

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <a href="{{ route('admin.requests.index', array_filter(['status' => $activeStatus])) }}"
                class="rounded-2xl border p-4 transition {{ $activeType === null ? 'border-palette-lime bg-palette-lime-pale/60' : 'border-gray-200 bg-white hover:border-palette-lime' }} dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm font-semibold text-gray-800 dark:text-white">All request types</p>
                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $pendingTotal }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">pending</p>
            </a>
            @foreach ($workflows as $key => $workflow)
                <a href="{{ route('admin.requests.index', ['type' => $key, 'status' => $activeStatus]) }}"
                    class="rounded-2xl border p-4 transition {{ $activeType === $key ? 'border-palette-lime bg-palette-lime-pale/60' : 'border-gray-200 bg-white hover:border-palette-lime' }} dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm font-semibold text-gray-800 dark:text-white">{{ $workflow['short_label'] }}</p>
                        @unless ($workflow['configured'])
                            <span class="rounded-full bg-warning-50 px-2 py-0.5 text-[10px] font-medium text-warning-700">Not configured</span>
                        @endunless
                    </div>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $workflow['pending'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">pending</p>
                </a>
            @endforeach
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <form method="GET" action="{{ route('admin.requests.index') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                @if ($activeType)<input type="hidden" name="type" value="{{ $activeType }}">@endif
                <div class="xl:col-span-2">
                    <label for="request-search" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">Search</label>
                    <input id="request-search" name="q" value="{{ request('q') }}" placeholder="Organization, requester, email, or ID"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-palette-lime dark:border-gray-700 dark:text-white">
                </div>
                <div>
                    <label for="request-status" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">Status</label>
                    <select id="request-status" name="status" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        @foreach ($statusOptions as $value => $label)<option value="{{ $value }}" @selected($activeStatus === $value)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="request-from" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">From</label>
                    <input id="request-from" type="date" name="from" value="{{ request('from') }}" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white">
                </div>
                <div>
                    <label for="request-to" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">To</label>
                    <input id="request-to" type="date" name="to" value="{{ request('to') }}" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white">
                </div>
                <div class="flex items-end gap-2">
                    <button class="h-10 rounded-lg bg-palette-lime px-4 text-sm font-semibold text-gray-900">Apply</button>
                    <a href="{{ route('admin.requests.index', array_filter(['type' => $activeType])) }}" class="inline-flex h-10 items-center rounded-lg border border-gray-300 px-3 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">Reset</a>
                </div>
            </form>
        </div>

        <form method="POST" action="{{ route('admin.requests.bulk') }}" @submit="confirmBulk" class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            @csrf
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <div>
                    <h2 class="font-semibold text-gray-800 dark:text-white">{{ $activeType ? $workflows[$activeType]['label'] : 'All requests' }}</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $requests->total() }} matching request(s)</p>
                </div>
                <div class="flex flex-wrap items-end gap-2">
                    <div>
                        <label for="bulk-decision" class="mb-1 block text-xs text-gray-500">Bulk action</label>
                        <select id="bulk-decision" name="decision" x-model="decision" class="h-9 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="approve">Approve selected</option>
                            <option value="reject">Reject selected</option>
                        </select>
                    </div>
                    <div x-show="decision === 'reject'" x-cloak>
                        <label for="bulk-reason" class="mb-1 block text-xs text-gray-500">Reason</label>
                        <input id="bulk-reason" name="rejection_reason" :required="decision === 'reject'" maxlength="1000" placeholder="Required for rejection"
                            class="h-9 w-64 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white">
                    </div>
                    <button type="submit" :disabled="selected.length === 0" class="h-9 rounded-lg bg-palette-lime px-4 text-sm font-semibold text-gray-900 disabled:cursor-not-allowed disabled:opacity-50">
                        Apply to <span x-text="selected.length"></span>
                    </button>
                </div>
            </div>

            @if ($requests->isEmpty())
                <div class="px-6 py-14 text-center text-sm text-gray-500 dark:text-gray-400">No requests match these filters.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-100 dark:border-gray-800">
                            <tr>
                                <th class="w-12 px-5 py-3"><input type="checkbox" :checked="allSelected()" @change="toggleAll" aria-label="Select all pending requests on this page" @disabled($pendingIds->isEmpty())></th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-500">Type</th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-500">Organization</th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-500">Requester</th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-500">Submitted</th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-500">Status</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($requests as $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-5 py-4">
                                        @if ($row->status === 'pending')
                                            <input type="checkbox" name="request_ids[]" value="{{ $row->request_id }}" x-model="selected" aria-label="Select request {{ $row->request_id }}">
                                        @else<span class="text-gray-300">—</span>@endif
                                    </td>
                                    <td class="px-4 py-4"><span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $row->workflow_label }}</span></td>
                                    <td class="px-4 py-4 font-medium text-gray-800 dark:text-white">{{ $row->organization_name }}</td>
                                    <td class="px-4 py-4 text-gray-600 dark:text-gray-300">{{ $row->requester_name }}</td>
                                    <td class="px-4 py-4 text-gray-500">{{ \Illuminate\Support\Carbon::parse($row->requested_at)->format('M d, Y g:i A') }}</td>
                                    <td class="px-4 py-4">
                                        @php($statusClass = match($row->status) { 'approved' => 'bg-success-50 text-success-700', 'rejected' => 'bg-error-50 text-error-700', default => 'bg-warning-50 text-warning-700' })
                                        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClass }}">{{ ucfirst($row->status) }}</span>
                                        @if ($row->status === 'rejected' && filled($row->rejection_reason))
                                            <p class="mt-2 max-w-xs text-xs leading-5 text-error-600 dark:text-error-400">
                                                {{ $row->rejection_reason }}
                                            </p>
                                        @elseif ($row->status === 'approved' && $row->approved_at)
                                            <p class="mt-2 text-xs text-gray-400">
                                                {{ \Illuminate\Support\Carbon::parse($row->approved_at)->format('M d, Y g:i A') }}
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right"><a href="{{ $row->show_url }}" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300">{{ $row->status === 'pending' ? 'Review' : 'View' }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-800">{{ $requests->links() }}</div>
            @endif
        </form>

        @if ($activeType === 'workplans')
            <div class="text-right"><a href="{{ route('admin.workplans.index') }}" class="text-sm font-medium text-brand-500 hover:underline">View compiled and finalized workplans →</a></div>
        @endif
    </div>
@endsection
