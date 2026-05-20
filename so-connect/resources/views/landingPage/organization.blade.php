<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $organization['name'] }} | Organization Feed</title>

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

        .org-hero {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .org-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            border: 3px solid var(--brand-ocean);
            box-shadow: 4px 4px 0 rgba(19, 121, 91, 0.3);
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--brand-ocean);
            overflow: hidden;
        }

        .org-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
                <span class="text-slate-500">{{ $organization['name'] }}</span>

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
                                            {{ $typeName }}
                                        </p>
                                        <ul class="space-y-1 text-sm">
                                            @foreach ($organizationsByType[$typeKey] as $menuOrganization)
                                                <li>
                                                    <a href="{{ route('organization-feed', ['organizationId' => $menuOrganization['id'], 'slug' => $menuOrganization['slug']]) }}"
                                                        class="block rounded-md px-2 py-1 text-slate-700 transition hover:bg-emerald-50 hover:text-emerald-700">
                                                        {{ $menuOrganization['name'] }}
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
            </nav>

            <a href="{{ route('home') }}"
                class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 md:hidden">Back</a>
        </div>
    </header>

    <main class="mx-auto w-full max-w-5xl px-4 pb-20 pt-8 sm:px-6 lg:px-8">
        @php
            $orgInitials = collect(preg_split('/\s+/', $organization['name']))
                ->filter(fn($word) => strlen($word) > 2)
                ->take(2)
                ->map(fn($word) => strtoupper($word[0]))
                ->implode('');
        @endphp
        <section class="rounded-3xl bg-[color:var(--brand-cream)] p-6 ring-1 ring-emerald-100 sm:p-8">
            <div class="org-hero">
                <div class="org-avatar">
                    @if (!empty($organization['logo_url']))
                        <img src="{{ $organization['logo_url'] }}" alt="{{ $organization['name'] }} logo">
                    @else
                        {{ $orgInitials }}
                    @endif
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-emerald-700">Organization Page</p>
                    <h1 class="brand-font mt-2 text-4xl text-slate-800">{{ $organization['name'] }}</h1>
                    <p class="mt-3 text-sm text-slate-600">
                        Latest announcements and updates from this organization.
                    </p>
                </div>
            </div>
        </section>

        <section class="mt-8 space-y-4">
            @forelse ($posts as $post)
                <article class="rounded-2xl border border-slate-200 bg-[color:var(--card-bg)] p-5 shadow-sm">
                    @if (!empty($post->image_path))
                        <div class="mb-4 overflow-hidden rounded-xl border border-emerald-100 bg-white shadow-sm">
                            <img src="{{ asset($post->image_path) }}" alt="{{ $post->title }}" class="h-48 w-full object-cover"
                                loading="lazy">
                        </div>
                    @endif
                    <div class="flex items-center justify-between gap-3">
                        <span
                            class="text-xs font-bold uppercase tracking-[0.12em] text-[color:var(--brand-ocean)]">{{ $post->tag ?? 'Update' }}</span>
                        <span class="text-xs text-slate-500">
                            {{ $post->published_at?->diffForHumans() ?? $post->created_at?->diffForHumans() }}
                        </span>
                    </div>
                    <h2 class="mt-2 text-xl font-bold text-slate-800">{{ $post->title }}</h2>
                    <p class="mt-2 text-sm text-slate-600">{{ $post->excerpt }}</p>
                    @if (! empty($post->body))
                        <p class="mt-3 text-sm leading-7 text-slate-600">{!! nl2br(e($post->body)) !!}</p>
                    @endif

                    @if (! empty($post->gallery_images))
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($post->gallery_images as $galleryImage)
                                <div class="overflow-hidden rounded-xl border border-emerald-100 bg-white shadow-sm">
                                    <img src="{{ asset($galleryImage) }}" alt="{{ $post->title }} image" class="h-48 w-full object-cover" loading="lazy">
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <p class="mt-3 text-xs font-semibold text-slate-500">Organization: {{ $organization['name'] }}</p>
                </article>
            @empty
                <article class="rounded-2xl border border-dashed border-emerald-200 bg-white p-6 text-center">
                    <p class="text-sm font-semibold text-slate-500">No posts yet for this organization.</p>
                </article>
            @endforelse
        </section>
    </main>
</body>

</html>