@props([
    // Items from App\Forms\SubmissionPresenter::attachments()
    'items' => [],
    'label' => '',
])

@php
    use App\Forms\SubmissionPresenter;
@endphp

{{--
    Photos and files submitted on a form, as the admin reviewing the request
    sees them: a thumbnail that opens the picture full size in the layout's
    global lightbox, and a plain link for anything that is not an image.

    Thumbnails sit on white on purpose — a captured signature is dark ink on a
    transparent background, which is invisible against a dark card.
--}}
{{-- x-data makes this its own Alpine scope: the thumbnails are rendered
     outside any component, and the store magics only exist inside one. --}}
<div x-data {{ $attributes->merge(['class' => 'mt-2 flex flex-wrap gap-3']) }}>
    @forelse ($items as $item)
        @if ($item['is_image'])
            <figure class="w-full max-w-[15rem]">
                <button type="button"
                    @click="$store.lightbox.show(@js($item['url']), @js($label !== '' ? $label.' — '.$item['name'] : $item['name']))"
                    class="group block w-full cursor-zoom-in overflow-hidden rounded-lg border border-gray-200 bg-white transition hover:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30 dark:border-gray-700"
                    title="Click to view full size">
                    <img src="{{ $item['url'] }}" alt="{{ $label ?: $item['name'] }}"
                         class="h-40 w-full bg-white object-contain transition group-hover:scale-[1.02]" />
                </button>
                <figcaption class="mt-1 flex items-center justify-between gap-2 text-[11px] text-gray-400">
                    <span class="truncate">{{ $item['name'] }}</span>
                    <a href="{{ $item['url'] }}" target="_blank" rel="noopener"
                       class="shrink-0 text-brand-500 hover:underline">Open</a>
                </figcaption>
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
