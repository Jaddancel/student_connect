<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Directory of Student Leader' }} | SO-Connect</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @include('layouts.partials.theme-boot')
</head>

<body class="min-h-full bg-gray-50 dark:bg-gray-900">

    {{-- Top bar --}}
    <header class="sticky top-0 z-30 border-b border-gray-200 bg-white/90 backdrop-blur dark:border-gray-800 dark:bg-gray-900/90">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3 sm:px-6">
            <div class="flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-palette-lime">
                    <svg class="h-4 w-4 text-gray-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422A12.083 12.083 0 0121 17.5H3a12.083 12.083 0 012.84-6.922L12 14z" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-gray-800 dark:text-white">Tarlac Agricultural University</span>
            </div>
            <div class="flex items-center gap-3">
                <x-theme-toggle
                    class="flex items-center rounded-lg border border-gray-200 p-2 text-gray-500 transition hover:bg-gray-100 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800" />
                <a href="/"
                    class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                    Sign In
                </a>
            </div>
        </div>
    </header>

    {{-- Main content --}}
    <main class="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-10">

        @if (session('success'))
            <div class="mb-6 rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="border-t border-gray-200 bg-white py-5 dark:border-gray-800 dark:bg-gray-900">
        <div class="mx-auto max-w-3xl px-4 sm:px-6">
            <p class="text-center text-xs text-gray-400 dark:text-gray-500">
                Office of Student Services and Development · Student Development Unit
                <span class="mx-2">·</span>
                Already have an account?
                <a href="/" class="text-brand-500 hover:text-brand-600 dark:text-brand-400">Sign In</a>
            </p>
        </div>
    </footer>

    @include('layouts.partials.auto-hide-alerts')

    @stack('scripts')

</body>
</html>
