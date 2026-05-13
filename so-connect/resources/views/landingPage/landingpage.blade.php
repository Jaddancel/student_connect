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

        // Step 1: Define the hero image URL with safe encoding for the space
        $heroImageUrl = asset('images/sample-images/' . rawurlencode('autumn aspen.jpg'));
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
                <ul class="navbar-nav text-uppercase py-4 py-lg-0">
                    @foreach ($organizationTypes as $typeKey => $typeName)
                        @if (!empty($organizationsByType[$typeKey]))
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button"
                                    aria-expanded="false">{{ $typeName }}</a>
                                <ul class="dropdown-menu">
                                    @foreach ($organizationsByType[$typeKey] as $menuOrganization)
                                        <li>
                                            <a class="dropdown-item"
                                                href="{{ route('organization-feed', ['organizationId' => $menuOrganization['id'], 'slug' => $menuOrganization['slug']]) }}">
                                                {{ $menuOrganization['name'] }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        </div>
    </nav>
    <!-- Masthead-->
    <header class="masthead"
        style="background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('{{ $heroImageUrl }}');">
        <div class="container">
            <div class="masthead-subheading">Welcome to SO Connect!</div>
            <div class="masthead-heading text-uppercase">Find Your Community</div>
            <a class="btn btn-primary btn-xl text-uppercase" href="#clusters">Explore Organizations</a>
        </div>
    </header>
    <!-- Organization Clusters-->
    <section class="page-section" id="clusters">
        <div class="container">
            <div class="text-center">
                <h2 class="section-heading text-uppercase">Organization Clusters</h2>
                <h3 class="section-subheading text-muted">Discover the different avenues for student involvement on
                    campus.</h3>
            </div>
            @php
                $clusterMeta = [
                    1 => [
                        'icon' => 'hands-helping',
                        'description' => 'Engage in community service, environmental advocacy, and social awareness campaigns.',
                    ],
                    2 => [
                        'icon' => 'praying-hands',
                        'description' => 'Connect with faith-centered communities and events that nurture shared values.',
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
                        'description' => 'Lead student initiatives and represent your peers across university councils.',
                    ],
                ];
            @endphp
            <div class="row text-center">
                @foreach ($organizationTypes as $typeKey => $typeName)
                    @if (!empty($organizationsByType[$typeKey]))
                        @php
                            $meta = $clusterMeta[$typeKey] ?? [
                                'icon' => 'users',
                                'description' => 'Connect with student leaders, mentors, and campus-wide opportunities.',
                            ];
                        @endphp
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="cluster-card text-center">
                                <div class="cluster-icon-wrap">
                                    <span class="fa-stack fa-4x">
                                        <i class="fas fa-circle fa-stack-2x text-primary"></i>
                                        <i class="fas fa-{{ $meta['icon'] }} fa-stack-1x fa-inverse"></i>
                                    </span>
                                </div>
                                <h4 class="my-3">{{ $typeName }}</h4>
                                <p class="text-muted">{{ $meta['description'] }}</p>
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
                                                alt="{{ $organization['name'] }} logo" loading="lazy" />
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
                <h3 class="section-subheading text-muted">Highlights from student organization announcements.</h3>
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
                                @if (!empty($post->image_path))
                                    <div class="post-card-media">
                                        <img src="{{ asset($post->image_path) }}" alt="{{ $post->title }}" loading="lazy" />
                                    </div>
                                @endif
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
                <h3 class="section-subheading text-muted">Don't miss out on what's happening around the university.</h3>
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
    <!-- About-->

    <!-- Team-->

    <!-- Clients-->

    <!-- Contact-->
    <section class="page-section" id="contact"
        style="background: linear-gradient(135deg, #1A3C2E 0%, #2D6A4F 100%); position: relative; overflow: hidden;">
        <!-- Decorative background elements -->
        <div
            style="position: absolute; top: -50px; right: -50px; width: 300px; height: 300px; background: rgba(255,255,255,0.05); border-radius: 50%;">
        </div>
        <div
            style="position: absolute; bottom: -80px; left: -80px; width: 400px; height: 400px; background: rgba(255,255,255,0.03); border-radius: 50%;">
        </div>

        <div class="container" style="position: relative; z-index: 1;">
            <div class="text-center">
                <!-- Decorative icon -->
                <div style="margin-bottom: 30px;">
                    <i class="fas fa-handshake fa-3x" style="color: #C9A84C; margin-bottom: 20px;"></i>
                </div>

                <h2 class="section-heading text-uppercase"
                    style="color: white; margin-bottom: 15px; font-weight: 700; font-size: 2.5rem;">Ready to find your
                    place?</h2>
                <p
                    style="color: rgba(255,255,255,0.9); font-size: 1.1rem; margin-bottom: 40px; max-width: 600px; margin-left: auto; margin-right: auto; font-style: italic;">
                    Join thousands of students who have already discovered their community at TAU. Start your journey
                    today!</p>

                <!-- Enhanced CTA Button -->
                <a href="#" class="btn btn-light btn-xl text-uppercase"
                    style="padding: 18px 50px; font-weight: 700; box-shadow: 0 8px 25px rgba(0,0,0,0.2); transition: all 0.3s ease; border-radius: 50px; color: #1A3C2E;">
                    <i class="fas fa-user-plus" style="margin-right: 10px;"></i> Create Your Account Now
                </a>

                <!-- Contact Info
                    <div style="margin-top: 60px; padding-top: 40px; border-top: 2px solid rgba(255,255,255,0.2);">
                        <div style="display: flex; justify-content: center; gap: 40px; flex-wrap: wrap; margin-bottom: 30px;">
                            <div style="color: rgba(255,255,255,0.9);">
                                <i class="fas fa-envelope fa-lg" style="margin-right: 10px; color: #C9A84C;"></i>
                                <span>soconnect@tau.edu.ph</span>
                            </div>
                            <div style="color: rgba(255,255,255,0.9);">
                                <i class="fas fa-phone fa-lg" style="margin-right: 10px; color: #C9A84C;"></i>
                                <span>(+63) 456-7890</span>
                            </div>
                            <div style="color: rgba(255,255,255,0.9);">
                                <i class="fas fa-map-marker-alt fa-lg" style="margin-right: 10px; color: #C9A84C;"></i>
                                <span>Tarlac Agricultural University</span>
                            </div>
                        </div>
                        <p style="margin-top: 20px; opacity: 0.7; font-size: 0.85rem;">&copy; 2026 SOConnect — Tarlac Agricultural University. All rights reserved.</p>
                    </div> -->
            </div>
            <!-- Footer-->

            <!-- <footer  class="page-section" id="contact" class="container"
            style="background: var(--dark); color: white; padding: 60px; text-align: center;">
            <h2 style="font-family: 'Montserrat', sans-serif; margin-bottom: 20px;">Ready to find your place?</h2>
            <a href="#" class="nav-register-btn" style="box-shadow: none;">Create Your Account Now</a>
            <p style="margin-top: 40px; opacity: 0.6; font-size: 0.8rem;">&copy; 2026 SOConnect — Tarlac Agricultural University</p>
        </footer> -->

            <!-- Bootstrap core JS-->
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
            <!-- Core theme JS-->
            @vite(['resources/js/scripts.js'])
            <!-- * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *-->
            <!-- * *                               SB Forms JS                               * *-->
            <!-- * * Activate your form at https://startbootstrap.com/solution/contact-forms * *-->
            <!-- * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *-->
            <script src="https://cdn.startbootstrap.com/sb-forms-latest.js"></script>
</body>

</html>