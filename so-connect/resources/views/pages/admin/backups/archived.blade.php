@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Archived Backups" />

    <div class="mx-auto max-w-4xl space-y-6">
        <nav class="flex gap-1 rounded-xl border border-gray-200 bg-white p-1 dark:border-gray-800 dark:bg-white/[0.03]">
            <a href="{{ route('superadmin.backups.index') }}"
                class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">
                Backups
            </a>
            <a href="{{ route('superadmin.backups.archived') }}"
                class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white">
                Archived
            </a>
        </nav>

        @if (session('success'))
            <div class="rounded-xl border border-success-300 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/40 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-error-300 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/40 dark:bg-error-500/10 dark:text-error-400">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <div>
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Archived backups</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Archived backups are kept out of the active list and are ignored by automatic backup rotation.
                </p>
            </div>

            @if (empty($archived))
                <p class="mt-6 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                    No archived backups.
                </p>
            @else
                <div class="mt-6 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="pb-2">Backup</th>
                                <th class="pb-2">Size</th>
                                <th class="pb-2">Created</th>
                                <th class="pb-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($archived as $backup)
                                <tr>
                                    <td class="py-3 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $backup['name'] }}</td>
                                    <td class="py-3 text-gray-500 dark:text-gray-400">{{ number_format($backup['size'] / 1024, 1) }} KB</td>
                                    <td class="py-3 text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Carbon::createFromTimestamp($backup['last_modified'])->diffForHumans() }}</td>
                                    <td class="py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('superadmin.backups.download', ['filename' => $backup['name'], 'archived' => 1]) }}"
                                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Download</a>
                                            <form method="POST" action="{{ route('superadmin.backups.unarchive', $backup['name']) }}">
                                                @csrf
                                                <button type="submit"
                                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Unarchive</button>
                                            </form>
                                            <form method="POST" action="{{ route('superadmin.backups.destroy', ['filename' => $backup['name'], 'archived' => 1]) }}"
                                                onsubmit="return confirm('Delete this archived backup?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="Delete" aria-label="Delete archived backup"
                                                    class="rounded-lg bg-error-500 p-2 text-white hover:bg-error-600">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
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
