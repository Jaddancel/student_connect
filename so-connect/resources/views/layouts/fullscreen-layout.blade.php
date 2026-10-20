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

    @include('layouts.partials.auto-hide-alerts')
    @stack('scripts')

</body>

</html>
