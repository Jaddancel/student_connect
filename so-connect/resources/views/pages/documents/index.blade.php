@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Documents" />

    <div class="space-y-6">

        {{-- Admin org filter --}}
        @if ($isAdmin && $orgNames->isNotEmpty())
            <div class="flex flex-wrap items-center gap-3">
                <form method="GET" action="{{ route('documents.index') }}" class="flex items-center gap-2">
                    <label for="org-filter" class="text-sm font-medium text-gray-600 dark:text-gray-400">Filter by organization:</label>
                    <select id="org-filter" name="organization_id" onchange="this.form.submit()"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 focus:border-brand-300 focus:ring-2 focus:ring-brand-500/10 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="0" @selected($filterOrgId === 0)>All Organizations</option>
                        @foreach ($orgNames as $org)
                            <option value="{{ $org->organization_id }}" @selected($filterOrgId === (int) $org->organization_id)>
                                {{ $org->name }}
                            </option>
                        @endforeach
                    </select>
                </form>

                @if ($filterOrgId > 0)
                    <a href="{{ route('documents.index') }}"
                        class="text-xs font-medium text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        Clear filter
                    </a>
                @endif
            </div>
        @endif

        {{-- Document list --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <div>
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Generated Documents</h3>
                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">System-generated documents from approved requests</p>
                </div>
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $documents->total() }} {{ Str::plural('document', $documents->total()) }}</span>
            </div>

            @if ($documents->isEmpty())
                <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                        <svg class="h-6 w-6 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M8.50391 4.25C8.50391 3.83579 8.83969 3.5 9.25391 3.5H15.2777C15.4766 3.5 15.6674 3.57902 15.8081 3.71967L18.2807 6.19234C18.4214 6.333 18.5004 6.52376 18.5004 6.72268V16.75C18.5004 17.1642 18.1646 17.5 17.7504 17.5H16.248V17.4993H14.748V17.5H9.25391C8.83969 17.5 8.50391 17.1642 8.50391 16.75V4.25ZM14.748 19H9.25391C8.01126 19 7.00391 17.9926 7.00391 16.75V6.49854H6.24805C5.83383 6.49854 5.49805 6.83432 5.49805 7.24854V19.75C5.49805 20.1642 5.83383 20.5 6.24805 20.5H13.998C14.4123 20.5 14.748 20.1642 14.748 19.75L14.748 19ZM7.00391 4.99854V4.25C7.00391 3.00736 8.01127 2 9.25391 2H15.2777C15.8745 2 16.4468 2.23705 16.8687 2.659L19.3414 5.13168C19.7634 5.55364 20.0004 6.12594 20.0004 6.72268V16.75C20.0004 17.9926 18.9931 19 17.7504 19H16.248L16.248 19.75C16.248 20.9926 15.2407 22 13.998 22H6.24805C5.00541 22 3.99805 20.9926 3.99805 19.75V7.24854C3.99805 6.00589 5.00541 4.99854 6.24805 4.99854H7.00391Z" fill="currentColor"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No documents found</p>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Documents appear here once a request has been approved and generated.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Document Type</th>
                                @if ($isAdmin)
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Organization</th>
                                @endif
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Generated</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($documents as $doc)
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 dark:bg-brand-500/10">
                                                <svg class="h-4 w-4 text-brand-600 dark:text-brand-400" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M8.50391 4.25C8.50391 3.83579 8.83969 3.5 9.25391 3.5H15.2777C15.4766 3.5 15.6674 3.57902 15.8081 3.71967L18.2807 6.19234C18.4214 6.333 18.5004 6.52376 18.5004 6.72268V16.75C18.5004 17.1642 18.1646 17.5 17.7504 17.5H9.25391C8.83969 17.5 8.50391 17.1642 8.50391 16.75V4.25ZM7.00391 4.25C7.00391 3.00736 8.01127 2 9.25391 2H15.2777C15.8745 2 16.4468 2.23705 16.8687 2.659L19.3414 5.13168C19.7634 5.55364 20.0004 6.12594 20.0004 6.72268V16.75C20.0004 17.9926 18.9931 19 17.7504 19H9.25391C8.01127 19 7.00391 17.9926 7.00391 16.75V4.25Z" fill="currentColor"/>
                                                </svg>
                                            </div>
                                            <span class="font-medium text-gray-800 dark:text-white/90">
                                                @if ($doc->form_route === 'workplan' && $doc->semester_name)
                                                    Workplan {{ $doc->semester_name }}
                                                @else
                                                    {{ $doc->form_name ?? 'Document' }}
                                                @endif
                                            </span>
                                        </div>
                                    </td>
                                    @if ($isAdmin)
                                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $doc->org_name }}</td>
                                    @endif
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                        @if ($doc->generated_at)
                                            <span>{{ \Illuminate\Support\Carbon::parse($doc->generated_at)->timezone('Asia/Manila')->format('M d, Y') }}</span>
                                            <span class="block text-xs text-gray-400 dark:text-gray-500">{{ \Illuminate\Support\Carbon::parse($doc->generated_at)->timezone('Asia/Manila')->format('g:i A') }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @if ($doc->document_id)
                                            <a href="{{ route('download-files.download', $doc->document_id) }}"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-600 transition-colors">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v1a2 2 0 002 2h12a2 2 0 002-2v-1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                                Download
                                            </a>
                                        @else
                                            <span class="text-xs text-gray-400">Unavailable</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($documents->hasPages())
                    <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">
                        {{ $documents->links() }}
                    </div>
                @endif
            @endif
        </div>

    </div>
@endsection
