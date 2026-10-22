@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Forms" />

    <div x-data="formDirectorySearch({ forms: @js($forms) })"
        class="space-y-6">

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Forms</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Open a blank printable PDF of any form — search by name or purpose.</p>
            </div>
            <div class="flex w-full gap-2 sm:w-auto"><x-common.predictive-select x-model="category" placeholder="All categories" search-placeholder="Search categories…" :options="$categories->map(fn($category) => ['value'=>$category->id,'label'=>$category->name])->all()" class="w-48" /><input type="search" x-model="q" placeholder="Search by name or purpose…" class="h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90 sm:w-72" /></div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <template x-for="form in filtered" :key="form.url">
                <a :href="form.url" target="_blank" rel="noopener"
                    class="block rounded-2xl border border-gray-200 bg-palette-surface p-5 transition hover:border-brand-400 hover:shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90" x-text="form.name"></h3>
                    <span class="mt-2 inline-block rounded-full bg-brand-50 px-2 py-1 text-[10px] font-medium text-brand-600" x-text="form.category"></span>
                    <p class="mt-1 line-clamp-3 text-xs text-gray-500 dark:text-gray-400" x-text="form.purpose || 'No description provided.'"></p>
                    <span class="mt-3 inline-block text-xs font-medium text-brand-500" x-text="'Blank printable PDF · ' + form.template_name"></span>
                </a>
            </template>
        </div>

        <template x-if="filtered.length === 0">
            <div class="rounded-2xl border border-dashed border-gray-300 py-16 text-center text-sm text-gray-400 dark:border-gray-700">
                No forms match your search.
            </div>
        </template>
    </div>
@endsection
