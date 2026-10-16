@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title" />

    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
            <div>
                <h3 class="font-semibold text-gray-800 dark:text-white/90">{{ $isSupportAgent ? 'Assistant escalations' : 'Your support requests' }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Track replies and resolution status in one place.</p>
            </div>
            @if ($isSupportAgent)
                <form method="GET" action="{{ route('admin.support-tickets.index') }}">
                    <select name="status" onchange="this.form.submit()" class="h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(($selectedStatus ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        @if ($tickets->isEmpty())
            <p class="p-10 text-center text-sm text-gray-500 dark:text-gray-400">No support tickets found.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-100 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        <tr><th class="px-5 py-3">Ticket</th>@if($isSupportAgent)<th class="px-5 py-3">Requester</th>@endif<th class="px-5 py-3">Status</th><th class="px-5 py-3">Assigned to</th><th class="px-5 py-3">Updated</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($tickets as $ticket)
                            @php
                                $requesterName = collect([$ticket->requester?->profileRecord?->first_name, $ticket->requester?->profileRecord?->last_name])->filter()->implode(' ') ?: $ticket->requester?->user_email;
                                $assigneeName = collect([$ticket->assignee?->profileRecord?->first_name, $ticket->assignee?->profileRecord?->last_name])->filter()->implode(' ') ?: $ticket->assignee?->user_email;
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-4"><a href="{{ route('support-tickets.show', $ticket) }}" class="font-medium text-brand-600 hover:underline">#{{ $ticket->id }} {{ $ticket->subject }}</a></td>
                                @if($isSupportAgent)<td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $requesterName }}</td>@endif
                                <td class="px-5 py-4"><span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $statuses[$ticket->status] }}</span></td>
                                <td class="px-5 py-4 text-gray-500 dark:text-gray-400">{{ $assigneeName ?: 'Unassigned' }}</td>
                                <td class="px-5 py-4 text-gray-500 dark:text-gray-400">{{ $ticket->updated_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-800">{{ $tickets->links() }}</div>
        @endif
    </div>
@endsection
