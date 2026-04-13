@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Upload Forms" />

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Submit a New Form File</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Upload form templates or documents for other officers and members to access.
            </p>

            @if (session('success'))
                <div
                    class="mt-4 rounded-lg border border-success-300 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/40 dark:bg-success-500/10 dark:text-success-400">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('status'))
                <div
                    class="mt-4 rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/40 dark:bg-warning-500/10 dark:text-warning-400">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div
                    class="mt-4 rounded-lg border border-error-300 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/40 dark:bg-error-500/10 dark:text-error-400">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('upload-forms.store') }}" method="post" enctype="multipart/form-data"
                class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
                @csrf

                <div class="md:col-span-2">
                    <label for="description_text" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Description <span class="text-error-500">*</span>
                    </label>
                    <input type="text" id="description_text" name="description_text" value="{{ old('description_text') }}"
                        placeholder="e.g. Event proposal template"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                </div>

                <div>
                    <label for="form_file" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        File <span class="text-error-500">*</span>
                    </label>
                    <input type="file" id="form_file" name="form_file"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 block h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 file:mr-3 file:rounded-md file:border-0 file:bg-brand-500 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-white hover:file:bg-brand-600 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                </div>

                <div class="md:col-span-3 flex justify-end">
                    <button type="submit"
                        class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Upload Form
                    </button>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <h4 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Your Uploaded Files</h4>

            @if ($documents->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">You have not uploaded any forms yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Description
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Stored Path
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Uploaded
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($documents as $document)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $document->description_text }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $document->link }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $document->created_at ? \Illuminate\Support\Carbon::parse($document->created_at)->format('M d, Y h:i A') : 'N/A' }}
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