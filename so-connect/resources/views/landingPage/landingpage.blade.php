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
    <script src="https://use.fontawesome.com/releases/v5.15.3/js/all.js" crossorigin="anonymous"></script>
    <!-- Google fonts-->
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700" rel="stylesheet" type="text/css" />
    <link href="https://fonts.googleapis.com/css?family=Roboto+Slab:400,100,300,700" rel="stylesheet" type="text/css" />
    <!-- Core theme CSS (includes Bootstrap)-->
    @vite(['resources/css/landingPage.css'])
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
                    <li class="nav-item ms-lg-3">
                        <a class="nav-link btn btn-sm text-uppercase" href="{{ route('login') }}"
                            style="padding: 8px 20px; border-radius: 20px; font-weight: 600; background-color: #C9A84C; color: white; transition: all 0.3s ease; display: inline-block;">Login
                            / Sign Up</a>
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
                <!-- Event 1 -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100 shadow-lg border-0 event-card event-gold">
                        <div class="event-header gold-gradient">
                            <i class="fas fa-users fa-3x"></i>
                        </div>
                        <div class="card-body">
                            <h4 class="card-title my-3 event-title">Annual Org Fair</h4>
                            <div class="event-details">
                                <i class="fas fa-calendar"></i>
                                <h6 class="card-subtitle mb-0 event-date gold">August 15 - University Quadrangle</h6>
                            </div>
                            <p class="card-text text-muted event-description">Explore all recognized student
                                organizations, sign up for memberships, and watch live performances.</p>
                            <a href="#" class="event-link gold">Learn More <i class="fas fa-arrow-right"
                                    style="margin-left: 5px;"></i></a>
                        </div>
                    </div>
                </div>
                <!-- Event 2 -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100 shadow-lg border-0 event-card event-emerald">
                        <div class="event-header emerald-gradient">
                            <i class="fas fa-graduation-cap fa-3x"></i>
                        </div>
                        <div class="card-body">
                            <h4 class="card-title my-3 event-title">Leadership Training Seminar</h4>
                            <div class="event-details">
                                <i class="fas fa-calendar"></i>
                                <h6 class="card-subtitle mb-0 event-date emerald">September 10 - Main Auditorium</h6>
                            </div>
                            <p class="card-text text-muted event-description">A mandatory seminar for all newly elected
                                organization officers to learn project management and communication.</p>
                            <a href="#" class="event-link emerald">Learn More <i class="fas fa-arrow-right"
                                    style="margin-left: 5px;"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Create Account / Login Section -->
    <section class="page-section" id="auth"
        style="padding: 100px 0; background: linear-gradient(135deg, rgba(45, 106, 79, 0.85) 0%, rgba(26, 60, 46, 0.85) 100%), url('{{ asset('images/landing/BG1.jpg') }}') center/cover no-repeat; position: relative; overflow: hidden;">
        <div
            style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: radial-gradient(circle at top right, rgba(201, 168, 76, 0.1), transparent 50%); pointer-events: none;">
        </div>
        <div class="container" style="position: relative; z-index: 1;">
            <div class="row align-items-center">
                <div class="col-lg-6 mx-auto text-center">
                    <div
                        style="background: white; padding: 60px 40px; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                        <i class="fas fa-sign-in-alt fa-3x" style="color: #2D6A4F; margin-bottom: 30px;"></i>
                        <h2 class="section-heading text-uppercase mb-3" style="color: #1A3C2E;">Ready to Get Started?
                        </h2>
                        <p class="section-subheading text-muted mb-4">Join SO Connect today and become part of a
                            thriving community of student organizations. Login to your account or create a new one to
                            get access to all the exciting opportunities.</p>
                        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                            <a href="{{ route('login') }}" class="btn btn-xl text-uppercase"
                                style="background-color: #2D6A4F; border-color: #2D6A4F; padding: 15px 40px; font-weight: 600; color: white; transition: all 0.3s ease;">
                                <i class="fas fa-sign-in-alt" style="margin-right: 10px;"></i> Login
                            </a>
                        </div>
                        <p style="margin-top: 30px; color: #6c757d; font-size: 0.9rem;">
                            Already have an account? <a href="{{ route('login') }}"
                                style="color: #C9A84C; text-decoration: none; font-weight: 600;">Login here</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Recent Activity Section -->
    <section class="page-section bg-light" id="activity">
        <div class="container">
            <div class="text-center mb-5">
                <i class="fas fa-history fa-2x" style="color: #2D6A4F; margin-bottom: 20px;"></i>
                <h2 class="section-heading text-uppercase">Recent Activities</h2>
            </div>
            <div class="row g-4">
                <!-- Activity 1 -->
                <div class="col-lg-4 col-md-6">
                    <div class="activity-card"
                        style="overflow: hidden; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); transition: transform 0.3s ease;">
                        <div
                            style="background: linear-gradient(135deg, #2D6A4F 0%, #1A3C2E 100%); height: 200px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-brain fa-5x" style="color: rgba(255,255,255,0.8);"></i>
                        </div>
                        <div style="padding: 20px;">
                            <div style="color: #2D6A4F; font-size: 0.8rem; font-weight: 600; margin-bottom: 8px;">
                                <i class="fas fa-calendar-day"></i> May 10, 2026
                            </div>
                            <h5 style="margin: 0; font-weight: 700; color: #1A3C2E;">AFP Seminar Success</h5>
                        </div>
                    </div>
                </div>
                <!-- Activity 2 -->
                <div class="col-lg-4 col-md-6">
                    <div class="activity-card"
                        style="overflow: hidden; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); transition: transform 0.3s ease;">
                        <div
                            style="background: linear-gradient(135deg, #C9A84C 0%, #A68238 100%); height: 200px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-handshake fa-5x" style="color: rgba(255,255,255,0.8);"></i>
                        </div>
                        <div style="padding: 20px;">
                            <div style="color: #C9A84C; font-size: 0.8rem; font-weight: 600; margin-bottom: 8px;">
                                <i class="fas fa-calendar-day"></i> May 8, 2026
                            </div>
                            <h5 style="margin: 0; font-weight: 700; color: #1A3C2E;">Community Drive</h5>
                        </div>
                    </div>
                </div>
                <!-- Activity 3 -->
                <div class="col-lg-4 col-md-6">
                    <div class="activity-card"
                        style="overflow: hidden; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); transition: transform 0.3s ease;">
                        <div
                            style="background: linear-gradient(135deg, #2D6A4F 0%, #40826d 100%); height: 200px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-certificate fa-5x" style="color: rgba(255,255,255,0.8);"></i>
                        </div>
                        <div style="padding: 20px;">
                            <div style="color: #2D6A4F; font-size: 0.8rem; font-weight: 600; margin-bottom: 8px;">
                                <i class="fas fa-calendar-day"></i> May 5, 2026
                            </div>
                            <h5 style="margin: 0; font-weight: 700; color: #1A3C2E;">Training Completed</h5>
                        </div>
                    </div>
                </div>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Core theme JS-->
    @vite(['resources/js/scripts.js'])
    <script src="https://cdn.startbootstrap.com/sb-forms-latest.js"></script>
</body>

</html>
