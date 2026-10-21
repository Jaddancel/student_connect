<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>SoConnect</title>
    <!-- Favicon-->
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
    <!-- Font Awesome icons (free version)-->
    {{-- data-auto-add-css="false": Font Awesome would otherwise inject an un-nonced <style>
         (blocked by the CSP); its stylesheet is linked statically instead. --}}
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.3/css/svg-with-js.css" integrity="sha384-C4KLoR3asMHYArL0nLQXEaFZIFfRMiV0Ul0DvsMfSMZ+YLJwFu0Rpxix+EZwqxOy" crossorigin="anonymous" />
    <script src="https://use.fontawesome.com/releases/v5.15.3/js/all.js" integrity="sha384-haqrlim99xjfMxRP6EWtafs0sB1WKcMdynwZleuUSwJR0mDeRYbhtY+KPMr+JL6f" crossorigin="anonymous" data-auto-add-css="false"></script>
    <!-- Core theme CSS (includes Bootstrap)-->
    @vite(['resources/css/landingPage.css'])

    <!-- Shared light/dark bootstrap (same localStorage key as the dashboard) -->
    @include('layouts.partials.theme-boot')

    {{-- Broken-image fallback (replaces inline onerror handlers, which the CSP blocks):
         hide the image and show the element right after it. Capture phase, since
         image `error` events don't bubble. --}}
    <script @nonce>
        if (!window.__landingImgFallbackBound) {
            window.__landingImgFallbackBound = true;
            document.addEventListener('error', function (event) {
                var img = event.target;
                if (img instanceof HTMLImageElement && img.hasAttribute('data-error-reveal-next')) {
                    img.style.display = 'none';
                    img.nextElementSibling.style.display = img.getAttribute('data-error-reveal-next');
                }
            }, true);
        }
    </script>
</head>

<body id="page-top" class="landing-page">
    @php
        $organizationSlugMap = collect($organizationsByType)
            ->flatten(1)
            ->mapWithKeys(fn($organization) => [$organization['id'] => $organization['slug']])
            ->all();
    @endphp

    <!-- Navigation-->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav">
        <div class="container">
            <a class="navbar-brand" href="#page-top" aria-label="SO Connect home">
                <img src="{{ asset('images/brand/student-organization-logo-light.svg') }}" alt="SO Connect"
                    width="114" height="40">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarResponsive"
                aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
                Menu
                <i class="fas fa-bars ms-1"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarResponsive">
                <ul class="navbar-nav text-uppercase ms-auto py-4 py-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="#page-top">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#portfolio">Featured Org</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#events">Upcoming Events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#activity">Recent Activities</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('forms.render', 'new-organization-registration') }}">Register Organization</a>
                    </li>
                    <li class="nav-item ms-lg-3 d-flex align-items-center gap-2">
                        <a class="nav-link btn btn-sm text-uppercase lp-login-btn" href="{{ route('login') }}">Sign In</a>
                        <x-theme-toggle class="lp-theme-toggle" />
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <!-- Masthead-->
    <header class="masthead"
        style="background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('{{ asset('images/landing/BG1.jpg') }}');">
        <div class="container">
            <div class="masthead-subheading">Your student community starts here</div>
            <div class="masthead-heading text-uppercase">Discover. Participate. Lead.</div>
            <p class="masthead-intro">Explore accredited organizations, upcoming events, and campus initiatives in one connected student hub.</p>
            <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                <a class="btn btn-primary btn-xl text-uppercase" href="#categories">Explore Organizations</a>
                <a class="btn btn-outline-light btn-xl text-uppercase" href="{{ route('login') }}">Sign In to SO Connect</a>
            </div>
        </div>
    </header>
    <!-- Organization Categories -->
    <section class="page-section" id="categories">
        <div class="container">
            <div class="text-center">
                <h2 class="section-heading text-uppercase">Organization Categories</h2>
                <h3 class="section-subheading text-muted">Discover the different avenues for student involvement on
                    campus. <em>Hover a card to browse organizations.</em></h3>
            </div>
            @php
                $categoryMeta = [
                    1 => [
                        'icon' => 'hands-helping',
                        'description' =>
                            'Engage in community service, environmental advocacy, and social awareness campaigns.',
                    ],
                    2 => [
                        'icon' => 'praying-hands',
                        'description' =>
                            'Connect with faith-centered communities and events that nurture shared values.',
                    ],
                    3 => [
                        'icon' => 'user-friends',
                        'description' => 'Build lifelong bonds through brotherhood, sisterhood, and shared traditions.',
                    ],
                    4 => [
                        'icon' => 'palette',
                        'description' => 'Explore creativity, hobbies, and special interests beyond the classroom.',
                    ],
                    5 => [
                        'icon' => 'university',
                        'description' => 'Join university-backed groups that represent campus pride and tradition.',
                    ],
                    6 => [
                        'icon' => 'landmark',
                        'description' =>
                            'Lead student initiatives and represent your peers across university councils.',
                    ],
                ];
            @endphp
            <div class="row g-4">
                @foreach ($organizationTypes as $typeKey => $typeName)
                    @if (!empty($organizationsByType[$typeKey]))
                        @php
                            $meta = $categoryMeta[$typeKey] ?? [
                                'icon' => 'users',
                                'description' =>
                                    'Connect with student leaders, mentors, and campus-wide opportunities.',
                            ];
                            $orgsInType = $organizationsByType[$typeKey];
                        @endphp
                        <div class="col-md-6 col-lg-4">
                            <div class="cat-flip">
                                <div class="cat-flip-inner">

                                    {{-- ── FRONT ── --}}
                                    <div class="cat-face cat-face--front">
                                        <span class="fa-stack fa-4x cat-icon-stack">
                                            <i class="fas fa-circle fa-stack-2x cat-icon-circle"></i>
                                            <i class="fas fa-{{ $meta['icon'] }} fa-stack-1x fa-inverse"></i>
                                        </span>
                                        <h4 class="cat-front-title">{{ $typeName }}</h4>
                                        <p class="cat-front-desc">{{ $meta['description'] }}</p>
                                        <span class="cat-count-badge">{{ count($orgsInType) }}
                                            {{ count($orgsInType) === 1 ? 'organization' : 'organizations' }}</span>
                                    </div>

                                    {{-- ── BACK ── --}}
                                    <div class="cat-face cat-face--back">
                                        <div class="cat-back-header">
                                            <i class="fas fa-{{ $meta['icon'] }} cat-back-icon"></i>
                                            <span class="cat-back-label">{{ $typeName }}</span>
                                        </div>
                                        <div class="cat-org-list">
                                            @foreach ($orgsInType as $org)
                                                <a href="{{ route('organization-feed', ['organizationId' => $org['id'], 'slug' => $org['slug']]) }}"
                                                    class="cat-org-link">
                                                    <span class="cat-org-name">{{ $org['name'] }}</span>
                                                    <svg class="cat-org-arrow" width="16" height="16"
                                                        viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                                        stroke-width="2.5" stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                        <path d="M4 10h12M12 5l5 5-5 5" />
                                                    </svg>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </section>
    <!-- Featured Organizations Grid-->
    <section class="page-section bg-light" id="portfolio">
        <div class="container">
            <div class="text-center">
                <h2 class="section-heading text-uppercase">Featured Organizations</h2>
                <h3 class="section-subheading text-muted">Get a glimpse of our vibrant student life.</h3>
            </div>
            @if ($topFeed->isEmpty())
                <div class="empty-state">No featured organizations yet. Check back soon for updates.</div>
            @else
                <div class="row g-4">
                    @foreach ($topFeed as $organization)
                        @php
                            $orgInitials = collect(preg_split('/\s+/', $organization['name']))
                                ->filter(fn($word) => strlen($word) > 2)
                                ->take(2)
                                ->map(fn($word) => strtoupper($word[0]))
                                ->implode('');
                        @endphp
                        <div class="col-md-6 col-lg-4">
                            <article class="feature-card">
                                <a class="feature-card-link"
                                    href="{{ route('organization-feed', ['organizationId' => $organization['id'], 'slug' => $organization['slug']]) }}">
                                    <div class="feature-card-media">
                                        @if (!empty($organization['logo_url']))
                                            <img class="feature-card-image" src="{{ $organization['logo_url'] }}"
                                                alt="{{ $organization['name'] }} logo" loading="lazy"
                                                data-error-reveal-next="flex" />
                                            <div class="feature-card-initials" style="display:none;">
                                                {{ $orgInitials }}</div>
                                        @else
                                            <div class="feature-card-initials">{{ $orgInitials }}</div>
                                        @endif
                                    </div>
                                    <span class="feature-card-tag">{{ $organization['announcement_tag'] }}</span>
                                    <h4 class="feature-card-title">{{ $organization['announcement_title'] }}</h4>
                                    <p class="feature-card-text">{{ $organization['announcement_excerpt'] }}</p>
                                    <div class="feature-card-footer">
                                        <span>{{ $organization['name'] }}</span>
                                        <span>{{ $organization['announcement_time'] }}</span>
                                    </div>
                                </a>
                            </article>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
    <!-- Featured Posts -->
    <section class="page-section" id="featured-posts">
        <div class="container">
            <div class="text-center">
                <h2 class="section-heading text-uppercase">Featured Posts</h2>
                <h3 class="section-subheading text-muted">Top highlights from student organization announcements.</h3>
            </div>
            @if ($featuredPosts->isEmpty())
                <div class="empty-state">No featured posts yet. New updates will appear here soon.</div>
            @else
                <div class="row g-4">
                    @foreach ($featuredPosts as $post)
                        @php
                            $organizationName = $organizationNameMap[$post->organization] ?? 'Unknown Organization';
                            $organizationSlug = $organizationSlugMap[$post->organization] ?? null;
                        @endphp
                        <div class="col-md-6 col-lg-4">
                            <article class="post-card">
                                <div class="post-card-media">
                                    @if (!empty($post->image_path))
                                        <img src="{{ '/storage/' . $post->image_path }}" alt="{{ $post->title }}"
                                            loading="lazy"
                                            data-error-reveal-next="flex" />
                                        <div class="img-placeholder" style="display:none;">
                                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                stroke-width="1.5">
                                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                                <circle cx="8.5" cy="8.5" r="1.5" />
                                                <polyline points="21 15 16 10 5 21" />
                                            </svg>
                                            <span>Image unavailable</span>
                                        </div>
                                    @else
                                        <div class="img-placeholder">
                                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                stroke-width="1.5">
                                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                                <circle cx="8.5" cy="8.5" r="1.5" />
                                                <polyline points="21 15 16 10 5 21" />
                                            </svg>
                                            <span>No image</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="post-card-body">
                                    <div class="post-card-meta">
                                        <span>{{ $post->tag ?? 'Update' }}</span>
                                        <span>
                                            {{ $post->published_at?->format('M d, Y') ?? $post->created_at?->format('M d, Y') }}
                                        </span>
                                    </div>
                                    <h4 class="post-card-title">{{ $post->title }}</h4>
                                    <p class="post-card-text">{{ $post->excerpt }}</p>
                                    <div class="post-card-footer">
                                        <span>{{ $organizationName }}</span>
                                        <a
                                            href="{{ route('organization-feed', ['organizationId' => $post->organization, 'slug' => $organizationSlug]) }}">View
                                            feed</a>
                                    </div>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
    <!-- Upcoming Events (NEW) -->
    <section class="page-section" id="events">
        <div class="container">
            <div class="text-center">
                <div>
                    <i class="fas fa-calendar-alt fa-2x events-icon"></i>
                </div>
                <h2 class="section-heading text-uppercase events-heading">Upcoming Events</h2>
                <h3 class="section-subheading text-muted">Don't miss out on what's happening around the university.
                </h3>
            </div>
            <div class="row text-center">
                @php
                    $eventColors = [
                        ['card' => 'event-gold',    'header' => 'gold-gradient',    'date' => 'gold'],
                        ['card' => 'event-emerald',  'header' => 'emerald-gradient', 'date' => 'emerald'],
                        ['card' => 'event-blue',     'header' => 'blue-gradient',    'date' => 'blue'],
                    ];
                @endphp
                @forelse ($recentActivities as $index => $activity)
                    @php $colors = $eventColors[$index % 3]; @endphp
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 shadow-lg border-0 event-card {{ $colors['card'] }}">
                            <div class="event-header {{ $colors['header'] }}">
                                <i class="fas fa-calendar-check fa-3x"></i>
                            </div>
                            <div class="card-body">
                                <h4 class="card-title my-3 event-title">{{ $activity->event_name }}</h4>
                                <div class="event-details">
                                    <i class="fas fa-calendar"></i>
                                    <h6 class="card-subtitle mb-0 event-date {{ $colors['date'] }}">
                                        {{ \Carbon\Carbon::parse($activity->start_time)->format('M d, Y') }}
                                        @if ($activity->event_location) &mdash; {{ $activity->event_location }} @endif
                                    </h6>
                                </div>
                                <p class="card-text text-muted event-description">{{ $activity->event_description }}</p>
                                <div class="text-muted small mt-2">
                                    <i class="fas fa-building me-1"></i>{{ $activity->organization_name }}
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-center text-muted py-4">No upcoming events at this time.</div>
                @endforelse
            </div>
        </div>
    </section>
    <!-- Recent Activity Section -->
    <section class="page-section bg-light" id="activity">
        <div class="container">
            <div class="text-center mb-5">
                <i class="fas fa-history fa-2x" style="color: var(--lp-accent); margin-bottom: 20px;"></i>
                <h2 class="section-heading text-uppercase">Recent Activities</h2>
            </div>
            <div class="row g-4">
                @forelse ($recentActivities as $activity)
                <div class="col-lg-4 col-md-6">
                    <div class="activity-card"
                        style="overflow: hidden; border-radius: 10px; background: var(--lp-surface); box-shadow: 0 5px 20px var(--lp-shadow); transition: transform 0.3s ease;">
                        <div
                            style="background: linear-gradient(135deg, #2D6A4F 0%, #1A3C2E 100%); height: 200px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-calendar-check fa-5x" style="color: rgba(255,255,255,0.8);"></i>
                        </div>
                        <div style="padding: 20px;">
                            <div style="color: var(--lp-accent); font-size: 0.8rem; font-weight: 600; margin-bottom: 8px;">
                                <i class="fas fa-calendar-day"></i>
                                {{ \Carbon\Carbon::parse($activity->start_time)->format('M j, Y') }}
                            </div>
                            <h5 style="margin: 0; font-weight: 700; color: var(--lp-heading);">{{ $activity->event_name }}</h5>
                            <p style="margin: 4px 0 0; font-size: 0.8rem; color: var(--lp-muted);">{{ $activity->organization_name }}</p>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-12 text-center text-muted py-4">
                    <i class="fas fa-calendar-times fa-2x mb-2" style="color: var(--lp-faint);"></i>
                    <p style="color: var(--lp-subtle);">No recent activities yet.</p>
                </div>
                @endforelse
            </div>
        </div>
    </section>
    <!-- Contact / Footer -->
    <section class="page-section" id="contact"
        style="background: linear-gradient(135deg, #1A3C2E 0%, #2D6A4F 100%); position: relative; overflow: hidden;">
        <div
            style="position: absolute; top: -50px; right: -50px; width: 300px; height: 300px; background: rgba(255,255,255,0.05); border-radius: 50%;">
        </div>
        <div
            style="position: absolute; bottom: -80px; left: -80px; width: 400px; height: 400px; background: rgba(255,255,255,0.03); border-radius: 50%;">
        </div>
        <div class="container" style="position: relative; z-index: 1;">
            <div class="text-center">
                <p style="color: rgba(255,255,255,0.9); font-size: 0.95rem; margin-bottom: 0;">&copy; 2026 SO Connect —
                    Tarlac Agricultural University. All rights reserved.</p>
            </div>
        </div>
    </section>
    <!-- Bootstrap core JS-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
    <!-- Core theme JS-->
    @vite(['resources/js/scripts.js'])

</body>

</html>
