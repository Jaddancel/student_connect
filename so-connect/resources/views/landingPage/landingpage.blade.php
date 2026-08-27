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
    <script src="https://use.fontawesome.com/releases/v5.15.3/js/all.js" integrity="sha384-haqrlim99xjfMxRP6EWtafs0sB1WKcMdynwZleuUSwJR0mDeRYbhtY+KPMr+JL6f" crossorigin="anonymous"></script>
    <!-- Google fonts-->
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700" rel="stylesheet" type="text/css" />
    <link href="https://fonts.googleapis.com/css?family=Roboto+Slab:400,100,300,700" rel="stylesheet" type="text/css" />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Lato:wght@300;400;700&display=swap" rel="stylesheet" />
    <!-- Core theme CSS (includes Bootstrap)-->
    @vite(['resources/css/landingPage.css'])

    <!-- Shared light/dark bootstrap (same localStorage key as the dashboard) -->
    @include('layouts.partials.theme-boot')
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
            <a class="navbar-brand" href="#page-top" style="font-family: 'Roboto Slab', serif; font-weight: 700;"> SO
                Connect </a>
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
                    {{-- Login + theme toggle share one nav-item so the toggle stays
                         beside the button instead of dropping to its own row when
                         the navbar collapses. --}}
                    <li class="nav-item ms-lg-3 d-flex align-items-center gap-2">
                        <a class="nav-link btn btn-sm text-uppercase lp-login-btn" href="#auth">Login
                            / Sign Up</a>
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
            <div class="masthead-subheading">Welcome to SO Connect!</div>
            <div class="masthead-heading text-uppercase">Find Your Community</div>
            <a class="btn btn-primary btn-xl text-uppercase" href="#categories">Explore Organizations</a>
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
                                                onerror="this.style.display='none';this.nextElementSibling.style.display='flex';" />
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
                                            onerror="this.style.display='none';this.nextElementSibling.style.display='flex';" />
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
    <!-- Embedded Login Section -->
    <section id="auth" style="
        min-height: 100vh;
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: stretch;
    ">
        {{-- Left atmospheric panel --}}
        <div class="auth-left-panel d-none d-lg-flex" style="
            flex: 0 0 52%;
            background:
                radial-gradient(ellipse at 30% 20%, rgba(201,168,76,0.18) 0%, transparent 55%),
                radial-gradient(ellipse at 80% 80%, rgba(45,106,79,0.4) 0%, transparent 60%),
                linear-gradient(160deg, #0f2318 0%, #1A3C2E 40%, #2D6A4F 100%);
            position: relative;
            overflow: hidden;
            flex-direction: column;
            justify-content: center;
            padding: 80px 70px;
        ">
            {{-- Decorative geometric lines --}}
            <div style="position:absolute;top:0;left:0;right:0;bottom:0;pointer-events:none;overflow:hidden;">
                <svg width="100%" height="100%" viewBox="0 0 600 800" preserveAspectRatio="xMidYMid slice" style="position:absolute;top:0;left:0;opacity:0.07;">
                    <line x1="0" y1="200" x2="600" y2="600" stroke="#C9A84C" stroke-width="1"/>
                    <line x1="0" y1="400" x2="600" y2="0" stroke="#C9A84C" stroke-width="0.5"/>
                    <line x1="100" y1="0" x2="100" y2="800" stroke="#C9A84C" stroke-width="0.5"/>
                    <line x1="500" y1="0" x2="500" y2="800" stroke="#C9A84C" stroke-width="0.5"/>
                    <circle cx="300" cy="400" r="280" stroke="#C9A84C" stroke-width="0.5" fill="none"/>
                    <circle cx="300" cy="400" r="180" stroke="#C9A84C" stroke-width="0.3" fill="none"/>
                    <rect x="60" y="60" width="480" height="680" stroke="#C9A84C" stroke-width="0.5" fill="none" rx="4"/>
                </svg>
                {{-- Bottom-right glow --}}
                <div style="position:absolute;bottom:-100px;right:-100px;width:400px;height:400px;background:radial-gradient(circle,rgba(201,168,76,0.15),transparent 70%);border-radius:50%;"></div>
            </div>

            {{-- Gold rule --}}
            <div style="width:48px;height:3px;background:#C9A84C;margin-bottom:36px;position:relative;z-index:1;"></div>

            {{-- Headline --}}
            <h2 style="
                font-family: 'Playfair Display', Georgia, serif;
                font-size: clamp(2.4rem, 3.5vw, 3.2rem);
                font-weight: 700;
                color: #fff;
                line-height: 1.18;
                letter-spacing: -0.01em;
                margin-bottom: 24px;
                position: relative;
                z-index: 1;
            ">
                Your community<br>
                <em style="color:#C9A84C;font-style:italic;">starts here.</em>
            </h2>

            <p style="
                font-family: 'Lato', sans-serif;
                font-size: 1.05rem;
                font-weight: 300;
                color: rgba(255,255,255,0.72);
                line-height: 1.75;
                max-width: 380px;
                margin-bottom: 48px;
                position: relative;
                z-index: 1;
            ">
                SO Connect is the official student organization management platform of Tarlac Agricultural University — where leaders are recognized, events are organized, and communities thrive.
            </p>

            {{-- Feature pills --}}
            <div style="display:flex;flex-direction:column;gap:14px;position:relative;z-index:1;">
                @php
                $features = [
                    ['icon'=>'fa-users','text'=>'46 recognized student organizations'],
                    ['icon'=>'fa-calendar-check','text'=>'Activity planning & approvals'],
                    ['icon'=>'fa-id-badge','text'=>'Digital officer directory'],
                ];
                @endphp
                @foreach($features as $f)
                <div style="display:flex;align-items:center;gap:14px;">
                    <div style="
                        width:36px;height:36px;border-radius:50%;
                        background:rgba(201,168,76,0.15);
                        border:1px solid rgba(201,168,76,0.35);
                        display:flex;align-items:center;justify-content:center;
                        flex-shrink:0;
                    ">
                        <i class="fas {{ $f['icon'] }}" style="color:#C9A84C;font-size:0.8rem;"></i>
                    </div>
                    <span style="font-family:'Lato',sans-serif;font-size:0.9rem;color:rgba(255,255,255,0.65);font-weight:300;">{{ $f['text'] }}</span>
                </div>
                @endforeach
            </div>

            {{-- Bottom logo/seal --}}
            <div style="position:absolute;bottom:40px;left:70px;z-index:1;display:flex;align-items:center;gap:10px;opacity:0.45;">
                <div style="width:28px;height:28px;border-radius:50%;border:1px solid rgba(201,168,76,0.6);display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-leaf" style="color:#C9A84C;font-size:0.65rem;"></i>
                </div>
                <span style="font-family:'Lato',sans-serif;font-size:0.75rem;color:rgba(255,255,255,0.6);letter-spacing:0.12em;text-transform:uppercase;font-weight:700;">Tarlac Agricultural University</span>
            </div>
        </div>

        {{-- Right form panel --}}
        <div style="
            flex: 1;
            background: var(--lp-surface-alt);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 80px 40px;
            position: relative;
        ">
            {{-- Subtle texture overlay --}}
            <div style="position:absolute;inset:0;background-image:url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%232D6A4F\' fill-opacity=\'0.025\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');pointer-events:none;"></div>

            <div style="width:100%;max-width:420px;position:relative;z-index:1;">

                {{-- Mobile-only top bar --}}
                <div class="d-lg-none" style="margin-bottom:32px;text-align:center;">
                    <div style="width:40px;height:3px;background:var(--lp-gold);margin:0 auto 16px;"></div>
                    <p style="font-family:'Lato',sans-serif;font-size:0.8rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--lp-accent);font-weight:700;">SO Connect</p>
                </div>

                {{-- Form heading --}}
                <h3 style="
                    font-family: 'Playfair Display', Georgia, serif;
                    font-size: 2rem;
                    font-weight: 700;
                    color: var(--lp-heading);
                    margin-bottom: 6px;
                    line-height: 1.2;
                ">Welcome back</h3>
                <p style="font-family:'Lato',sans-serif;font-size:0.92rem;color:var(--lp-muted);margin-bottom:32px;font-weight:300;">Sign in to your account to continue.</p>

                {{-- Error message --}}
                @if ($errors->has('user_email'))
                    <div style="
                        background:var(--lp-error-bg);
                        border:1px solid var(--lp-error-border);
                        border-left:4px solid #dc3545;
                        border-radius:6px;
                        padding:12px 16px;
                        margin-bottom:22px;
                        display:flex;
                        align-items:flex-start;
                        gap:10px;
                    ">
                        <i class="fas fa-exclamation-circle" style="color:#dc3545;margin-top:2px;flex-shrink:0;font-size:0.85rem;"></i>
                        <p style="margin:0;font-family:'Lato',sans-serif;font-size:0.875rem;color:var(--lp-error-text);line-height:1.4;">
                            {{ $errors->first('user_email') }}
                        </p>
                    </div>
                @endif

                <form action="/login" method="post" id="auth-form">
                    @csrf

                    {{-- Email --}}
                    <div style="margin-bottom:20px;">
                        <label style="
                            display:block;
                            font-family:'Lato',sans-serif;
                            font-size:0.8rem;
                            font-weight:700;
                            color:var(--lp-heading);
                            letter-spacing:0.08em;
                            text-transform:uppercase;
                            margin-bottom:8px;
                        ">Email address</label>
                        <input
                            type="email"
                            name="user_email"
                            value="{{ old('user_email') }}"
                            placeholder="you@example.com"
                            autocomplete="email"
                            required
                            style="
                                width:100%;
                                padding:13px 16px;
                                border:1.5px solid {{ $errors->has('user_email') ? '#dc3545' : 'var(--lp-border-strong)' }};
                                border-radius:8px;
                                font-family:'Lato',sans-serif;
                                font-size:0.95rem;
                                color:var(--lp-heading);
                                background:var(--lp-surface);
                                outline:none;
                                transition:border-color 0.2s,box-shadow 0.2s;
                            "
                            onfocus="this.style.borderColor='var(--lp-accent)';this.style.boxShadow='0 0 0 3px rgba(45,106,79,0.12)'"
                            onblur="this.style.borderColor='{{ $errors->has('user_email') ? '#dc3545' : 'var(--lp-border-strong)' }}';this.style.boxShadow='none'"
                        />
                    </div>

                    {{-- Password --}}
                    <div style="margin-bottom:14px;">
                        <label style="
                            display:block;
                            font-family:'Lato',sans-serif;
                            font-size:0.8rem;
                            font-weight:700;
                            color:var(--lp-heading);
                            letter-spacing:0.08em;
                            text-transform:uppercase;
                            margin-bottom:8px;
                        ">Password</label>
                        <div style="position:relative;">
                            <input
                                type="password"
                                name="user_password"
                                id="auth-password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                                style="
                                    width:100%;
                                    padding:13px 48px 13px 16px;
                                    border:1.5px solid var(--lp-border-strong);
                                    border-radius:8px;
                                    font-family:'Lato',sans-serif;
                                    font-size:0.95rem;
                                    color:var(--lp-heading);
                                    background:var(--lp-surface);
                                    outline:none;
                                    transition:border-color 0.2s,box-shadow 0.2s;
                                "
                                onfocus="this.style.borderColor='var(--lp-accent)';this.style.boxShadow='0 0 0 3px rgba(45,106,79,0.12)'"
                                onblur="this.style.borderColor='var(--lp-border-strong)';this.style.boxShadow='none'"
                                onkeyup="document.getElementById('caps-warn').style.display=event.getModifierState('CapsLock')?'flex':'none'"
                                onkeydown="document.getElementById('caps-warn').style.display=event.getModifierState('CapsLock')?'flex':'none'"
                            />
                            <button
                                type="button"
                                id="auth-toggle-pw"
                                onclick="toggleAuthPassword()"
                                style="
                                    position:absolute;right:14px;top:50%;transform:translateY(-50%);
                                    background:none;border:none;cursor:pointer;padding:0;
                                    color:var(--lp-muted);display:flex;align-items:center;
                                "
                                tabindex="-1"
                            >
                                <i class="fas fa-eye" id="auth-eye-icon" style="font-size:0.95rem;"></i>
                            </button>
                        </div>
                        {{-- Caps lock warning --}}
                        <div id="caps-warn" style="display:none;align-items:center;gap:6px;margin-top:6px;">
                            <i class="fas fa-exclamation-triangle" style="color:#d97706;font-size:0.75rem;"></i>
                            <span style="font-family:'Lato',sans-serif;font-size:0.8rem;color:#92400e;">Caps Lock is on</span>
                        </div>
                    </div>

                    {{-- Forgot password --}}
                    <div style="text-align:right;margin-bottom:28px;">
                        <a href="/reset-password" style="
                            font-family:'Lato',sans-serif;
                            font-size:0.82rem;
                            color:var(--lp-accent);
                            text-decoration:none;
                            font-weight:700;
                            letter-spacing:0.02em;
                        ">Forgot password?</a>
                    </div>

                    {{-- Submit --}}
                    <button type="submit" style="
                        width:100%;
                        padding:15px 24px;
                        background: linear-gradient(135deg, #2D6A4F 0%, #1A3C2E 100%);
                        color:#fff;
                        border:none;
                        border-radius:8px;
                        font-family:'Lato',sans-serif;
                        font-size:0.9rem;
                        font-weight:700;
                        letter-spacing:0.1em;
                        text-transform:uppercase;
                        cursor:pointer;
                        transition:opacity 0.2s, transform 0.15s, box-shadow 0.2s;
                        box-shadow:0 4px 20px rgba(26,60,46,0.35);
                        position:relative;
                        overflow:hidden;
                    "
                    onmouseover="this.style.opacity='0.9';this.style.transform='translateY(-1px)';this.style.boxShadow='0 8px 28px rgba(26,60,46,0.45)'"
                    onmouseout="this.style.opacity='1';this.style.transform='translateY(0)';this.style.boxShadow='0 4px 20px rgba(26,60,46,0.35)'"
                    >
                        <i class="fas fa-sign-in-alt" style="margin-right:10px;"></i>
                        Sign In
                    </button>
                </form>

                {{-- Divider --}}
                <div style="display:flex;align-items:center;gap:14px;margin:28px 0;">
                    <div style="flex:1;height:1px;background:var(--lp-border-strong);"></div>
                    <span style="font-family:'Lato',sans-serif;font-size:0.8rem;color:var(--lp-subtle);font-weight:400;">New to SO Connect?</span>
                    <div style="flex:1;height:1px;background:var(--lp-border-strong);"></div>
                </div>

                {{-- Sign Up with Google — opens the OAuth popup, then continues to
                     the officer registration wizard with name/email pre-filled. --}}
                <button type="button" id="signup-google-btn" onclick="startGoogleSignup()" style="
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    gap:10px;
                    width:100%;
                    padding:13px 24px;
                    margin-bottom:12px;
                    background:var(--lp-surface);
                    color:var(--lp-google-text);
                    border:1.5px solid var(--lp-border-strong);
                    border-radius:8px;
                    font-family:'Lato',sans-serif;
                    font-size:0.9rem;
                    font-weight:700;
                    letter-spacing:0.02em;
                    cursor:pointer;
                    transition:background 0.2s,box-shadow 0.2s;
                "
                onmouseover="this.style.background='var(--lp-google-hover)'"
                onmouseout="this.style.background='var(--lp-surface)'"
                >
                    <i class="fab fa-google" style="color:#4285F4;font-size:1rem;"></i>
                    Sign Up with Google
                </button>
                <p id="signup-google-status" style="display:none;margin:0 0 12px;font-family:'Lato',sans-serif;font-size:0.8rem;text-align:center;"></p>

                {{-- Sign Up CTA --}}
                <a href="/signup" style="
                    display:block;
                    width:100%;
                    padding:14px 24px;
                    background:transparent;
                    color:var(--lp-accent);
                    border:1.5px solid var(--lp-accent);
                    border-radius:8px;
                    font-family:'Lato',sans-serif;
                    font-size:0.9rem;
                    font-weight:700;
                    letter-spacing:0.08em;
                    text-transform:uppercase;
                    text-align:center;
                    text-decoration:none;
                    transition:background 0.2s,color 0.2s;
                "
                onmouseover="this.style.background='var(--lp-accent)';this.style.color='var(--lp-accent-contrast)'"
                onmouseout="this.style.background='transparent';this.style.color='var(--lp-accent)'"
                >
                    <i class="fas fa-user-plus" style="margin-right:8px;"></i>
                    Register as Officer
                </a>

            </div>
        </div>
    </section>

    <script>
    function toggleAuthPassword() {
        var input = document.getElementById('auth-password');
        var icon  = document.getElementById('auth-eye-icon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
    @if ($errors->any())
    document.addEventListener('DOMContentLoaded', function () {
        var authSection = document.getElementById('auth');
        if (authSection) {
            setTimeout(function () {
                authSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 120);
        }
    });
    @endif

    // "Sign Up with Google" — opens the stateless Google OAuth popup (shared
    // with the admin/officer account-creation forms), then hands the
    // applicant's name/email off to the officer registration wizard
    // (/signup) as query params for pre-fill. Never logs the visitor in here.
    function startGoogleSignup() {
        var status = document.getElementById('signup-google-status');
        status.style.display = 'block';

        if (!@json(\App\Http\Controllers\Auth\GoogleLinkController::isConfigured())) {
            status.style.color = '#dc3545';
            status.textContent = 'Google sign-in is not configured yet. See GOOGLE-AUTH-SETUP.md.';
            return;
        }

        status.style.color = 'var(--lp-muted)';
        status.textContent = 'Opening Google…';

        var w = 500, h = 600;
        var left = window.screenX + (window.outerWidth - w) / 2;
        var top = window.screenY + (window.outerHeight - h) / 2;
        window.open(
            '{{ route('admin.accounts.google.redirect') }}',
            'google-signup',
            'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top
        );
    }

    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin) { return; }
        var data = event.data || {};
        if (data.source !== 'google-link') { return; }

        var status = document.getElementById('signup-google-status');
        if (data.error) {
            status.style.display = 'block';
            status.style.color = '#dc3545';
            status.textContent = data.error;
            return;
        }

        var params = new URLSearchParams({
            google_id: data.google_id || '',
            first_name: data.first_name || '',
            last_name: data.last_name || '',
            email: data.email || '',
        });
        window.location.href = '{{ route('signup') }}?' + params.toString();
    });
    </script>
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
    <script src="https://cdn.startbootstrap.com/sb-forms-latest.js"></script>

    @if ($errors->has('user_email'))
    <div id="login-toast" style="
        position:fixed;
        bottom:24px;
        right:24px;
        z-index:9999;
        min-width:300px;
        max-width:380px;
        background:var(--lp-surface);
        border-radius:10px;
        box-shadow:0 8px 32px rgba(0,0,0,0.18);
        border-left:4px solid #dc3545;
        padding:16px 20px;
        display:flex;
        align-items:flex-start;
        gap:12px;
        animation:toastSlideIn 0.35s cubic-bezier(0.16,1,0.3,1);
        font-family:'Lato',sans-serif;
    ">
        <i class="fas fa-exclamation-circle" style="color:#dc3545;font-size:1.1rem;margin-top:2px;flex-shrink:0;"></i>
        <div style="flex:1;">
            <div style="font-weight:700;color:var(--lp-heading);font-size:0.9rem;margin-bottom:4px;">Login Failed</div>
            <div style="color:var(--lp-muted);font-size:0.85rem;line-height:1.4;">{{ $errors->first('user_email') }}</div>
        </div>
        <button onclick="dismissLoginToast()" style="
            background:none;border:none;cursor:pointer;padding:0;
            color:var(--lp-subtle);font-size:1.25rem;flex-shrink:0;line-height:1;margin-top:1px;
        " aria-label="Dismiss">&times;</button>
    </div>
    <style>
    @keyframes toastSlideIn {
        from { opacity:0; transform:translateX(48px); }
        to   { opacity:1; transform:translateX(0); }
    }
    </style>
    <script>
    function dismissLoginToast() {
        var t = document.getElementById('login-toast');
        if (!t) return;
        t.style.transition = 'opacity 0.35s, transform 0.35s';
        t.style.opacity = '0';
        t.style.transform = 'translateX(48px)';
        setTimeout(function() { t.style.display = 'none'; }, 350);
    }
    setTimeout(dismissLoginToast, 6000);
    </script>
    @endif
</body>

</html>
