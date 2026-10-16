@props([
    'name' => '',
    'size' => 'h-10 w-10',
    'text' => 'text-sm',
    'font' => 'font-semibold',
    'photo' => null,
])

@php
    [$bgClass, $textClass] = \App\Helpers\AvatarHelper::colorClassesForName($name);
    $initial = \App\Helpers\AvatarHelper::initialFromName($name);

    // Accepts a public-disk path (profiles.photo) or an absolute/root URL.
    $photo = trim((string) $photo);
    $photoUrl = match (true) {
        $photo === '' => null,
        str_starts_with($photo, 'http://'), str_starts_with($photo, 'https://'), str_starts_with($photo, '/') => $photo,
        default => \Illuminate\Support\Facades\Storage::disk('public')->url($photo),
    };
@endphp

<span {{ $attributes->merge([
    'class' => 'relative inline-flex aspect-square h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-white/80 bg-white/20 ring-1 ring-slate-200/70 shadow-sm ' . $bgClass . ' ' . $textClass . ' ' . $text . ' ' . $font . ' leading-none',
]) }}>
    {{ $initial }}
    @if ($photoUrl)
        {{-- Covers the initial; removed on load failure so the initial shows through. --}}
        <img src="{{ $photoUrl }}" alt="{{ $name }}" loading="lazy"
            class="absolute inset-0 h-full w-full object-cover" onerror="this.remove()" />
    @endif
</span>
