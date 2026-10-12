@props([
    // Items from App\Forms\SubmissionPresenter::attachments()
    'items' => [],
    'label' => '',
])

@php
    use App\Forms\SubmissionPresenter;
@endphp

{{--
    Photos and videos submitted on a form, as the admin reviewing the request
    sees them. Thumbnails open in the global lightbox; EXIF/metadata is shown
    separately so reviewers can inspect it without leaving the request.

    Thumbnails sit on white on purpose — a captured signature is dark ink on a
    transparent background, which is invisible against a dark card.
--}}
<div x-data {{ $attributes->merge(['class' => 'mt-2 flex flex-wrap gap-3']) }}>
    @forelse ($items as $item)
        @if ($item['is_image'] || $item['is_video'])
            <figure x-data="mediaPreview({
                    src: @js($item['url']),
                    label: @js($label !== '' ? $label.' — '.$item['name'] : $item['name']),
                    isHeic: @js(in_array(strtolower(pathinfo($item['name'], PATHINFO_EXTENSION)), ['heic', 'heif'], true)),
                })"
                @keydown.escape.window="metadataOpen = false" class="w-full max-w-[15rem]">
                @if ($item['is_image'])
                    <button type="button"
                        @click="openPreview()"
                        class="group block w-full cursor-zoom-in overflow-hidden rounded-lg border border-gray-200 bg-white transition hover:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30 dark:border-gray-700"
                        title="Click to view full size">
                        <img :src="previewUrl" alt="{{ $label ?: $item['name'] }}"
                             class="h-40 w-full bg-white object-contain transition group-hover:scale-[1.02]" />
                    </button>
                @else
                    <video src="{{ $item['url'] }}" aria-label="{{ $label ?: $item['name'] }}"
                        controls preload="metadata" class="h-40 w-full rounded-lg border border-gray-200 bg-black object-contain dark:border-gray-700"></video>
                @endif
                <figcaption class="mt-1 flex items-center justify-between gap-2 text-[11px] text-gray-400">
                    <span class="truncate">{{ $item['name'] }}</span>
                    <span x-show="previewError" x-text="previewError" class="text-error-500"></span>
                    @if ($item['metadata_error'] ?? null)
                        <span class="shrink-0 text-error-500">{{ $item['metadata_error'] }}</span>
                    @elseif ($item['metadata'] === [])
                        <span class="shrink-0 text-gray-400">EXIF data missing</span>
                    @else
                        <button type="button" @click="metadataOpen = true"
                            class="shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            EXIF Data
                        </button>
                    @endif
                    <a href="{{ $item['url'] }}" target="_blank" rel="noopener"
                       class="shrink-0 text-brand-500 hover:underline">Open</a>
                </figcaption>

                @if ($item['metadata'] !== [])
                    <div x-show="metadataOpen" x-cloak role="dialog" aria-modal="true" aria-labelledby="exif-title-{{ $loop->index }}"
                        class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-5">
                        <button type="button" @click="metadataOpen = false" aria-label="Close EXIF data"
                            class="fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"></button>
                        <div @click.stop class="relative max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-3xl bg-white p-6 shadow-xl dark:bg-gray-900">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h2 id="exif-title-{{ $loop->index }}" class="text-lg font-semibold text-gray-800 dark:text-white/90">EXIF Data</h2>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $item['name'] }}</p>
                                </div>
                                <button type="button" @click="metadataOpen = false"
                                    class="rounded-full p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Close">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M18 6 6 18" />
                                    </svg>
                                </button>
                            </div>
                            <dl class="mt-5 grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                                @foreach ($item['metadata'] as $name => $value)
                                    <div class="min-w-0">
                                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $name }}</dt>
                                        <dd class="mt-1 break-words text-sm text-gray-800 dark:text-white/90">{{ $value }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    </div>
                @endif
            </figure>
        @else
            <a href="{{ $item['url'] }}" target="_blank" rel="noopener"
               class="inline-flex max-w-full items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 transition hover:border-brand-400 hover:text-brand-600 dark:border-gray-700 dark:text-gray-300">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z" />
                </svg>
                {{-- Uploads are stored under a generated name, so the field's own
                     label reads better than the file's; the type and size still
                     say what is about to open. --}}
                <span class="truncate">{{ $label !== '' ? $label : $item['name'] }}</span>
                <span class="shrink-0 text-xs text-gray-400">
                    {{ strtoupper(pathinfo($item['name'], PATHINFO_EXTENSION)) ?: 'FILE' }}@if ($item['size'] !== null) · {{ SubmissionPresenter::humanSize($item['size']) }}@endif
                </span>
            </a>
        @endif
    @empty
        <span class="text-gray-800 dark:text-white/90">—</span>
    @endforelse
</div>
