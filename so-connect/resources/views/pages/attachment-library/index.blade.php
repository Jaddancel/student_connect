@extends('layouts.app')

@php
    use Illuminate\Support\Facades\Storage;
    $disk = Storage::disk((string) config('documents.disk', 'public'));
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Attachment Library" />

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">{{ session('success') }}</div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Reusable accomplishment evidence</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Search, tag, preview, and reuse documentation from your organization’s previous reports.</p>
                </div>
                <a href="{{ url('/forms/accomplishment-report') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-600">Create accomplishment report</a>
            </div>

            <form method="GET" class="mt-5 flex flex-col gap-3 sm:flex-row">
                <input type="search" name="search" value="{{ $search }}" placeholder="Search activity, filename, or tag…"
                    class="h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90" />
                <select name="tag" class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="">All tags</option>
                    @foreach ($tags as $availableTag)
                        <option value="{{ $availableTag }}" @selected($tag === $availableTag)>{{ $availableTag }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg border border-brand-300 px-4 py-2 text-sm font-semibold text-brand-600 hover:bg-brand-50 dark:border-brand-500/40 dark:text-brand-300">Search</button>
            </form>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($media as $item)
                <article class="overflow-hidden rounded-2xl border border-gray-200 bg-palette-surface dark:border-gray-800 dark:bg-white/[0.03]">
                    <button type="button" x-data @click="$store.lightbox.show(@js($disk->url($item->file_path)), @js($item->activity_title))" class="block w-full cursor-zoom-in bg-gray-100 dark:bg-gray-900">
                        <img src="{{ $disk->url($item->file_path) }}" alt="{{ $item->activity_title }}" class="h-52 w-full object-cover" />
                    </button>
                    <div class="p-4">
                        <p class="text-xs text-gray-400">{{ $item->organization?->detail?->name ?? 'Unknown Organization' }} · {{ optional($item->submitted_at)->format('M j, Y') ?: 'No date' }}</p>
                        <h3 class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90">{{ $item->activity_title ?: $item->original_name ?: basename($item->file_path) }}</h3>
                        <div class="mt-2 flex flex-wrap gap-1">
                            @forelse ((array) $item->tags as $itemTag)
                                <a href="{{ route('attachment-library.index', ['tag' => $itemTag]) }}" class="rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-medium text-brand-600 dark:bg-brand-500/10 dark:text-brand-300">#{{ $itemTag }}</a>
                            @empty
                                <span class="text-xs text-gray-400">No tags yet</span>
                            @endforelse
                        </div>

                        <form method="POST" action="{{ route('attachment-library.update', $item) }}" class="mt-4 space-y-2">
                            @csrf
                            @method('PUT')
                            <input name="activity_title" value="{{ $item->activity_title }}" required placeholder="Activity title"
                                class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs dark:border-gray-700 dark:text-white" />
                            <input name="tags" value="{{ implode(', ', (array) $item->tags) }}" placeholder="Tags separated by commas"
                                class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs dark:border-gray-700 dark:text-white" />
                            <button class="text-xs font-semibold text-brand-600 hover:underline dark:text-brand-300">Save metadata</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="sm:col-span-2 xl:col-span-3 rounded-2xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">No indexed attachments found.</p>
                    <p class="mt-1 text-xs text-gray-400">Submit an accomplishment report with documentation photos to populate this library.</p>
                </div>
            @endforelse
        </div>

        {{ $media->links() }}
    </div>
@endsection
