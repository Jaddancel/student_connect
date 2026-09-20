@props([
    'name' => '',
    'size' => 'h-10 w-10',
    'text' => 'text-sm',
    'font' => 'font-semibold',
])

@php
    [$bgClass, $textClass] = \App\Helpers\AvatarHelper::colorClassesForName($name);
    $initial = \App\Helpers\AvatarHelper::initialFromName($name);
@endphp

<span {{ $attributes->merge([
    'class' => 'relative inline-flex aspect-square h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-white/80 bg-white/20 ring-1 ring-slate-200/70 shadow-sm ' . $bgClass . ' ' . $textClass . ' ' . $text . ' ' . $font . ' leading-none',
]) }}>
    {{ $initial }}
</span>
