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
