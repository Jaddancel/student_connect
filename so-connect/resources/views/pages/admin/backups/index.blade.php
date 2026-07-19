@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Database Backups" />

    <div class="mx-auto max-w-4xl space-y-6">
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
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Database backups</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Automatic backups run every {{ $intervalHours }} hour{{ $intervalHours === 1 ? '' : 's' }}.
                        You can also back up now, download a dump, or restore the database from one.
                    </p>
                </div>
                <form method="POST" action="{{ route('superadmin.backups.store') }}">
                    @csrf
                    <button type="submit"
                        class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-600">
                        Back up now
                    </button>
                </form>
            </div>

            @if (empty($backups))
                <p class="mt-6 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                    No backups yet.
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
                            @foreach ($backups as $backup)
                                <tr>
                                    <td class="py-3 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $backup['name'] }}</td>
                                    <td class="py-3 text-gray-500 dark:text-gray-400">{{ number_format($backup['size'] / 1024, 1) }} KB</td>
                                    <td class="py-3 text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Carbon::createFromTimestamp($backup['last_modified'])->diffForHumans() }}</td>
                                    <td class="py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('superadmin.backups.download', $backup['name']) }}"
                                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Download</a>
                                            <form method="POST" action="{{ route('superadmin.backups.restore', $backup['name']) }}"
                                                onsubmit="return confirm('Restore the database from this backup? The current database will be replaced (a safety backup is taken first).');">
                                                @csrf
                                                <button type="submit"
                                                    class="rounded-lg border border-warning-300 px-3 py-1.5 text-xs font-medium text-warning-600 hover:bg-warning-50 dark:border-warning-500/40">Restore</button>
                                            </form>
                                            <form method="POST" action="{{ route('superadmin.backups.destroy', $backup['name']) }}"
                                                onsubmit="return confirm('Delete this backup?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="rounded-lg bg-error-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-error-600">Delete</button>
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
