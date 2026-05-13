@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Manage Document Forms" />

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Create Form From DOCX Template</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Upload a DOCX template with placeholders in the {{ '{' }}{{ '{' }}field_name{{ '}' }}{{ '}' }} format.
            </p>

            @if (session('success'))
                <div
                    class="mt-4 rounded-lg border border-success-300 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/40 dark:bg-success-500/10 dark:text-success-400">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div
                    class="mt-4 rounded-lg border border-error-300 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/40 dark:bg-error-500/10 dark:text-error-400">
                    {{ $errors->first() }}
                </div>
            @endif

            @if ($organizations->isEmpty())
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    You do not currently manage any organizations for document forms.
                </p>
            @else
                <form action="{{ route('forms.manage.store') }}" method="post" enctype="multipart/form-data"
                    class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    @csrf

                    <div>
                        <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Form Name <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}"
                            placeholder="e.g. Event Approval Document"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    <div>
                        <label for="organization_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Organization <span class="text-error-500">*</span>
                        </label>
                        <select id="organization_id" name="organization_id"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                            <option value="">Select organization</option>
                            @foreach ($organizations as $organization)
                                <option value="{{ $organization->organization_id }}" @selected((int) old('organization_id') === (int) $organization->organization_id)>
                                    {{ $organization->organization_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="sidebar_group" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Sidebar Category <span class="text-error-500">*</span>
                        </label>
                        <select id="sidebar_group" name="sidebar_group"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                            @foreach ($sidebarGroups as $groupValue => $groupLabel)
                                <option value="{{ $groupValue }}" @selected(old('sidebar_group', 'president') === $groupValue)>
                                    {{ ucfirst($groupLabel) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label for="description_text" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Description
                        </label>
                        <textarea id="description_text" name="description_text" rows="3"
                            placeholder="Optional notes for officers"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ old('description_text') }}</textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label for="template_file" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            DOCX Template <span class="text-error-500">*</span>
                        </label>
                        <input type="file" id="template_file" name="template_file" accept=".docx"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 block h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 file:mr-3 file:rounded-md file:border-0 file:bg-brand-500 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-white hover:file:bg-brand-600 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    <div class="md:col-span-2 flex items-center gap-3">
                        <input type="checkbox" id="is_published" name="is_published" value="1" @checked(old('is_published'))
                            class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700" />
                        <label for="is_published" class="text-sm text-gray-700 dark:text-gray-300">
                            Publish immediately for officer requests
                        </label>
                    </div>

                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit"
                            class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                            Save Template
                        </button>
                    </div>
                </form>
            @endif
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h4 class="text-base font-semibold text-gray-800 dark:text-white/90">Existing Document Forms</h4>
                <span
                    class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    {{ $forms->count() }} forms
                </span>
            </div>

            @if ($forms->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No document forms have been created yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Form
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Organization / Sidebar
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Fields / Templates
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Published
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($forms as $form)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <p class="font-medium">{{ $form->name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $form->description_text ?: 'No description provided.' }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <p>#{{ (int) ($form->organization_id ?? 0) }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ ucfirst((string) ($form->sidebar_group ?? 'president')) }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $form->fields->count() }} fields / {{ $form->templates->count() }} templates
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $form->is_published ? 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-400' : 'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400' }}">
                                            {{ $form->is_published ? 'Published' : 'Draft' }}
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