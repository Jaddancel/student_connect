<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Form Unavailable' }} | SO-Connect</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                init() {
                    const saved = localStorage.getItem('theme');
                    // Default to light unless the user has explicitly chosen a theme.
                    this.theme = saved || 'light';
                    this.updateTheme();
                },
                theme: 'light',
                toggle() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                    localStorage.setItem('theme', this.theme);
                    this.updateTheme();
                },
                updateTheme() {
                    const html = document.documentElement;
                    const body = document.body;
                    if (this.theme === 'dark') {
                        html.classList.add('dark');
                        body.classList.add('dark', 'bg-gray-900');
                    } else {
                        html.classList.remove('dark');
                        body.classList.remove('dark', 'bg-gray-900');
                    }
                }
            });
        });
    </script>

    <script>
        (function () {
            const saved = localStorage.getItem('theme');
            const theme = saved || 'light';
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
                document.body.classList.add('dark', 'bg-gray-900');
            }
        })();
    </script>
</head>

<body class="min-h-full bg-gray-50 dark:bg-gray-900">

    <main class="flex min-h-screen items-center justify-center px-4 py-12 sm:px-6">
        <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900/60 sm:p-10">

            {{-- Icon --}}
            <div class="mx-auto mb-6 flex h-14 w-14 items-center justify-center rounded-full bg-warning-50 text-warning-500 dark:bg-warning-500/15 dark:text-warning-400">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v3.75m0 3.75h.008M10.34 3.94 1.7 18.06a1.5 1.5 0 0 0 1.3 2.25h17.96a1.5 1.5 0 0 0 1.3-2.25L13.66 3.94a1.5 1.5 0 0 0-2.62 0Z" />
                </svg>
            </div>

            <h1 class="text-lg font-semibold text-gray-800 dark:text-white">
                This form isn&rsquo;t available yet
            </h1>

            @if (!empty($form?->name))
                <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ $form->name }}
                </p>
            @endif

            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                The administrator hasn&rsquo;t published this form&rsquo;s template yet.
                Please check back later.
            </p>

            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                @auth
                    <a href="{{ route('dashboard') }}"
                        class="inline-flex min-w-[9rem] items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Back to dashboard
                    </a>
                @else
                    <a href="{{ route('home') }}"
                        class="inline-flex min-w-[9rem] items-center justify-center rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        Back to home
                    </a>
                @endauth
            </div>
        </div>
    </main>

    @stack('scripts')

</body>
</html>
