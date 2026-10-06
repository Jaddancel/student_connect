@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Superadmin Data Sync" />

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

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Export User and Profile Data</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Export all users and profiles into a JSON file.
            </p>
            <div class="mt-4">
                <a href="{{ route('superadmin.data-sync.export') }}"
                    class="inline-flex items-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                    Download JSON Export
                </a>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Import From JSON File</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Upload a JSON file generated from this system or a compatible payload.
            </p>
            <form action="{{ route('superadmin.data-sync.import-file') }}" method="post" enctype="multipart/form-data"
                class="mt-4 space-y-4">
                @csrf
                <input type="file" name="import_file" accept="application/json"
                    class="dark:bg-dark-900 block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90" />
                <button type="submit"
                    class="inline-flex items-center rounded-lg bg-success-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-success-700">
                    Import JSON File
                </button>
            </form>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Import From Remote API</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Fetch JSON payload from a remote API endpoint using an API key.
            </p>
            <form action="{{ route('superadmin.data-sync.import-api') }}" method="post"
                class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                @csrf
                <div class="lg:col-span-2">
                    <label for="api_endpoint" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        API Endpoint
                    </label>
                    <input id="api_endpoint" name="api_endpoint" type="url" value="{{ old('api_endpoint') }}"
                        placeholder="https://example.com/api/superadmin/data/export"
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90" />
                </div>

                <div class="lg:col-span-2">
                    <label for="api_key" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        API Key
                    </label>
                    <input id="api_key" name="api_key" type="text" value="{{ old('api_key') }}" placeholder="Enter API key"
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90" />
                </div>

                <div>
                    <button type="submit"
                        class="inline-flex items-center rounded-lg bg-warning-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-warning-700">
                        Import From API
                    </button>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Internal API Integration</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Use the endpoints below for machine-to-machine data sync with X-SC-API-KEY.
            </p>

            <div class="mt-4 space-y-2 text-sm text-gray-700 dark:text-gray-300">
                <p><span class="font-semibold">Export:</span> GET /api/superadmin/data/export</p>
                <p><span class="font-semibold">Import:</span> POST /api/superadmin/data/import</p>
                <p><span class="font-semibold">API Key:</span>
                    {{ $apiKeyConfigured ? 'Configured in environment.' : 'Missing. Set SUPERADMIN_DATA_SYNC_KEY in .env.' }}
                </p>
            </div>
        </div>
    </div>
@endsection