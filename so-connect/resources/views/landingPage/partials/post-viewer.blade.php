<dialog x-ref="postViewer" class="post-viewer" aria-labelledby="post-viewer-title"
    @cancel.prevent="closePost()" @keydown="handleKey($event)">
    <template x-if="post">
        <div class="post-viewer-layout">
            <section class="post-viewer-media" aria-label="Post attachments">
                <button type="button" autofocus @click="closePost()" aria-label="Close post"
                    class="post-viewer-control post-viewer-close">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                    </svg>
                </button>

                <template x-if="post.video">
                    <video :src="post.video" :aria-label="post.title" controls playsinline
                        class="h-full w-full object-contain" x-on:error="videoFailed = true">
                    </video>
                </template>
                <p x-show="videoFailed" x-cloak role="status" class="absolute bottom-16 rounded-lg bg-black/80 px-4 py-2 text-white">Video unavailable.</p>

                <template x-if="!post.video && images.length">
                    <div class="flex h-full w-full items-center justify-center">
                        <template x-for="(image, imageIndex) in images" :key="image">
                            <template x-if="index === imageIndex">
                                <img :src="image" :alt="post.title + ' — image ' + (index + 1)"
                                    x-show="!imageFailed" x-on:error="imageFailed = true"
                                    class="h-full w-full object-contain">
                            </template>
                        </template>
                        <p x-show="imageFailed" x-cloak role="status" class="px-16 text-center text-sm text-white/70">
                            Image unavailable. You can still browse the other attachments.
                        </p>
                    </div>
                </template>
                <p x-show="!post.video && !images.length" class="px-16 text-center text-sm text-white/60">
                    No images attached to this post.
                </p>

                <button type="button" x-show="images.length > 1 && !post.video" @click="changeImage(-1)"
                    aria-label="Previous image" class="post-viewer-control absolute left-4 top-1/2 -translate-y-1/2">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6" />
                    </svg>
                </button>
                <button type="button" x-show="images.length > 1 && !post.video" @click="changeImage(1)"
                    aria-label="Next image" class="post-viewer-control absolute right-4 top-1/2 -translate-y-1/2">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6" />
                    </svg>
                </button>
                <div x-show="images.length && !post.video"
                    class="absolute bottom-5 rounded-full bg-black/70 px-4 py-2 text-xs text-white/80"
                    role="status" aria-live="polite" aria-atomic="true">
                    <span x-text="(index + 1) + ' / ' + images.length"></span>
                    <span x-show="images.length > 1" class="ml-3">Use &#8592; &#8594; to browse</span>
                </div>
            </section>

            <aside class="post-viewer-details bg-white text-slate-800 dark:bg-[#16241d] dark:text-slate-100">
                <div class="flex items-center gap-3 border-b border-slate-200 pb-5 dark:border-slate-700">
                    <div class="org-avatar h-12 w-12 text-sm">
                        @if (!empty($organization['logo_url']))
                            <img src="{{ $organization['logo_url'] }}" alt="">
                        @else
                            {{ $orgInitials }}
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-bold">{{ $organization['name'] }}</p>
                        <time :datetime="post.dateIso" :title="post.date"
                            class="mt-1 block text-xs text-slate-500 dark:text-slate-400" x-text="post.date"></time>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap items-center gap-2 text-xs font-semibold">
                    <span x-show="post.tag" x-text="post.tag"
                        class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"></span>
                    <span x-show="post.featured" class="rounded-full bg-amber-50 px-3 py-1 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">Featured</span>
                </div>
                <h2 id="post-viewer-title" class="mt-4 text-xl font-bold leading-snug" x-text="post.title"></h2>
                <p class="mt-4 whitespace-pre-wrap break-words text-sm leading-relaxed" x-text="post.body || post.excerpt"></p>
            </aside>
        </div>
    </template>
</dialog>
