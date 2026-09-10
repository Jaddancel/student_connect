@props([
    'src',
    'alt' => '',
    'label' => null,
])

{{--
    Any image an admin reviews (photo, signature, ID scan, etc.) opens full
    size in the layout's global lightbox on click. This is the default way to
    render a previewable image on an admin request page — new fields that
    render an <img> should use this component instead of a bare <img> tag so
    the enlarge behavior is automatic.
--}}
<img src="{{ $src }}" alt="{{ $alt }}"
    {{ $attributes->merge(['class' => 'cursor-zoom-in']) }}
    @click="$store.lightbox.show(@js($src), @js($label ?? $alt))" />
