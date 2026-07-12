<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Dashboard' }} | TailAdmin - Laravel Tailwind CSS Admin Dashboard Template</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js -->
    {{--
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script> --}}

    <!-- Theme Store -->
    @include('layouts.partials.theme-boot')

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('fileAlert', {
                show: false,
                accepted: '',
                _timer: null,
                trigger(acceptedLabel) {
                    this.accepted = acceptedLabel;
                    this.show = true;
                    clearTimeout(this._timer);
                    this._timer = setTimeout(() => { this.show = false; }, 5000);
                },
                dismiss() {
                    this.show = false;
                    clearTimeout(this._timer);
                },
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

<body x-data="{ 'loaded': true }" x-init="$store.sidebar.isExpanded = window.innerWidth >= 1280;
const checkMobile = () => {
    if (window.innerWidth < 1280) {
        $store.sidebar.setMobileOpen(false);
        $store.sidebar.isExpanded = false;
    } else {
        $store.sidebar.isMobileOpen = false;
        $store.sidebar.isExpanded = true;
    }
};
window.addEventListener('resize', checkMobile);">

    {{-- preloader --}}
    <x-common.preloader />
    {{-- preloader end --}}

    <div class="min-h-screen xl:flex">
        @include('layouts.backdrop')
        @include('layouts.sidebar')

        <div class="flex-1 min-w-0 transition-all duration-300 ease-in-out" :class="{
                'xl:ml-[290px]': $store.sidebar.isExpanded || $store.sidebar.isHovered,
                'xl:ml-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered,
                'ml-0': $store.sidebar.isMobileOpen
            }">
            <!-- app header start -->
            @include('layouts.app-header')
            <!-- app header end -->
            <div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
                @yield('content')
            </div>
        </div>

    </div>

    @include('layouts.partials.auto-hide-alerts')

    {{-- File-type warning toast --}}
    <div x-data x-show="$store.fileAlert.show" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed top-6 right-6 z-[9999] w-80 rounded-xl border border-error-200 bg-white shadow-xl dark:border-error-500/30 dark:bg-gray-900"
         role="alert">
        <div class="flex items-start gap-3 p-4">
            <span class="mt-0.5 flex-shrink-0 text-error-500">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </svg>
            </span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900 dark:text-white">Incompatible file type</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    Please upload a compatible file. Accepted:
                    <span x-text="$store.fileAlert.accepted" class="font-medium text-gray-700 dark:text-gray-300"></span>
                </p>
            </div>
            <button @click="$store.fileAlert.dismiss()"
                    class="flex-shrink-0 text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-200">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                </svg>
            </button>
        </div>
        {{-- progress bar --}}
        <div class="h-1 w-full overflow-hidden rounded-b-xl bg-error-100 dark:bg-error-900/30">
            <div class="h-full bg-error-400 dark:bg-error-500"
                 x-show="$store.fileAlert.show"
                 style="animation: file-alert-shrink 5s linear forwards"
                 x-transition:enter=""></div>
        </div>
    </div>

    <style>
        @keyframes file-alert-shrink { from { width: 100%; } to { width: 0%; } }
        [x-cloak] { display: none !important; }
    </style>

    {{-- Global file-type validator (runs in capture phase, before Alpine handlers) --}}
    <script>
        (function () {
            const MIME_LABELS = {
                'image/jpeg': 'JPG', 'image/jpg': 'JPG', 'image/png': 'PNG',
                'image/gif': 'GIF', 'image/webp': 'WEBP', 'image/svg+xml': 'SVG',
                'application/pdf': 'PDF',
                'application/msword': 'DOC',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'DOCX',
                'application/vnd.ms-excel': 'XLS',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'XLSX',
            };

            function formatAcceptLabel(accept) {
                return accept.split(',')
                    .map(t => t.trim().toLowerCase())
                    .map(t => {
                        if (t.startsWith('.')) return t.slice(1).toUpperCase();
                        if (t === 'image/*') return 'Images';
                        return MIME_LABELS[t] || t.split('/').pop().toUpperCase();
                    })
                    .filter((v, i, a) => a.indexOf(v) === i)
                    .join(', ');
            }

            function isAccepted(file, accept) {
                return accept.split(',').some(function (raw) {
                    const t = raw.trim().toLowerCase();
                    if (t.startsWith('.')) return file.name.toLowerCase().endsWith(t);
                    if (t.endsWith('/*')) return file.type.toLowerCase().startsWith(t.slice(0, -1));
                    return file.type.toLowerCase() === t;
                });
            }

            document.addEventListener('change', function (e) {
                const input = e.target;
                if (input.type !== 'file' || !input.accept || !input.files.length) return;

                const file = input.files[0];
                if (!file || isAccepted(file, input.accept)) return;

                // Clear the selection before Alpine's handler runs
                input.value = '';

                const label = formatAcceptLabel(input.accept);
                if (window.Alpine) {
                    Alpine.store('fileAlert').trigger(label);
                }
            }, true); // capture phase — runs before Alpine's bubble-phase @change
        })();
    </script>

    {{-- Global image lightbox --}}
    <div x-data x-show="$store.lightbox.open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/85 p-4 cursor-zoom-out"
         @click.self="$store.lightbox.close()"
         @keydown.escape.window="$store.lightbox.close()"
         style="display:none">
        <img :src="$store.lightbox.src" :alt="$store.lightbox.alt"
             class="max-h-[90vh] max-w-[90vw] rounded-xl object-contain shadow-2xl cursor-default"
             @click.stop />
        <button @click="$store.lightbox.close()"
                class="absolute top-4 right-4 flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-white/80 backdrop-blur-sm transition hover:bg-white/20 hover:text-white">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('lightbox', {
                open: false,
                src: '',
                alt: '',
                show(src, alt = '') { this.src = src; this.alt = alt; this.open = true; },
                close() { this.open = false; this.src = ''; this.alt = ''; },
            });
        });
    </script>

</body>

@stack('scripts')

</html>