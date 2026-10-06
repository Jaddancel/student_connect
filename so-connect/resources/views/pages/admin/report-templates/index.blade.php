@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Report Templates" />

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Report Templates</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Define report data with tokens, lay it out in a printed template, and generate it from the Reports page.</p>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-palette-surface dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-400">
                    <tr>
                        <th class="px-5 py-3">Name</th>
                        <th class="px-5 py-3">Available to</th>
                        <th class="px-5 py-3">Tokens</th>
                        <th class="px-5 py-3">Printed templates</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($reports as $report)
                        <tr class="text-gray-700 dark:text-gray-300">
                            <td class="px-5 py-3">
                                <p class="font-medium text-gray-800 dark:text-white/90">{{ $report->name }}</p>
                                @if ($report->description)
                                    <p class="text-xs text-gray-400">{{ Str::limit($report->description, 90) }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3">{{ \App\Models\ReportTemplate::audiences()[$report->audience] ?? $report->audience }}</td>
                            <td class="px-5 py-3">{{ count($report->tokens()) }}</td>
                            <td class="px-5 py-3">{{ $report->slots_count }}</td>
                            <td class="px-5 py-3">
                                @if ($report->is_active)
                                    <span class="rounded-full bg-success-50 px-2.5 py-1 text-xs font-medium text-success-600 dark:bg-success-500/15">Active</span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500 dark:bg-white/5">Inactive</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-2">
                                    @if ($report->is_active)
                                        <a href="{{ route('reports.index') }}"
                                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Generate</a>
                                    @endif
                                    <a href="{{ route('admin.report-templates.edit', $report) }}"
                                        class="rounded-lg border border-brand-300 px-3 py-1.5 text-xs font-medium text-brand-600 transition hover:bg-brand-50">Edit</a>
                                    <form action="{{ route('admin.report-templates.destroy', $report) }}" method="POST"
                                        onsubmit="return confirm('Delete this report template and its printed templates?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="rounded-lg border border-error-300 px-3 py-1.5 text-xs font-medium text-error-600 transition hover:bg-error-50">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-gray-400">No report templates yet. Create your first one.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-common.fab :href="route('admin.report-templates.create')" label="New report template" />
@endsection
