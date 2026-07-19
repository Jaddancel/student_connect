@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Waiver Review" />

    <div class="mx-auto max-w-4xl space-y-6">
        <section class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Scanned waivers</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Submissions with a scanned waiver and the server's validation verdict.
            </p>

            @if ($rows->isEmpty())
                <p class="mt-6 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                    No scanned-waiver submissions yet.
                </p>
            @else
                <div class="mt-6 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-gray-400">
                            <tr><th class="pb-2">Form</th><th class="pb-2">Submitted</th><th class="pb-2">Verdict</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="py-3 font-medium text-gray-800 dark:text-white/90">{{ $row['form'] }}</td>
                                    <td class="py-3 text-gray-500 dark:text-gray-400">{{ $row['submitted_at']?->diffForHumans() ?? '—' }}</td>
                                    <td class="py-3">
                                        @php
                                            $badge = match ($row['verdict']) {
                                                'valid' => ['Valid', 'bg-success-50 text-success-600 dark:bg-success-500/10'],
                                                'needs_review' => ['Needs review', 'bg-warning-50 text-warning-600 dark:bg-warning-500/10'],
                                                default => ['Unvalidated', 'bg-gray-100 text-gray-500 dark:bg-gray-800'],
                                            };
                                        @endphp
                                        <span class="rounded px-2 py-0.5 text-[11px] font-semibold {{ $badge[1] }}">{{ $badge[0] }}</span>
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
