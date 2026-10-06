@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Backups" />

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
        @if (session('restore_warnings'))
            <div class="rounded-xl border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/40 dark:bg-warning-500/10 dark:text-orange-400">
                <p class="font-medium">Some items could not be fully restored:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach (session('restore_warnings') as $warning)<li>{{ $warning }}</li>@endforeach
                </ul>
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
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Backups</h2>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('superadmin.backups.store') }}" class="relative"
                        x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                        @csrf
                        <button type="button" @click="open = ! open" :aria-expanded="open" aria-haspopup="menu"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-600">
                            Back up now
                            <svg class="h-4 w-4 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" x-cloak x-transition.origin.top.left role="menu"
                            class="absolute left-0 z-30 mt-2 w-72 rounded-xl border border-gray-200 bg-white p-1.5 shadow-theme-lg dark:border-gray-700 dark:bg-gray-900">
                            <button type="submit" name="type" value="{{ \App\Services\BackupService::TYPE_DATABASE }}" role="menuitem"
                                class="block w-full rounded-lg px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-white/5">
                                <span class="block text-sm font-medium text-gray-800 dark:text-white/90">Database backup</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">The entire database.</span>
                            </button>
                            <button type="submit" name="type" value="{{ \App\Services\BackupService::TYPE_CONFIGURATION }}" role="menuitem"
                                class="block w-full rounded-lg px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-white/5">
                                <span class="block text-sm font-medium text-gray-800 dark:text-white/90">Configuration backup</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">Settings, forms, reports, printed templates and tally configurations.</span>
                            </button>
                        </div>
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
                @php($backupNames = array_column($backups, 'name'))
                <div class="mt-6" x-data="{ all: @js($backupNames), selected: [] }" :class="selected.length > 0 && 'pb-20'">
                    <form method="POST" action="{{ route('superadmin.backups.bulk') }}" x-show="selected.length > 0" x-cloak
                        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4"
                        x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-4"
                        class="fixed inset-x-0 bottom-6 z-40 mx-auto flex w-[calc(100%-2rem)] max-w-xl flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-theme-lg dark:border-gray-700 dark:bg-gray-900">
                        @csrf
                        <template x-for="name in selected" :key="name">
                            <input type="hidden" name="filenames[]" :value="name">
                        </template>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            <span x-text="selected.length"></span> selected
                            <button type="button" @click="selected = []"
                                class="ml-2 text-xs font-normal text-gray-500 underline hover:text-gray-700 dark:text-gray-400">Clear</button>
                        </p>
                        <div class="flex items-center gap-2">
                            <button type="submit" name="action" value="archive"
                                @click="if (! confirm(`Archive ${selected.length} backup(s)? They will be moved to the archived section.`)) $event.preventDefault()"
                                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-300">Archive selected</button>
                            <button type="submit" name="action" value="delete"
                                @click="if (! confirm(`Delete ${selected.length} backup(s)? This cannot be undone.`)) $event.preventDefault()"
                                class="rounded-lg bg-error-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-error-600">Delete selected</button>
                        </div>
                    </form>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="w-8 pb-2">
                                    <input type="checkbox" aria-label="Select all backups"
                                        class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700"
                                        :checked="selected.length === all.length"
                                        :indeterminate="selected.length > 0 && selected.length < all.length"
                                        @change="selected = $event.target.checked ? [...all] : []">
                                </th>
                                <th class="pb-2">Backup</th>
                                <th class="pb-2">Type</th>
                                <th class="pb-2">Size</th>
                                <th class="pb-2">Created</th>
                                <th class="pb-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($backups as $backup)
                                <tr :class="selected.includes(@js($backup['name'])) && 'bg-brand-50/50 dark:bg-brand-500/5'">
                                    <td class="py-3">
                                        <input type="checkbox" value="{{ $backup['name'] }}" x-model="selected"
                                            aria-label="Select {{ $backup['name'] }}"
                                            class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700">
                                    </td>
                                    <td class="py-3 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $backup['name'] }}</td>
                                    <td class="py-3"><x-admin.backup-type-badge :type="$backup['type']" /></td>
                                    <td class="py-3 text-gray-500 dark:text-gray-400">{{ number_format($backup['size'] / 1024, 1) }} KB</td>
                                    <td class="py-3 text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Carbon::createFromTimestamp($backup['last_modified'])->diffForHumans() }}</td>
                                    <td class="py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('superadmin.backups.download', $backup['name']) }}"
                                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Download</a>
                                            <form method="POST" action="{{ route('superadmin.backups.restore', $backup['name']) }}"
                                                onsubmit="return confirm(@js($backup['type'] === \App\Services\BackupService::TYPE_CONFIGURATION
                                                    ? 'Restore the configuration from this backup? Matching settings, forms, reports, printed templates and tally configurations will be updated and missing ones added; nothing is deleted (a safety configuration backup is taken first).'
                                                    : 'Restore the database from this backup? The current database will be replaced (a safety backup is taken first).'));">
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
            <p class="mt-2 text-xs text-gray-400">
                Configuration backups are detected automatically and merged in instead: matching items are updated, missing ones added, and nothing is deleted.
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
