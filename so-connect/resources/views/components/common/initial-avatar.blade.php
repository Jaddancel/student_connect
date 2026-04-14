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
    'class' => 'inline-flex items-center justify-center rounded-full ' . $size . ' ' . $bgClass . ' ' . $textClass . ' ' . $text . ' ' . $font,
]) }}>
    {{ $initial }}
</span>
