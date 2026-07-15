@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Scoring Rules" />

    <div class="space-y-6" x-data="{ addOpen: false, editKey: null }">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Scoring System Triggers</h3>
                    <p class="mt-1 max-w-2xl text-xs text-gray-500 dark:text-gray-400">
                        Each criterion of the organization scoring system is given a block-built trigger that
                        tallies its instances automatically from approved form submissions and event plans.
                        A criterion without a trigger is not tallied — it scores 0 until you build one. Changes
                        here are recorded in the administrator action logs.
                    </p>
                </div>
                <button type="button" @click="addOpen = !addOpen"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add criterion
                </button>
            </div>

            {{-- Add criterion --}}
            <form x-show="addOpen" x-cloak method="POST" action="{{ route('admin.scoring.criteria.store') }}"
                class="mt-4 grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-4 sm:grid-cols-2 lg:grid-cols-4 dark:border-gray-700">
                @csrf
                <div class="lg:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Criterion label</label>
                    <input type="text" name="label" required maxlength="255" placeholder="e.g. Community Outreach Events"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Category</label>
                    <select name="category_key" required
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        @foreach ($categories as $catKey => $catMeta)
                            <option value="{{ $catKey }}">{{ $catMeta['label'] }} (cap {{ $catMeta['cap'] }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Points / instance</label>
                        <input type="number" name="weight" required min="1" max="500" value="5"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <button type="submit"
                        class="h-10 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white transition hover:bg-brand-600">
                        Save
                    </button>
                </div>
            </form>

            @if ($errors->any())
                <p class="mt-3 rounded-lg bg-error-50 px-3 py-2 text-xs font-medium text-error-600 dark:bg-error-500/10">
                    {{ $errors->first() }}
                </p>
            @endif
        </div>

        @foreach ($categories as $catKey => $catMeta)
            @php $criteria = $criteriaByCategory->get($catKey, collect()); @endphp
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $catMeta['label'] }}</h3>
                    <span class="text-xs text-gray-400 dark:text-gray-500">capped at {{ $catMeta['cap'] }} points</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($criteria as $criterion)
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-3 text-gray-700 dark:text-gray-300">
                                        {{ $criterion->label }}
                                        @unless ($criterion->is_system)
                                            <span class="ml-1 rounded-full bg-palette-lime-pale px-2 py-0.5 text-[10px] font-semibold text-gray-700 dark:bg-palette-lime/10 dark:text-palette-lime">custom</span>
                                        @endunless
                                    </td>
                                    <td class="px-4 py-3 text-center text-xs text-gray-500 dark:text-gray-400">{{ $criterion->weight }} pt/instance</td>
                                    <td class="px-4 py-3 text-center">
                                        @if ($criterion->rule)
                                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $criterion->rule->enabled ? 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' : 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400' }}">
                                                {{ $criterion->rule->enabled ? 'Trigger active' : 'Trigger disabled' }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500">No trigger — scores 0</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('admin.scoring.rules.edit', $criterion) }}"
                                                class="rounded-lg border border-brand-300 px-3 py-1.5 text-xs font-medium text-brand-600 transition hover:bg-brand-50 dark:border-brand-700 dark:text-brand-400 dark:hover:bg-brand-900/20">
                                                {{ $criterion->rule ? 'Edit trigger' : 'Add trigger' }}
                                            </a>
                                            @if ($criterion->rule)
                                                <form method="POST" action="{{ route('admin.scoring.rules.toggle', $criterion) }}">
                                                    @csrf @method('PATCH')
                                                    <button type="submit"
                                                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                                                        {{ $criterion->rule->enabled ? 'Disable' : 'Enable' }}
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.scoring.rules.destroy', $criterion) }}"
                                                    onsubmit="return confirm('Remove this trigger? The criterion will not be tallied until you add a new one.');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit"
                                                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                                                        Remove trigger
                                                    </button>
                                                </form>
                                            @endif
                                            @unless ($criterion->is_system)
                                                <form method="POST" action="{{ route('admin.scoring.criteria.destroy', $criterion) }}"
                                                    onsubmit="return confirm('Delete this custom criterion (and its trigger)?');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit"
                                                        class="rounded-lg border border-error-300 px-3 py-1.5 text-xs font-medium text-error-600 transition hover:bg-error-50 dark:border-error-500/40 dark:hover:bg-error-500/10">
                                                        Delete
                                                    </button>
                                                </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-6 py-6 text-center text-xs text-gray-400 dark:text-gray-500">No criteria in this category.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
@endsection
