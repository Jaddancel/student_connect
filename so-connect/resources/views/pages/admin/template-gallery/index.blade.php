@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Template Gallery" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Templates by process</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Browse online forms and generated-report templates together by their business purpose.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.categories.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Manage categories</a>
                <a href="{{ route('admin.form-builder.create') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-600">New form</a>
            </div>
        </div>

        <form method="GET" class="grid gap-2 rounded-2xl border border-gray-200 bg-palette-surface p-4 dark:border-gray-800 dark:bg-white/[0.03] sm:grid-cols-4">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search template or purpose…"
                class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90" />
            <x-common.predictive-select name="category" :selected="$categoryFilter" placeholder="All processes" search-placeholder="Search process…"
                :options="$categories->map(fn($category) => ['value'=>$category->id,'label'=>$category->name,'keywords'=>$category->description])->all()" />
            <x-common.predictive-select name="type" :selected="$typeFilter" placeholder="Forms and reports" search-placeholder="Search template type…"
                :options="[['value'=>'form','label'=>'Online Forms'],['value'=>'report','label'=>'Report Templates']]" />
            <div class="flex gap-2">
                <button class="flex-1 rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white hover:bg-brand-600">Apply filters</button>
                @if ($search !== '' || $categoryFilter !== '' || $typeFilter !== '')
                    <a href="{{ route('admin.template-gallery.index') }}" class="flex items-center rounded-lg border border-gray-300 px-3 text-sm text-gray-500 dark:border-gray-700">Clear</a>
                @endif
            </div>
        </form>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            @foreach ($categories as $category)
                <a href="{{ route('admin.template-gallery.index', ['category' => $category->id, 'type' => $typeFilter ?: null]) }}"
                    @class([
                        'rounded-xl border p-4 transition hover:border-brand-400',
                        'border-brand-500 bg-brand-50/60 dark:bg-brand-500/10' => (string) $category->id === $categoryFilter,
                        'border-gray-200 dark:border-gray-800' => (string) $category->id !== $categoryFilter,
                    ])>
                    <div class="flex items-center justify-between gap-2">
                        <strong class="text-sm text-gray-800 dark:text-white/90">{{ $category->name }}</strong>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 dark:bg-gray-800">{{ $category->gallery_count }}</span>
                    </div>
                    <p class="mt-1 line-clamp-2 text-xs text-gray-500">{{ $category->description }}</p>
                </a>
            @endforeach
        </div>

        @php $groupedItems = $items->groupBy('category'); @endphp
        @forelse ($groupedItems as $categoryName => $categoryItems)
            <section class="space-y-3">
                <div class="flex items-center gap-3">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $categoryName }}</h3>
                    <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-600 dark:bg-brand-500/10 dark:text-brand-300">{{ count($categoryItems) }} template{{ count($categoryItems) === 1 ? '' : 's' }}</span>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($categoryItems as $item)
                        <article class="flex flex-col rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-bold uppercase text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                                            {{ $item['type'] === 'form' ? 'Online form' : 'Report template' }}
                                        </span>
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] text-gray-500 dark:bg-gray-800">{{ $item['category'] }}</span>
                                    </div>
                                    <h4 class="mt-2 text-base font-semibold text-gray-800 dark:text-white/90">{{ $item['name'] }}</h4>
                                </div>
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $item['active'] && $item['published'] ? 'bg-success-500' : 'bg-gray-300' }}" title="{{ $item['active'] && $item['published'] ? 'Available' : 'Draft or inactive' }}"></span>
                            </div>

                            <p class="mt-2 line-clamp-3 min-h-12 text-sm text-gray-500 dark:text-gray-400">{{ $item['description'] ?: 'No purpose description provided.' }}</p>

                            <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                <span class="rounded-lg bg-gray-50 px-2.5 py-1 text-gray-600 dark:bg-gray-900 dark:text-gray-300">{{ $item['metric'] }} {{ $item['metric_label'] }}</span>
                                <span class="rounded-lg bg-gray-50 px-2.5 py-1 text-gray-600 dark:bg-gray-900 dark:text-gray-300">{{ count($item['templates']) }} print layout{{ count($item['templates']) === 1 ? '' : 's' }}</span>
                            </div>

                            <div class="mt-3 min-h-12 space-y-1">
                                @forelse ($item['templates'] as $slot)
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">• {{ $slot['name'] }} <span class="text-gray-400">v{{ $slot['version'] }}</span></p>
                                @empty
                                    <p class="text-xs font-medium text-warning-600 dark:text-warning-400">No active printable layout</p>
                                @endforelse
                            </div>

                            <div class="mt-auto flex flex-wrap gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                                @if ($item['preview_url'])
                                    <a href="{{ $item['preview_url'] }}" target="_blank" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Preview</a>
                                @endif
                                @if ($item['use_url'])
                                    <a href="{{ $item['use_url'] }}" class="rounded-lg border border-success-300 px-3 py-1.5 text-xs font-semibold text-success-600 hover:bg-success-50 dark:border-success-500/40">{{ $item['type'] === 'form' ? 'Open form' : 'Generate' }}</a>
                                @endif
                                <a href="{{ $item['edit_url'] }}" class="rounded-lg border border-brand-300 px-3 py-1.5 text-xs font-semibold text-brand-600 hover:bg-brand-50">Edit</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-300">No templates match these filters.</p>
                <p class="mt-1 text-xs text-gray-400">Try another process, type, or search phrase.</p>
            </div>
        @endforelse
    </div>
@endsection
