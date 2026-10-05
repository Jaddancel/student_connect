@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="After Event Form" />

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @unless ($enabled)
            <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm font-medium text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-orange-400">
                The After Event Report form has not been set up yet. An administrator needs to create it in the Form Builder before reports can be filed.
            </div>
        @endunless

        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <div>
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Concluded events</h3>
                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                        Events appear here {{ $elapsedDays }} {{ Str::plural('day', $elapsedDays) }} after they end.
                        @if ($deadline)
                            Reports for {{ $semester?->name ?? 'this semester' }} must be filed by {{ $deadline->format('F j, Y') }}.
                        @else
                            Reports cannot be filed after the current semester ends.
                        @endif
                    </p>
                </div>
                <span class="text-xs text-gray-400 dark:text-gray-500">
                    {{ $events->where('filed', false)->count() }} unfiled · {{ $events->count() }} total
                </span>
            </div>

            @if ($events->isEmpty())
                <div class="px-6 py-16 text-center">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No concluded events need a report right now.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-xs font-semibold text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                <th class="px-6 py-3">Event</th>
                                @if ($multipleOrganizations)
                                    <th class="px-6 py-3">Organization</th>
                                @endif
                                <th class="px-6 py-3">After Report Status</th>
                                <th class="px-6 py-3">Date Finished</th>
                                <th class="px-6 py-3">Time Since Event</th>
                                <th class="px-6 py-3 text-right">Form</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($events as $event)
                                <tr class="text-gray-700 dark:text-gray-300">
                                    <td class="px-6 py-3">
                                        <p class="font-medium text-gray-800 dark:text-white/90">{{ $event['name'] }}</p>
                                        @if ($event['location'] !== '')
                                            <p class="text-xs text-gray-400">{{ $event['location'] }}</p>
                                        @endif
                                    </td>
                                    @if ($multipleOrganizations)
                                        <td class="px-6 py-3">{{ $event['organization_name'] }}</td>
                                    @endif
                                    <td class="px-6 py-3">
                                        @if ($event['filed'])
                                            <span class="rounded-full bg-success-50 px-2.5 py-1 text-xs font-medium text-success-600 dark:bg-success-500/15 dark:text-success-500">Filed</span>
                                        @else
                                            <span class="rounded-full bg-warning-50 px-2.5 py-1 text-xs font-medium text-warning-600 dark:bg-warning-500/15 dark:text-orange-400">Not filed</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3">{{ $event['finished_at']->format('M j, Y g:i A') }}</td>
                                    <td class="px-6 py-3">{{ $event['time_since'] }}</td>
                                    <td class="px-6 py-3 text-right">
                                        @if ($event['filed'])
                                            @if ($event['document_id'])
                                                <a href="{{ route('download-files.download', $event['document_id']) }}"
                                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">View document</a>
                                            @else
                                                <span class="text-xs text-gray-400">Filed</span>
                                            @endif
                                        @elseif ($enabled)
                                            <a href="{{ route('forms.render', ['routeName' => $form->route_name, 'event' => $event['event_id']]) }}"
                                                class="rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-brand-600">File report</a>
                                        @else
                                            <span class="cursor-not-allowed rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-400 dark:border-gray-700">File report</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
