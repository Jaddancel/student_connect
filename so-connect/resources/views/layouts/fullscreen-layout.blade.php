<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Welcome' }} | SO Connect</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js -->
    {{--
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script> --}}

    <!-- Theme Store -->
    @include('layouts.partials.theme-boot')

    <script @nonce>
        document.addEventListener('alpine:init', () => {
            Alpine.store('lightbox', {
                open: false,
                src: '',
                alt: '',
                show(src, alt = '') { this.src = src; this.alt = alt; this.open = true; },
                close() { this.open = false; this.src = ''; this.alt = ''; },
            });

            Alpine.store('sidebar', {
                // Initialize based on screen size
                isExpanded: window.innerWidth >= 1280, // true for desktop, false for mobile
                isMobileOpen: false,
                isHovered: false,

                toggleExpanded() {
                    this.isExpanded = !this.isExpanded;
                    // When toggling desktop sidebar, ensure mobile menu is closed
                    this.isMobileOpen = false;
                },

                toggleMobileOpen() {
                    this.isMobileOpen = !this.isMobileOpen;
                    // Don't modify isExpanded when toggling mobile menu
                },

                setMobileOpen(val) {
                    this.isMobileOpen = val;
                },

                setHovered(val) {
                    // Only allow hover effects on desktop when sidebar is collapsed
                    if (window.innerWidth >= 1280 && !this.isExpanded) {
                        this.isHovered = val;
                    }
                }
            });
        });
    </script>

</head>

<body x-data="layoutBody">

    {{-- preloader --}}
    <x-common.preloader />
    {{-- preloader end --}}

    {{-- Auth/fullscreen screens have no header, so the theme control floats.
         Without it these pages inherit dark from the dashboard with no way out. --}}
    <x-theme-toggle
        class="fixed bottom-6 right-6 z-[99999] flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 shadow-theme-md transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" />

    @yield('content')

    {{-- Registration and other fullscreen flows can preview uploaded images. --}}
    <div x-data x-show="$store.lightbox.open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[99999] flex cursor-zoom-out items-center justify-center bg-black/85 p-4"
         @click.self="$store.lightbox.close()"
         @keydown.escape.window="$store.lightbox.close()">
        <img :src="$store.lightbox.src" :alt="$store.lightbox.alt"
             class="max-h-[90vh] max-w-[90vw] cursor-default rounded-xl bg-white p-2 object-contain shadow-2xl"
             @click.stop />
        <button type="button" @click="$store.lightbox.close()" aria-label="Close image preview"
                class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white/80 backdrop-blur-sm transition hover:bg-white/20 hover:text-white">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
        </button>
    </div>

    @include('layouts.partials.auto-hide-alerts')
    @stack('scripts')

</body>

</html>
