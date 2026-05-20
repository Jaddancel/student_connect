@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Organization Posts" />

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-5 lg:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Create a new organization post</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create announcement content, attach a header image, and reuse accomplishment report media.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.posts.store') }}" enctype="multipart/form-data" class="mt-6 grid gap-5 lg:grid-cols-2">
                @csrf

                <div class="space-y-4">
                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">Organization</label>
                        <select name="organization" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-200">
                            <option value="">Select organization</option>
                            @foreach ($organizations as $organization)
                                <option value="{{ $organization->id }}" @selected(old('organization') == $organization->id)>{{ $organization->name ?? 'Organization '.$organization->id }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">Title</label>
                        <input type="text" name="title" value="{{ old('title') }}" placeholder="Post title"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-200" />
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">Tag</label>
                        <input type="text" name="tag" value="{{ old('tag') }}" placeholder="e.g. Announcement"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-200" />
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">Published at</label>
                        <x-form.date-picker name="published_at" value="{{ old('published_at') }}" placeholder="Select publish date" />
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="hidden" name="is_featured" value="0" />
                        <input id="is_featured" name="is_featured" type="checkbox" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" @checked(old('is_featured')) />
                        <label for="is_featured" class="text-sm font-medium text-slate-700">Feature this post</label>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">Header image</label>
                        <input type="file" name="header_image" accept="image/*"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-slate-700 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-200" />
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">Excerpt</label>
                        <textarea name="excerpt" rows="3" placeholder="Short summary for this post" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-200">{{ old('excerpt') }}</textarea>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">Body</label>
                        <textarea name="body" rows="6" placeholder="Full post content" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-200">{{ old('body') }}</textarea>
                    </div>

                    <div>
                        <label class="mb-3 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">Accomplishment report media</label>
                        @if (empty($reportMedia))
                            <p class="text-sm text-slate-500">No accomplishment report media available yet.</p>
                        @else
                            <div class="space-y-4">
                                @foreach ($reportMedia as $orgId => $items)
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                        <p class="text-sm font-semibold text-slate-800">{{ $organizations->firstWhere('id', $orgId)->name ?? 'Organization '.$orgId }}</p>
                                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                            @foreach ($items as $item)
                                                <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 text-sm text-slate-700 shadow-sm transition hover:border-brand-300">
                                                    <input type="checkbox" name="selected_media[]" value="{{ $item['path'] }}" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" @checked(in_array($item['path'], old('selected_media', []))) />
                                                    <div>
                                                        <div class="font-semibold">{{ $item['label'] }}</div>
                                                        <p class="text-xs text-slate-500">{{ $item['submitted_at']->format('M d, Y') }}</p>
                                                        <p class="truncate text-xs text-slate-500">{{ $item['path'] }}</p>
                                                    </div>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="sm:col-span-2 flex justify-end">
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">Create Post</button>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Existing posts</h3>
            </div>
            @if ($posts->isEmpty())
                <div class="p-10 text-center text-sm text-slate-500">No organization posts have been created yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Organization</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Title</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Published</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Featured</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Media</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($posts as $post)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                    <td class="px-6 py-4 text-slate-700">{{ $organizations->firstWhere('id', $post->organization)?->name ?? 'Organization '.$post->organization }}</td>
                                    <td class="px-6 py-4 font-medium text-slate-900">{{ $post->title }}</td>
                                    <td class="px-6 py-4 text-slate-600">{{ $post->published_at?->format('M d, Y') ?? $post->created_at->format('M d, Y') }}</td>
                                    <td class="px-6 py-4 text-slate-600">{{ $post->is_featured ? 'Yes' : 'No' }}</td>
                                    <td class="px-6 py-4 text-slate-600">{{ count($post->gallery_images ?? []) }} items</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
