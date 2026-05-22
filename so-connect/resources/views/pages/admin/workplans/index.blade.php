@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Workplans" />

    <div class="space-y-6">

        @if ($grouped->isEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white p-10 text-center dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-400 dark:text-gray-500">No workplans found. Create a semester first to generate workplans.</p>
            </div>
        @else
            @foreach ($grouped as $group)
                @php $semester = $group['semester']; @endphp

                <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    {{-- Semester header --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                        <div>
                            <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">
                                {{ $semester?->name ?? 'Unknown Semester' }}
                            </h3>
                            @if ($semester)
                                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                    Preparation period:
                                    {{ $semester->activePeriodStart()->format('M d') }} – {{ $semester->activePeriodEnd()->format('M d, Y') }}
                                    &middot; Semester starts {{ $semester->starts_at->format('M d, Y') }}
                                </p>
                            @endif
                        </div>
                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $group['workplans']->count() }} {{ Str::plural('organization', $group['workplans']->count()) }}</span>
                    </div>

                    {{-- Workplans table --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Organization</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Status</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Approved Plans</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Finalized By</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($group['workplans'] as $row)
                                    @php $wp = $row['workplan']; @endphp
                                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                        <td class="px-6 py-4 font-medium text-gray-800 dark:text-white/90">{{ $row['org_name'] }}</td>
                                        <td class="px-6 py-4">
                                            @if ($wp->status === 'active')
                                                <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">Active</span>
                                            @elseif ($wp->status === 'finalized')
                                                <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">Finalized</span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">Archived</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $row['plans_count'] }}</td>
                                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                                            {{ $row['finalizer_name'] ?? '—' }}
                                            @if ($wp->finalized_at)
                                                <span class="block text-xs text-gray-400">{{ \Illuminate\Support\Carbon::parse($wp->finalized_at)->format('M d, Y') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            @if (in_array($wp->status, ['finalized', 'archived']))
                                                <a href="{{ route('documents.index', ['organization_id' => $wp->organization_id]) }}"
                                                    class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                                                    View Documents →
                                                </a>
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        @endif

    </div>
@endsection
