@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Request Types" />

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Create Request Type</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Request types power the new request and approval workflow.
                    </p>
                </div>
            </div>

            @if (session('success'))
                <div
                    class="mb-4 rounded-lg border border-success-300 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/40 dark:bg-success-500/10 dark:text-success-400">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div
                    class="mb-4 rounded-lg border border-error-300 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/40 dark:bg-error-500/10 dark:text-error-400">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('superadmin.request-types.store') }}" method="post"
                class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @csrf

                <div>
                    <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Name
                        <span class="text-error-500">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90"
                        placeholder="e.g. Membership Request" />
                </div>

                <div>
                    <label for="category" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Category
                        <span class="text-error-500">*</span></label>
                    <select id="category" name="category"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                        <option value="">Select category</option>
                        @foreach ($categoryOptions as $categoryValue => $categoryLabel)
                            <option value="{{ $categoryValue }}" @selected(old('category') === $categoryValue)>{{ $categoryLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="system_key" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">System
                        Key</label>
                    <input type="text" id="system_key" name="system_key" value="{{ old('system_key') }}"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90"
                        placeholder="e.g. membership" />
                </div>

                <div class="flex items-end gap-3">
                    <input type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', true))
                        class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700" />
                    <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300">Active</label>
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit"
                        class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">Create
                        Request Type</button>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h4 class="text-base font-semibold text-gray-800 dark:text-white/90">Existing Request Types</h4>
                <span
                    class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $requestTypes->count() }}
                    types</span>
            </div>

            @if ($requestTypes->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No request types have been created yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Name</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Category</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    System Key</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Usage</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requestTypes as $requestType)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ $requestType->name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ \App\Models\RequestType::categoryLabelForContext($requestType->category, 'requests') }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $requestType->system_key ?: 'Custom' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $requestType->forms_count }} forms / {{ $requestType->requests_count }}
                                        requests</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $requestType->is_active ? 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-400' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                                            {{ $requestType->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
