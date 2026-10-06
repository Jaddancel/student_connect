@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Organization Accreditation" />

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('success'))
            <div class="rounded-xl border border-success-300 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/40 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Suspended organizations</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Organizations disabled for missing accreditation. Restore one to lift the suspension,
                or permanently delete it once its {{ $graceDays }}-day grace period has elapsed.
            </p>

            @if ($disabled->isEmpty())
                <p class="mt-6 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                    No organizations are currently suspended.
                </p>
            @else
                <div class="mt-6 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="pb-2">Organization</th>
                                <th class="pb-2">Disabled</th>
                                <th class="pb-2">Purge eligible</th>
                                <th class="pb-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($disabled as $org)
                                <tr>
                                    <td class="py-3 font-medium text-gray-800 dark:text-white/90">{{ $org['name'] }}</td>
                                    <td class="py-3 text-gray-500 dark:text-gray-400">{{ $org['disabled_at']?->toFormattedDateString() ?? '—' }}</td>
                                    <td class="py-3 text-gray-500 dark:text-gray-400">
                                        {{ $org['purge_eligible_at']?->toFormattedDateString() ?? '—' }}
                                        @if ($org['purge_eligible'])
                                            <span class="ml-1 rounded bg-error-50 px-1.5 py-0.5 text-[10px] font-semibold text-error-600 dark:bg-error-500/10">now</span>
                                        @endif
                                    </td>
                                    <td class="py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <form method="POST" action="{{ route('superadmin.organizations.restore', $org['id']) }}">
                                                @csrf
                                                <button type="submit"
                                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                                                    Restore
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('superadmin.organizations.purge', $org['id']) }}"
                                                onsubmit="return confirm('Permanently delete {{ $org['name'] }} and all of its data? This cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    @disabled(! $org['purge_eligible'])
                                                    class="rounded-lg bg-error-500 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-error-600 disabled:cursor-not-allowed disabled:opacity-40">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
