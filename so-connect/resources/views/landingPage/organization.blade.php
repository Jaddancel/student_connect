<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $organization['name'] }} | Organization Feed</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Shared light/dark bootstrap (same localStorage key as the dashboard) --}}
    @include('layouts.partials.theme-boot')

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
            --page-wash:
                radial-gradient(circle at 10% 10%, #d8f5e5 0%, rgba(216, 245, 229, 0.36) 30%, transparent 55%),
                radial-gradient(circle at 90% 12%, #bceece 0%, rgba(188, 238, 206, 0.4) 28%, transparent 56%),
                linear-gradient(180deg, #f2fff6 0%, #fff 48%, #f1fff8 100%);
            --avatar-bg: #fff;
        }

        /* Same signal the dashboard uses — see layouts/partials/theme-boot. */
        html.dark {
            --brand-ink: #dbe5de;
            --brand-cream: #16241d;
            --brand-mint: #1c3a2b;
            --brand-orange: #4fae83;
            --brand-ocean: #5ec095;
            --card-bg: #14211b;
            --page-wash:
                radial-gradient(circle at 10% 10%, rgba(31, 85, 64, 0.5) 0%, rgba(31, 85, 64, 0.18) 30%, transparent 55%),
                radial-gradient(circle at 90% 12%, rgba(23, 70, 52, 0.5) 0%, rgba(23, 70, 52, 0.2) 28%, transparent 56%),
                linear-gradient(180deg, #0d1613 0%, #101a16 48%, #0d1613 100%);
            --avatar-bg: #16241d;
        }

        body {
            font-family: 'Manrope', sans-serif;
            color: var(--brand-ink);
            background: var(--page-wash);
            min-height: 100vh;
        }

        .brand-font {
            font-family: 'Sora', sans-serif;
        }

        .submenu-panel {
            max-height: min(70vh, 34rem);
            overflow-y: auto;
        }

        .org-avatar {
            border-radius: 50%;
            border: 2px solid var(--brand-ocean);
            background: var(--avatar-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--brand-ocean);
            overflow: hidden;
            flex-shrink: 0;
        }

        .org-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Post card hover overlay */
        .post-card .card-overlay {
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .post-card:hover .card-overlay {
            opacity: 1;
        }

        .post-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(19, 121, 91, 0.12);
        }

        .post-card {
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .post-viewer {
            position: fixed;
            inset: 0;
            width: 100%;
            max-width: none;
            height: 100dvh;
            max-height: none;
            margin: 0;
            padding: 0;
            border: 0;
            background: #000;
        }

        .post-viewer::backdrop { background: #000; }
        .post-viewer-layout { display: grid; grid-template-columns: minmax(0, 1fr) 24rem; height: 100%; }
        .post-viewer-media { position: relative; display: flex; align-items: center; justify-content: center; min-width: 0; min-height: 0; padding: 4rem 0; background: #000; }
        .post-viewer-details { min-height: 0; overflow-y: auto; padding: 1.5rem; }
        .post-viewer-control { z-index: 1; display: flex; align-items: center; justify-content: center; width: 2.75rem; height: 2.75rem; border-radius: 50%; background: #303030; color: #fff; cursor: pointer; }
        .post-viewer-control:hover { background: #505050; }
        .post-viewer-control:focus-visible { outline: 3px solid #6ee7b7; outline-offset: 3px; }
        .post-viewer-close { position: absolute; top: 1rem; left: 1rem; }
        @media (max-width: 767px) {
            .post-viewer-layout { grid-template-columns: 1fr; grid-template-rows: minmax(0, 58fr) minmax(0, 42fr); }
            .post-viewer-details { padding: 1.25rem; }
        }
    </style>
</head>

<body x-data="postViewer">
    <header
        class="sticky top-0 z-30 border-b border-emerald-100/80 bg-white/85 backdrop-blur dark:border-emerald-900/40 dark:bg-[#0d1613]/85">
        <div class="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}"
                class="brand-font text-2xl font-bold tracking-tight text-slate-800 dark:text-slate-100">Student
                Connect</a>

            <nav class="hidden items-center gap-8 text-sm font-semibold md:flex">
                <a href="{{ route('home') }}"
                    class="text-slate-700 transition hover:text-emerald-600 dark:text-slate-300 dark:hover:text-emerald-400">Home</a>
                <span class="text-slate-500 dark:text-slate-400">{{ $organization['name'] }}</span>

                <div class="group relative">
                    <button
                        class="inline-flex items-center gap-2 text-slate-700 transition hover:text-emerald-600 dark:text-slate-300 dark:hover:text-emerald-400"
                        type="button">
                        Organizations <span aria-hidden="true">▾</span>
                    </button>
                    <div
                        class="invisible absolute right-0 top-full mt-3 w-[min(92vw,56rem)] rounded-2xl border border-slate-200 bg-white p-5 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100 dark:border-slate-700 dark:bg-[#16241d]">
                        <div class="submenu-panel grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                            @foreach ($organizationTypes as $typeKey => $typeName)
                                @if (!empty($organizationsByType[$typeKey]))
                                    <div>
                                        <p
                                            class="mb-2 border-b border-slate-200 pb-2 text-xs font-bold uppercase tracking-[0.12em] text-slate-500 dark:border-slate-700 dark:text-slate-400">
                                            {{ $typeName }}</p>
                                        <ul class="space-y-1 text-sm">
                                            @foreach ($organizationsByType[$typeKey] as $menuOrganization)
                                                <li>
                                                    <a href="{{ route('organization-feed', ['organizationId' => $menuOrganization['id'], 'slug' => $menuOrganization['slug']]) }}"
                                                        class="block rounded-md px-2 py-1 text-slate-700 transition hover:bg-emerald-50 hover:text-emerald-700 dark:text-slate-300 dark:hover:bg-emerald-900/30 dark:hover:text-emerald-300">
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

            <div class="flex items-center gap-3">
                <x-theme-toggle
                    class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" />

                <a href="{{ route('home') }}"
                    class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-300 md:hidden">Back</a>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-6xl px-4 pb-24 pt-8 sm:px-6 lg:px-8">

        {{-- ── Org Hero ── --}}
        @php
            $orgInitials = collect(preg_split('/\s+/', $organization['name']))
                ->filter(fn($word) => strlen($word) > 2)
                ->take(2)
                ->map(fn($word) => strtoupper($word[0]))
                ->implode('');

            $orgRouteParams = ['organizationId' => $organization['id'], 'slug' => $organization['slug']];
            $tabs = [
                'posts' => ['label' => 'Posts', 'url' => route('organization-feed', $orgRouteParams)],
                'about' => ['label' => 'About', 'url' => null],
                'members' => ['label' => 'Members', 'url' => route('organization-members', $orgRouteParams)],
                'events' => ['label' => 'Events', 'url' => route('organization-events', $orgRouteParams)],
            ];
        @endphp

        <section class="rounded-3xl bg-[color:var(--brand-cream)] p-6 ring-1 ring-emerald-100 sm:p-8 dark:ring-emerald-900/40">
            <div class="flex flex-col items-start gap-5 sm:flex-row sm:items-center">
                <div class="org-avatar h-20 w-20 text-2xl shadow-md">
                    @if (!empty($organization['logo_url']))
                        <img src="{{ $organization['logo_url'] }}" alt="{{ $organization['name'] }} logo"
                            onerror="this.style.display='none';this.nextElementSibling.style.display='inline';" />
                        <span style="display:none;">{{ $orgInitials }}</span>
                    @else
                        {{ $orgInitials }}
                    @endif
                </div>
                <div class="flex-1">
                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-emerald-700 dark:text-emerald-400">Organization Page</p>
                    <h1 class="brand-font mt-1 text-3xl text-slate-800 sm:text-4xl dark:text-slate-100">{{ $organization['name'] }}</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        {{ $memberCount }} member{{ $memberCount !== 1 ? 's' : '' }}
                        <span aria-hidden="true" class="mx-1">·</span>
                        {{ $publishedPostCount }} published post{{ $publishedPostCount !== 1 ? 's' : '' }}
                    </p>
                </div>
            </div>

            {{-- Tabs --}}
            <nav aria-label="Organization sections" class="mt-6 flex border-b border-emerald-100 dark:border-emerald-900/40">
                @foreach ($tabs as $tabKey => $tab)
                    @if ($tabKey === $activeTab)
                        <a href="{{ $tab['url'] }}" aria-current="page"
                            class="-mb-px border-b-2 border-emerald-600 px-4 py-2 text-sm font-semibold text-emerald-700 dark:border-emerald-400 dark:text-emerald-400">{{ $tab['label'] }}</a>
                    @elseif ($tab['url'])
                        <a href="{{ $tab['url'] }}"
                            class="px-4 py-2 text-sm font-semibold text-slate-500 transition hover:text-emerald-700 dark:text-slate-400 dark:hover:text-emerald-300">{{ $tab['label'] }}</a>
                    @else
                        <span aria-disabled="true" title="Coming soon"
                            class="cursor-not-allowed px-4 py-2 text-sm font-semibold text-slate-300 dark:text-slate-600">{{ $tab['label'] }}</span>
                    @endif
                @endforeach
            </nav>
        </section>

        @if ($activeTab === 'members')
            @include('landingPage.partials.org-members')
        @elseif ($activeTab === 'events')
            @include('landingPage.partials.org-events')
        @else
        {{-- ── Post Grid ── --}}
        <section class="mt-8">
            @if ($posts->isEmpty())
                <div class="rounded-2xl border border-dashed border-emerald-200 bg-white p-10 text-center dark:border-emerald-900/50 dark:bg-[#16241d]">
                    <svg class="mx-auto mb-3 h-10 w-10 text-emerald-200 dark:text-emerald-800" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12" />
                    </svg>
                    <p class="text-sm font-semibold text-slate-400 dark:text-slate-500">No posts yet for this organization.</p>
                </div>
            @else
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($posts as $post)
                        @php
                            $postDate = $post->published_at ?? $post->created_at;
                            $viewerPost = [
                                'id' => $post->post_id,
                                'title' => $post->title,
                                'excerpt' => $post->excerpt,
                                'body' => $post->body,
                                'tag' => $post->tag,
                                'featured' => (bool) $post->is_featured,
                                'date' => $postDate?->format('F j, Y \a\t g:i A'),
                                'dateIso' => $postDate?->toIso8601String(),
                                'images' => array_map(fn ($path) => '/storage/' . $path, $post->imagePaths()),
                                'video' => $post->video_path ? '/storage/' . $post->video_path : null,
                            ];
                        @endphp
                        <article
                            data-post-id="{{ $post->post_id }}"
                            @click="if (!$event.target.closest('video, button')) openPost({{ Js::from($viewerPost) }}, $el.querySelector('[data-open-post]'))"
                            class="post-card relative flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-[color:var(--card-bg)] shadow-sm dark:border-slate-700">

                            {{-- Media --}}
                            @if ($post->video_path)
                                <div class="aspect-video w-full overflow-hidden bg-slate-900 dark:bg-black">
                                    <video src="{{ '/storage/' . $post->video_path }}" class="h-full w-full object-cover"
                                        preload="metadata" controls playsinline>
                                    </video>
                                </div>
                            @elseif ($post->image_path)
                                <figure class="overflow-hidden">
                                    <img src="{{ '/storage/' . $post->image_path }}" alt="{{ $post->title }}"
                                        class="h-48 w-full object-cover" loading="lazy"
                                        onerror="this.parentElement.innerHTML='<div class=\'h-48 w-full flex flex-col items-center justify-center gap-2 bg-slate-100 text-slate-300 dark:bg-[#1c2c24] dark:text-slate-600\'><svg class=\'h-8 w-8\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\' stroke-width=\'1.5\'><rect x=\'3\' y=\'3\' width=\'18\' height=\'18\' rx=\'2\'/><circle cx=\'8.5\' cy=\'8.5\' r=\'1.5\'/><polyline points=\'21 15 16 10 5 21\'/></svg><span class=\'text-xs font-semibold uppercase tracking-wide\'>Image unavailable</span></div>';">
                                </figure>
                            @else
                                {{-- Decorative gradient placeholder --}}
                                <div
                                    class="h-28 w-full bg-gradient-to-br from-emerald-50 via-[color:var(--brand-mint)] to-emerald-100 flex items-end px-5 pb-4 dark:from-emerald-950 dark:to-emerald-900">
                                    <span
                                        class="text-xs font-bold uppercase tracking-widest text-emerald-600 opacity-60 dark:text-emerald-300">{{ $post->tag ?? 'Update' }}</span>
                                </div>
                            @endif

                            {{-- Content --}}
                            <div class="flex flex-1 flex-col p-5">
                                {{-- Org row --}}
                                <div class="mb-3 flex items-center gap-2">
                                    <div class="org-avatar h-7 w-7 text-[0.6rem]">{{ $orgInitials }}</div>
                                    <span
                                        class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ $organization['name'] }}</span>
                                    @if (!$post->video_path && !$post->image_path)
                                    @else
                                        <span
                                            class="ml-auto text-xs font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-400">{{ $post->tag ?? 'Update' }}</span>
                                    @endif
                                </div>

                                <h2 class="text-base font-bold leading-snug text-slate-800 dark:text-slate-100">{{ $post->title }}</h2>
                                <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-500 line-clamp-3 dark:text-slate-400">
                                    {{ $post->excerpt }}</p>

                                <div class="mt-4 flex items-center justify-between text-xs text-slate-400 dark:text-slate-500">
                                    @if ($post->is_featured)
                                        <span class="inline-flex items-center gap-1 font-medium text-amber-500">
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor">
                                                <path
                                                    d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                            </svg>
                                            Featured
                                        </span>
                                    @else
                                        <span></span>
                                    @endif
                                    <time>{{ $post->published_at?->diffForHumans() ?? $post->created_at?->diffForHumans() }}</time>
                                </div>
                            </div>

                            <button type="button" data-open-post
                                @click.stop="openPost({{ Js::from($viewerPost) }}, $el)"
                                aria-label="View post: {{ $post->title }}"
                                class="mx-5 mb-5 rounded-lg border border-emerald-200 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-900/30">
                                View post
                                @if (count($post->imagePaths()) > 1)
                                    <span class="ml-2 text-xs">({{ count($post->imagePaths()) }} images)</span>
                                @endif
                            </button>

                            {{-- Hover overlay --}}
                            <div
                                class="card-overlay pointer-events-none absolute inset-0 flex items-center justify-center bg-white/70 backdrop-blur-[2px] dark:bg-[#0d1613]/70">
                                <span
                                    class="translate-y-1 rounded-full border border-emerald-200 bg-white px-5 py-2 text-sm font-semibold text-emerald-700 shadow-sm transition group-hover:translate-y-0 dark:border-emerald-800 dark:bg-[#16241d] dark:text-emerald-300">
                                    Read More
                                </span>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
        @endif
    </main>
    @include('landingPage.partials.post-viewer')
</body>

</html>
