@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Scoring Rubric Versions" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div>
                <h2 class="text-base font-semibold text-gray-800 dark:text-white/90">Immutable rubric history</h2>
                <p class="mt-1 text-xs text-gray-500">A new version is recorded whenever a criterion or trigger changes. Verified scores keep the exact version used at the time.</p>
            </div>
            <a href="{{ route('admin.scoring.rules.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Back to scoring rules</a>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-white/[0.03]">
                        <tr><th class="px-5 py-3">Version</th><th class="px-5 py-3">Published</th><th class="px-5 py-3">Configuration</th><th class="px-5 py-3">Used by scores</th><th class="px-5 py-3">Hash</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($versions as $version)
                            @php $snapshot = (array) $version->snapshot; @endphp
                            <tr>
                                <td class="px-5 py-4 font-semibold text-gray-800 dark:text-gray-200">v{{ $version->version }}</td>
                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $version->published_at?->format('M j, Y g:i A') }}</td>
                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ count($snapshot['categories'] ?? []) }} categories · {{ count($snapshot['criteria'] ?? []) }} criteria</td>
                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $version->scores()->count() }}</td>
                                <td class="px-5 py-4 font-mono text-[11px] text-gray-400">{{ substr($version->content_hash, 0, 12) }}…</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-gray-500">No rubric versions yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($versions->hasPages())
                <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-800">{{ $versions->links() }}</div>
            @endif
        </div>
    </div>
@endsection
