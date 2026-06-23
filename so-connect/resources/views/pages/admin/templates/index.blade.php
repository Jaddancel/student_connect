@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Template Manager" />

    <div class="space-y-6">

        {{-- Flash --}}
        @if(session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- Actions --}}
        <div class="flex items-center gap-3 justify-end">
            <a href="{{ route('admin.form-wizard.start') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Create New Form
            </a>
            <a href="{{ route('admin.templates.field-reference') }}"
               class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                </svg>
                Field Reference
            </a>
        </div>

        {{-- Forms Table --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            @if($forms->isEmpty())
                <div class="p-10 text-center text-sm text-gray-400 dark:text-gray-500">
                    No fixed form pages found. Run the FormPageSeeder to populate them.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-6 py-4 font-semibold text-gray-700 dark:text-gray-300">Form</th>
                                <th class="px-6 py-4 font-semibold text-gray-700 dark:text-gray-300">Template</th>
                                <th class="px-6 py-4 font-semibold text-gray-700 dark:text-gray-300">Version</th>
                                <th class="px-6 py-4 font-semibold text-gray-700 dark:text-gray-300">Status</th>
                                <th class="px-6 py-4 font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($forms as $form)
                                @php $activeTemplate = $activeTemplates[$form->id] ?? null; @endphp
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-gray-800 dark:text-white/90">{{ $form->name }}</p>
                                        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">/forms/{{ $form->route_name }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($activeTemplate)
                                            <span class="text-gray-700 dark:text-gray-300">{{ $activeTemplate->template_name }}</span>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500 italic">No template bound</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($activeTemplate)
                                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-500/15 dark:text-blue-400">
                                                v{{ $activeTemplate->version }}
                                            </span>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($activeTemplate)
                                            <span class="inline-flex items-center rounded-full bg-success-100 px-2.5 py-1 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">
                                                Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                                No Template
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('admin.templates.upload', $form) }}"
                                               class="inline-flex items-center rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-brand-600">
                                                {{ $activeTemplate ? 'Replace' : 'Upload Template' }}
                                            </a>

                                            @if($activeTemplate)
                                                <form method="POST" action="{{ route('admin.templates.destroy', $activeTemplate) }}"
                                                      onsubmit="return confirm('Remove this template? The form will be hidden from the sidebar.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="inline-flex items-center rounded-lg border border-error-300 px-3 py-1.5 text-xs font-medium text-error-600 transition hover:bg-error-50 dark:border-error-500/40 dark:text-error-400 dark:hover:bg-error-500/10">
                                                        Remove
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
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
