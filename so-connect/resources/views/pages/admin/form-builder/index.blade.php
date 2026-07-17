@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Form Builder" />

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @if (session('toast_error'))
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                {{ session('toast_error') }}
            </div>
        @endif

        {{-- System Functions: fixed slots the admin fills with a form. --}}
        <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">System Functions</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Fixed flows (sign-up, events, workplans, membership). Each is disabled until a form is created for it.</p>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-palette-surface dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-400">
                    <tr>
                        <th class="px-5 py-3">Function</th>
                        <th class="px-5 py-3">Form</th>
                        <th class="px-5 py-3">Route</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($slots as $slot)
                        <tr class="text-gray-700 dark:text-gray-300">
                            <td class="px-5 py-3 font-medium text-gray-800 dark:text-white/90">{{ $slot['label'] }}</td>
                            <td class="px-5 py-3">{{ $slot['form']->name ?? '—' }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-gray-500">{{ $slot['form']->route_name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                @if ($slot['form'] && $slot['form']->is_published)
                                    <span class="rounded-full bg-success-50 px-2.5 py-1 text-xs font-medium text-success-600 dark:bg-success-500/15">Active</span>
                                @elseif ($slot['form'])
                                    <span class="rounded-full bg-warning-50 px-2.5 py-1 text-xs font-medium text-warning-600 dark:bg-warning-500/15 dark:text-orange-400">Unpublished</span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500 dark:bg-white/5">Disabled — no form</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-2">
                                    @if ($slot['form'])
                                        <a href="{{ route('admin.form-builder.preview', $slot['form']) }}" target="_blank"
                                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Preview</a>
                                        <a href="{{ route('admin.form-builder.edit', $slot['form']) }}"
                                            class="rounded-lg border border-brand-300 px-3 py-1.5 text-xs font-medium text-brand-600 transition hover:bg-brand-50">Edit</a>
                                        <form action="{{ route('admin.form-builder.destroy', $slot['form']) }}" method="POST"
                                            onsubmit="return confirm('Delete this form? The function will be disabled until you create a new one.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-error-300 px-3 py-1.5 text-xs font-medium text-error-600 transition hover:bg-error-50">Delete</button>
                                        </form>
                                    @else
                                        <a href="{{ route('admin.form-builder.create', ['function' => $slot['key']]) }}"
                                            class="rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-brand-600">Create form ▸</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Forms</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Design fill-in forms and their printable PDF layout.</p>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-palette-surface dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-400">
                    <tr>
                        <th class="px-5 py-3">Name</th>
                        <th class="px-5 py-3">Route</th>
                        <th class="px-5 py-3">Fields</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($forms as $form)
                        <tr class="text-gray-700 dark:text-gray-300">
                            <td class="px-5 py-3 font-medium text-gray-800 dark:text-white/90">{{ $form->name }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-gray-500">{{ $form->route_name }}</td>
                            <td class="px-5 py-3">{{ $form->fields_count }}</td>
                            <td class="px-5 py-3">
                                @if ($form->is_published)
                                    <span class="rounded-full bg-success-50 px-2.5 py-1 text-xs font-medium text-success-600 dark:bg-success-500/15">Published</span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500 dark:bg-white/5">Draft</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.form-builder.preview', $form) }}" target="_blank"
                                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Preview</a>
                                    <a href="{{ route('admin.form-builder.edit', $form) }}"
                                        class="rounded-lg border border-brand-300 px-3 py-1.5 text-xs font-medium text-brand-600 transition hover:bg-brand-50">Edit</a>
                                    <form action="{{ route('admin.form-builder.destroy', $form) }}" method="POST"
                                        onsubmit="return confirm('Delete this form and all its fields?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="rounded-lg border border-error-300 px-3 py-1.5 text-xs font-medium text-error-600 transition hover:bg-error-50">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-gray-400">No forms yet. Create your first form.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-common.fab :href="route('admin.form-builder.create')" label="New form" />
@endsection
