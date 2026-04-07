<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Student Connect | Organization Feed</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Sora:wght@600;700&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --brand-ink: #173b2e;
            --brand-cream: #eefbf3;
            --brand-mint: #d7f4e3;
            --brand-orange: #1e9f67;
            --brand-ocean: #13795b;
            --card-bg: #f7fffb;
        }

        body {
            font-family: 'Manrope', sans-serif;
            color: var(--brand-ink);
            background:
                radial-gradient(circle at 10% 10%, #d8f5e5 0%, rgba(216, 245, 229, 0.36) 30%, transparent 55%),
                radial-gradient(circle at 90% 12%, #bceece 0%, rgba(188, 238, 206, 0.4) 28%, transparent 56%),
                linear-gradient(180deg, #f2fff6 0%, #fff 48%, #f1fff8 100%);
            min-height: 100vh;
        }

        .brand-font {
            font-family: 'Sora', sans-serif;
        }

        .submenu-panel {
            max-height: min(70vh, 34rem);
            overflow-y: auto;
        }

        .feed-card {
            animation: rise-in 0.55s ease forwards;
            opacity: 0;
            transform: translateY(12px);
        }

        @keyframes rise-in {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <header class="sticky top-0 z-30 border-b border-emerald-100/80 bg-white/85 backdrop-blur">
        <div class="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="brand-font text-2xl font-bold tracking-tight text-slate-800">Student
                Connect</a>

            <nav class="hidden items-center gap-8 text-sm font-semibold md:flex">
                <a href="{{ route('home') }}" class="text-slate-700 transition hover:text-emerald-600">Home</a>
                <a href="#feed" class="text-slate-700 transition hover:text-emerald-600">Feed</a>

                <div class="group relative">
                    <button class="inline-flex items-center gap-2 text-slate-700 transition hover:text-emerald-600"
                        type="button">
                        Organizations
                        <span aria-hidden="true">▾</span>
                    </button>

                    <div
                        class="invisible absolute right-0 top-full mt-3 w-[min(92vw,56rem)] rounded-2xl border border-slate-200 bg-white p-5 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
                        <div class="submenu-panel grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                            @foreach ($organizationTypes as $typeKey => $typeName)
                                @if (!empty($organizationsByType[$typeKey]))
                                    <div>
                                        <p
                                            class="mb-2 border-b border-slate-200 pb-2 text-xs font-bold uppercase tracking-[0.12em] text-slate-500">
                                            {{ $typeName }}</p>
                                        <ul class="space-y-1 text-sm">
                                            @foreach ($organizationsByType[$typeKey] as $organization)
                                                <li>
                                                    <a href="{{ route('organization-feed', ['organizationId' => $organization['id'], 'slug' => $organization['slug']]) }}"
                                                        class="block rounded-md px-2 py-1 text-slate-700 transition hover:bg-emerald-50 hover:text-emerald-700">
                                                        {{ $organization['name'] }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>

                <a href="{{ route('login') }}"
                    class="rounded-full bg-slate-800 px-4 py-2 text-white transition hover:bg-slate-700">Log In</a>
            </nav>

            <details class="relative md:hidden">
                <summary
                    class="cursor-pointer rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                    Menu</summary>
                <div
                    class="absolute right-0 mt-3 w-[min(92vw,23rem)] rounded-2xl border border-slate-200 bg-white p-4 shadow-lg">
                    <a href="{{ route('home') }}"
                        class="block rounded-md px-2 py-2 text-sm font-semibold text-slate-700 hover:bg-emerald-50">Home</a>
                    <a href="#feed"
                        class="mt-1 block rounded-md px-2 py-2 text-sm font-semibold text-slate-700 hover:bg-emerald-50">Feed</a>
                    <div class="mt-3 rounded-lg border border-slate-200 p-3">
                        <p class="mb-2 text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Organizations</p>
                        <div class="max-h-64 space-y-3 overflow-y-auto pr-1">
                            @foreach ($organizationTypes as $typeKey => $typeName)
                                @if (!empty($organizationsByType[$typeKey]))
                                    <div>
                                        <p class="text-xs font-semibold text-slate-500">{{ $typeName }}</p>
                                        <ul class="mt-1 space-y-1">
                                            @foreach ($organizationsByType[$typeKey] as $organization)
                                                <li>
                                                    <a href="{{ route('organization-feed', ['organizationId' => $organization['id'], 'slug' => $organization['slug']]) }}"
                                                        class="block rounded-md px-2 py-1 text-sm text-slate-700 hover:bg-emerald-50">{{ $organization['name'] }}</a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </details>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl px-4 pb-20 pt-8 sm:px-6 lg:px-8">
        {{-- <section
            class="grid gap-8 rounded-3xl bg-[color:var(--brand-cream)] p-6 shadow-sm ring-1 ring-emerald-100 md:grid-cols-5 md:p-10">
            <div class="md:col-span-3">
                <p
                    class="mb-3 inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold uppercase tracking-[0.12em] text-emerald-700">
                    Campus Pulse</p>
                <h1 class="brand-font text-4xl leading-tight text-slate-800 sm:text-5xl">Default landing feed for all
                    organizations</h1>
                <p class="mt-4 max-w-2xl text-base text-slate-700">
                    This page summarizes organization activity across campus. Use the Organizations submenu to jump to
                    an individual organization feed page.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="#feed"
                        class="rounded-full bg-[color:var(--brand-orange)] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">View
                        Feed</a>
                    <a href="#organizations"
                        class="rounded-full border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400">Browse
                        Organizations</a>
                </div>
            </div>

            <div class="space-y-3 md:col-span-2">
                <div class="rounded-2xl bg-white p-4 ring-1 ring-slate-200">
                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Active Categories</p>
                    <p class="mt-2 text-3xl font-bold text-slate-800">{{ count($organizationTypes) }}</p>
                </div>
                <div class="rounded-2xl bg-[color:var(--brand-mint)] p-4 ring-1 ring-emerald-100">
                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-emerald-700">Individual Feeds</p>
                    <p class="mt-2 text-sm text-emerald-900">Each organization now has its own feed page linked from the
                        navigation submenu.</p>
                </div>
            </div>
        </section> --}}

        <section id="feed" class="mt-12">
            <div class="mb-5 flex items-end justify-between gap-3">
                <h2 class="brand-font text-3xl text-slate-800">Latest Organization Updates</h2>
                <p class="text-sm text-slate-500">Sample content for landing feed preview</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($topFeed as $index => $entry)
                    <article
                        class="feed-card rounded-2xl border border-slate-200 bg-[color:var(--card-bg)] p-5 shadow-sm"
                        style="animation-delay: {{ $index * 0.06 }}s;">
                        <p class="text-xs font-bold uppercase tracking-[0.12em] text-[color:var(--brand-ocean)]">
                            {{ $entry['announcement_tag'] }}</p>
                        <h3 class="mt-2 text-lg font-bold text-slate-800">{{ $entry['announcement_title'] }}</h3>
                        <p class="mt-2 text-sm text-slate-600">{{ $entry['announcement_excerpt'] }}</p>
                        <div
                            class="mt-4 flex items-center justify-between border-t border-slate-200 pt-3 text-xs text-slate-500">
                            <span>{{ $entry['name'] }}</span>
                            <span>{{ $entry['announcement_time'] }}</span>
                        </div>
                    </article>
                @empty
                    <p class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">No organizations
                        found yet. Seed data to populate the landing feed.</p>
                @endforelse
            </div>
        </section>

        <section id="organizations" class="mt-14 space-y-10">
            <h2 class="brand-font text-3xl text-slate-800">Organizations by Type</h2>

            @foreach ($organizationTypes as $typeKey => $typeName)
                @if (!empty($organizationsByType[$typeKey]))
                    <section class="rounded-3xl border border-slate-200 bg-white/90 p-5 sm:p-7">
                        <h3 class="brand-font text-2xl text-slate-800">{{ $typeName }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Open an organization to view its dedicated feed page</p>

                        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($organizationsByType[$typeKey] as $organization)
                                <article
                                    class="rounded-2xl border border-slate-200 bg-[color:var(--card-bg)] p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                                    <p class="text-xs font-semibold uppercase tracking-[0.11em] text-emerald-700">
                                        {{ $organization['name'] }}</p>
                                    <h4 class="mt-2 text-base font-bold text-slate-800">
                                        {{ $organization['announcement_title'] }}</h4>
                                    <p class="mt-2 text-sm text-slate-600">{{ $organization['announcement_excerpt'] }}
                                    </p>
                                    <div
                                        class="mt-4 flex items-center justify-between border-t border-slate-200 pt-3 text-xs text-slate-500">
                                        <span>{{ $organization['announcement_tag'] }}</span>
                                        <a href="{{ route('organization-feed', ['organizationId' => $organization['id'], 'slug' => $organization['slug']]) }}"
                                            class="font-semibold text-[color:var(--brand-ocean)] hover:underline">Open
                                            feed</a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach
        </section>
    </main>
</body>

</html>
