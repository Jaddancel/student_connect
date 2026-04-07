@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Membership Registration" />

    <div
        class="rounded-2xl border border-gray-200 bg-white px-5 py-7 dark:border-gray-800 dark:bg-white/[0.03] xl:px-10 xl:py-12">
        <div class="mx-auto w-full max-w-3xl space-y-6">
            <div>
                <h3 class="text-xl font-semibold text-gray-800 dark:text-white/90">Register For Membership</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Submit a membership request to your target organization. Organizations with type 6 are excluded.
                </p>
            </div>

            @if (session('success'))
                <div
                    class="rounded-lg border border-success-300 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/40 dark:bg-success-500/10 dark:text-success-400">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('status'))
                <div
                    class="rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/40 dark:bg-warning-500/10 dark:text-warning-400">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div
                    class="rounded-lg border border-error-300 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/40 dark:bg-error-500/10 dark:text-error-400">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('register.store') }}" method="post" class="space-y-5">
                @csrf

                <div>
                    <label for="organization_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Target Organization <span class="text-error-500">*</span>
                    </label>
                    <select id="organization_id" name="organization_id" required
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="">Select an organization</option>
                        @foreach ($organizations as $organization)
                            <option value="{{ $organization->organization_id }}" @selected((int) old('organization_id') === (int) $organization->organization_id)>
                                {{ $organization->organization_name ?? 'Unknown Organization' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                        class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 rounded-lg px-4 py-3 text-sm font-medium text-white transition">
                        Submit Membership Request
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
