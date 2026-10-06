@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Database Backups" />

    <div class="mx-auto max-w-4xl space-y-6">
        <nav class="flex gap-1 rounded-xl border border-gray-200 bg-white p-1 dark:border-gray-800 dark:bg-white/[0.03]">
            <a href="{{ route('superadmin.backups.index') }}"
                class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white">
                Backups
            </a>
            <a href="{{ route('superadmin.backups.archived') }}"
                class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">
                Archived{{ $archivedCount > 0 ? ' ('.$archivedCount.')' : '' }}
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
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Database backups</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Automatic backups run every {{ $intervalHours }} hour{{ $intervalHours === 1 ? '' : 's' }}.
                        You can also back up now, download a dump, or restore the database from one.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('superadmin.backups.store') }}">
                        @csrf
                        <button type="submit"
                            class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-600">
                            Back up now
                        </button>
                    </form>
                    <button type="button" x-data @click="$dispatch('open-restore-file-modal')"
                        class="rounded-lg border border-warning-300 px-4 py-2 text-sm font-semibold text-warning-600 hover:bg-warning-50 dark:border-warning-500/40 dark:hover:bg-warning-500/10">
                        Restore from file
                    </button>
                </div>
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
                                            <form method="POST" action="{{ route('superadmin.backups.archive', $backup['name']) }}"
                                                onsubmit="return confirm('Archive this backup? It will be moved to the archived section.');">
                                                @csrf
                                                <button type="submit"
                                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Archive</button>
                                            </form>
                                            <form method="POST" action="{{ route('superadmin.backups.destroy', $backup['name']) }}"
                                                onsubmit="return confirm('Delete this backup?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="Delete" aria-label="Delete backup"
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

    <x-ui.modal @open-restore-file-modal.window="open = true" :isOpen="false" class="max-w-[480px]">
        <form method="POST" action="{{ route('superadmin.backups.restore-upload') }}" enctype="multipart/form-data"
            x-data="{ submitting: false }" @submit="submitting = true" class="p-6">
            @csrf
            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-warning-50 dark:bg-warning-500/10">
                <svg class="h-6 w-6 text-warning-600 dark:text-warning-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM10.29 3.86l-8.18 14.18A1.5 1.5 0 003.42 20.5h17.16a1.5 1.5 0 001.31-2.46L13.71 3.86a1.5 1.5 0 00-2.42 0z" />
                </svg>
            </div>

            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Restore from file</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                The current database will be replaced; a safety backup is taken first.
            </p>

            <label for="backup_file" class="mt-5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Backup file <span class="font-normal text-gray-400">(.zip downloaded from this page, max 100 MB)</span>
            </label>
            <input id="backup_file" type="file" name="backup_file" accept=".zip,application/zip" required
                class="mt-2 block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200 dark:text-gray-300 dark:file:bg-gray-800 dark:file:text-gray-300" />

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="open = false" :disabled="submitting"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">
                    Cancel
                </button>
                <button type="submit" :disabled="submitting"
                    class="inline-flex items-center gap-2 rounded-lg bg-warning-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-warning-600 disabled:opacity-50">
                    <svg x-show="submitting" x-cloak class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    <span x-text="submitting ? 'Restoring…' : 'Restore'">Restore</span>
                </button>
            </div>
        </form>
    </x-ui.modal>
@endsection
