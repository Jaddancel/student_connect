@props(['href' => '#', 'label' => 'Add'])

{{-- Floating action button, fixed bottom-right. --}}
<a href="{{ $href }}" title="{{ $label }}" aria-label="{{ $label }}"
    {{ $attributes->merge(['class' => 'fixed bottom-6 right-6 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-brand-500 text-white shadow-lg transition hover:bg-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-300']) }}>
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
    </svg>
</a>
