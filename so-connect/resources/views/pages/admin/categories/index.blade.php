@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Template Categories" />

    @if (session('success'))<div class="mb-4 rounded-lg bg-success-50 p-3 text-sm text-success-700">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="mb-4 rounded-lg bg-error-50 p-3 text-sm text-error-700">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="mb-4 rounded-lg bg-error-50 p-3 text-sm text-error-700">{{ $errors->first() }}</div>@endif

    <div class="space-y-5">
        <form method="POST" action="{{ route('admin.categories.store') }}" class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[.03]">
            @csrf
            <h2 class="mb-4 font-semibold text-gray-800 dark:text-white">Create category</h2>
            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                <input name="name" required placeholder="Category name" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700" />
                <input name="slug" placeholder="slug (auto-generated)" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700" />
                <select name="icon" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700"><option value="">Default icon</option>@foreach($icons as $icon)<option value="{{ $icon }}">{{ ucfirst(str_replace('-', ' ', $icon)) }}</option>@endforeach</select>
                <div class="flex gap-2"><select name="color" class="h-10 flex-1 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700">@foreach($colors as $color)<option value="{{ $color }}">{{ ucfirst($color) }}</option>@endforeach</select><input name="display_order" type="number" min="0" value="{{ ($categories->max('display_order') ?? 0) + 1 }}" title="Display order" class="h-10 w-24 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700" /></div>
                <textarea name="description" placeholder="Purpose / description" class="rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm md:col-span-2 dark:border-gray-700"></textarea>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked /> Active</label>
                <button class="h-10 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white">Add category</button>
            </div>
        </form>

        <div class="space-y-3">
            @foreach($categories as $category)
                <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[.03]">
                    <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="grid gap-3 md:grid-cols-2 lg:grid-cols-6">
                        @csrf @method('PUT')
                        <input name="name" value="{{ $category->name }}" required class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700" />
                        <input name="slug" value="{{ $category->slug }}" required class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700" />
                        <select name="icon" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700"><option value="">Default icon</option>@foreach($icons as $icon)<option value="{{ $icon }}" @selected($category->icon === $icon)>{{ ucfirst(str_replace('-', ' ', $icon)) }}</option>@endforeach</select>
                        <select name="color" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700">@foreach($colors as $color)<option value="{{ $color }}" @selected($category->color === $color)>{{ ucfirst($color) }}</option>@endforeach</select>
                        <input name="display_order" type="number" min="0" value="{{ $category->display_order }}" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700" />
                        <button class="h-10 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white">Save</button>
                        <textarea name="description" class="rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm md:col-span-2 lg:col-span-4 dark:border-gray-700">{{ $category->description }}</textarea>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($category->is_active) /> Active</label>
                        <div class="flex items-center justify-between gap-2 text-xs text-gray-500"><span>{{ $category->forms_count }} forms · {{ $category->report_templates_count }} reports</span></div>
                    </form>
                    <div class="mt-2 flex items-center justify-end gap-4">
                        <a href="{{ route('admin.template-gallery.index', ['category' => $category->id]) }}" class="text-xs font-semibold text-brand-600 hover:underline">Open gallery</a>
                        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category?')">@csrf @method('DELETE')<button class="text-xs text-error-600 disabled:opacity-40" @disabled($category->forms_count + $category->report_templates_count > 0)>Delete unused category</button></form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
