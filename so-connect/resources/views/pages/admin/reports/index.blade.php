@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Reports" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Registered organizations report --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Registered Organizations</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                A PDF listing every registered organization, its type, and its officer count.
            </p>

            <a href="{{ route('admin.reports.organizations') }}"
                class="mt-5 inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                Download PDF
            </a>
        </div>

        {{-- Officers per organization report --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Organization Officers</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                A PDF listing the officers of each organization. Optionally limit it to one organization.
            </p>

            <form method="GET" action="{{ route('admin.reports.officers') }}" class="mt-5 space-y-3">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Organization</label>
                    <select name="organization_id"
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90">
                        <option value="">All organizations</option>
                        @foreach ($organizations as $org)
                            <option value="{{ $org['organization_id'] }}">{{ $org['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                    Download PDF
                </button>
            </form>
        </div>
    </div>
@endsection
