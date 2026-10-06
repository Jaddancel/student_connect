@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="ID Templates" />

    <div class="space-y-6">

        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">ID recognition templates</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Draw named zones on a reference ID; the active default template drives the ID scanner.
                </p>
            </div>
            <a href="{{ route('superadmin.id-templates.create') }}"
                class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                New template
            </a>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            @if ($templates->isEmpty())
                <div class="p-10 text-center text-sm text-gray-400 dark:text-gray-500">
                    No ID templates yet. Create one to configure the scanner.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-6 py-4 font-semibold text-gray-700 dark:text-gray-300">Template</th>
                                <th class="px-6 py-4 font-semibold text-gray-700 dark:text-gray-300">Zones</th>
                                <th class="px-6 py-4 font-semibold text-gray-700 dark:text-gray-300">Status</th>
                                <th class="px-6 py-4 font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($templates as $template)
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-12 w-20 shrink-0 overflow-hidden rounded-md border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/40">
                                                @if ($template->image_path)
                                                    <img src="{{ asset('storage/' . $template->image_path) }}"
                                                        alt="{{ $template->name }}" class="h-full w-full object-cover" />
                                                @endif
                                            </div>
                                            <span class="font-medium text-gray-800 dark:text-white/90">{{ $template->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ count($template->zones ?? []) }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap gap-1.5">
                                            @if ($template->is_default)
                                                <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">Default</span>
                                            @endif
                                            @if ($template->is_active)
                                                <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">Active</span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">Inactive</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-4">
                                            <a href="{{ route('superadmin.id-templates.edit', $template) }}"
                                                class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">Edit</a>
                                            <form method="POST" action="{{ route('superadmin.id-templates.destroy', $template) }}"
                                                onsubmit="return confirm('Delete this ID template? This cannot be undone.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="text-xs font-medium text-error-600 hover:underline dark:text-error-400">Delete</button>
                                            </form>
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
