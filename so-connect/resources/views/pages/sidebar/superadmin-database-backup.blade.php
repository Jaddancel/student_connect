@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Database Backup & Restore" />

    <div class="space-y-6">
        @if (session('success'))
            <div
                class="rounded-lg border border-success-300 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/40 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div
                class="rounded-lg border border-error-300 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/40 dark:bg-error-500/10 dark:text-error-400">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Create backup --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Create Database Backup</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Generates a compressed SQL dump of the application database and stores it securely on the server.
            </p>
            <form action="{{ route('superadmin.backups.run') }}" method="post" class="mt-4">
                @csrf
                <button type="submit"
                    class="inline-flex items-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                    Create Backup Now
                </button>
            </form>
        </div>

        {{-- Available backups --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Available Backups</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ count($backups) }} backup{{ count($backups) === 1 ? '' : 's' }} stored.
            </p>

            @if (count($backups) > 0)
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                <th class="px-3 py-2 font-medium">Backup File</th>
                                <th class="px-3 py-2 font-medium">Created</th>
                                <th class="px-3 py-2 font-medium">Size</th>
                                <th class="px-3 py-2 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($backups as $backup)
                                <tr class="text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/[0.02]">
                                    <td class="px-3 py-2.5 font-medium text-gray-800 dark:text-white/90">{{ $backup['name'] }}</td>
                                    <td class="px-3 py-2.5">{{ $backup['created_at'] }}</td>
                                    <td class="px-3 py-2.5">{{ $backup['size'] }}</td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('superadmin.backups.download', ['file' => $backup['name']]) }}"
                                                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-300 dark:hover:bg-white/[0.03]">
                                                Download
                                            </a>
                                            <form action="{{ route('superadmin.backups.destroy', ['file' => $backup['name']]) }}"
                                                method="post" onsubmit="return confirm('Delete this backup permanently?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="inline-flex items-center rounded-lg border border-error-300 bg-error-50 px-3 py-1.5 text-xs font-medium text-error-700 transition hover:bg-error-100 dark:border-error-500/40 dark:bg-error-500/10 dark:text-error-400">
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
            @else
                <p class="mt-4 text-sm italic text-gray-400">No backups yet. Create one above.</p>
            @endif
        </div>

        {{-- Restore --}}
        @if (count($backups) > 0)
            <div
                class="rounded-2xl border border-warning-300 bg-warning-50 p-5 dark:border-warning-500/40 dark:bg-warning-500/10 lg:p-6">
                <h3 class="text-lg font-semibold text-warning-700 dark:text-warning-400">Restore Database</h3>
                <p class="mt-1 text-sm text-warning-700/80 dark:text-warning-400/80">
                    <strong>Warning:</strong> Restoring overwrites the current database with the contents of the selected
                    backup. This cannot be undone. Type <code class="font-mono font-semibold">RESTORE</code> to confirm.
                </p>

                <form action="{{ route('superadmin.backups.restore') }}" method="post"
                    class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2"
                    onsubmit="return confirm('Are you absolutely sure? This will overwrite the live database.');">
                    @csrf
                    <div>
                        <label for="file" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Backup to restore
                        </label>
                        <select id="file" name="file" required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90">
                            @foreach ($backups as $backup)
                                <option value="{{ $backup['name'] }}">{{ $backup['name'] }} ({{ $backup['created_at'] }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="confirmation" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Confirmation
                        </label>
                        <input id="confirmation" name="confirmation" type="text" autocomplete="off" placeholder="Type RESTORE"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90" />
                    </div>

                    <div class="lg:col-span-2">
                        <button type="submit"
                            class="inline-flex items-center rounded-lg bg-error-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-error-700">
                            Restore Database
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
@endsection
