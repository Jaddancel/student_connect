<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduPlay — TAU Organizations</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;600;700;800;900&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --mint: #C8F7C5;
            --sage: #88C98A;
            --forest: #526B52;
            --bg: #F2FBF2;
            --dark: #2B3D2B;
            --text: #3E5240;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }

        /* ===== UPDATED NAVIGATION ===== */
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(242, 251, 242, 0.95);
            backdrop-filter: blur(12px);
            border-bottom: 3px solid var(--sage);
            height: 85px;
        }

        .logo {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.7rem;
            /* Increased slightly to maintain hierarchy */
            color: var(--forest);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 900;
            text-decoration: none;
            min-width: fit-content;
            margin-right: 10px;
            letter-spacing: -0.5px;
        }

        .nav-links {
            display: flex;
            gap: 10px;
            list-style: none;
            flex: 1;
            justify-content: flex-end;
            margin-left: auto;
            margin-right: 25px;
            align-items: center;
        }

        .dropdown {
            position: relative;
        }

        .drop-btn {
            text-decoration: none;
            color: var(--text);
            /* ADJUSTED: Slightly lighter weight and larger size for better legibility */
            font-weight: 800;
            font-size: 0.8rem;
            padding: 10px 12px;
            border-radius: 8px;
            transition: all 0.2s;
            cursor: pointer;
            /* Changed from default to pointer for better UX */
            text-transform: uppercase;
            letter-spacing: 0.5px;
            /* Adds space between letters to make them feel "bigger" */
            gap: 4px;
            white-space: nowrap;
            /* Prevents text from breaking into multiple lines */
        }


        .dropdown:hover .drop-btn {
            background: var(--mint);
            color: var(--forest);
        }

        .dropdown-content {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            min-width: 240px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            border: 2px solid var(--forest);
            border-radius: 12px;
            max-height: 400px;
            overflow-y: auto;
            padding: 10px 0;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .dropdown-content a {
            color: var(--text);
            padding: 8px 16px;
            text-decoration: none;
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .nav-register-btn {
            background: var(--forest);
            color: #fff;
            text-decoration: none;
            padding: 12px 24px;
            /* Increased horizontal padding */
            border-radius: 30px;
            font-weight: 900;
            /* INCREASED: Matching the scale of the new nav links */
            font-size: 1.0rem;
            border: 3px solid var(--dark);
            box-shadow: 4px 4px 0 var(--dark);
            transition: all 0.15s;
            margin-left: 20px;
            text-transform: uppercase;
        }

        .nav-register-btn:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 var(--dark);
        }

        .nav-register-btn:active {
            transform: translate(2px, 2px);
            box-shadow: 2px 2px 0 var(--dark);
        }

        .dropdown-content a:hover {
            background: var(--mint);
        }

        /* Scrollbar for long lists */
        .dropdown-content::-webkit-scrollbar {
            width: 6px;
        }

        .dropdown-content::-webkit-scrollbar-thumb {
            background: var(--sage);
            border-radius: 10px;
        }

        /* Original Hero Styling */
        .hero {
            padding: 80px 60px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            min-height: 80vh;
        }

        .hero h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 4rem;
            font-weight: 900;
            text-transform: uppercase;
            color: var(--dark);
            line-height: 1.1;
        }

        .highlight {
            color: var(--forest);
            position: relative;
            z-index: 1;
        }

        .highlight::after {
            content: '';
            position: absolute;
            bottom: 5px;
            left: 0;
            width: 100%;
            height: 15px;
            background: var(--mint);
            z-index: -1;
        }

        /* Stats Section */
        .stats-ribbon {
            background: var(--forest);
            color: white;
            padding: 40px;
            display: flex;
            justify-content: space-around;
            text-align: center;
            border-top: 4px solid var(--dark);
            border-bottom: 4px solid var(--dark);
        }

        .stat-item h2 {
            font-family: 'Montserrat', sans-serif;
            text-transform: uppercase;
            font-size: 2.5rem;
        }

        .stat-item p {
            font-weight: 700;
            opacity: 0.9;
        }

        /* Search Bar */
        .search-container {
            max-width: 600px;
            margin: -30px auto 50px;
            position: relative;
            z-index: 10;
        }

        .search-bar {
            width: 100%;
            padding: 20px 30px;
            border-radius: 50px;
            border: 3px solid var(--dark);
            font-family: 'Montserrat', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            box-shadow: 6px 6px 0 var(--sage);
        }

        /* Category Grid */
        .section-title {
            text-align: center;
            font-family: 'Montserrat', sans-serif;
            font-size: 2.5rem;
            margin-bottom: 40px;
            color: var(--dark);
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            padding: 0 60px 80px;
        }

        .cat-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            border: 3px solid var(--dark);
            box-shadow: 6px 6px 0 var(--mint);
            transition: transform 0.2s;
        }

        .cat-card:hover {
            transform: translateY(-5px);
        }

        .cat-card h3 {
            font-family: 'Montserrat', sans-serif;
            text-transform: uppercase;
            color: var(--forest);
            margin-bottom: 10px;
        }

        .cat-card p {
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .cat-tag {
            display: inline-block;
            padding: 5px 12px;
            background: var(--mint);
            border-radius: 5px;
            font-size: 0.75rem;
            font-weight: 800;
            margin-right: 5px;
        }

        .featured-posts {
            padding: 80px 60px 0;
        }

        .featured-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-top: 30px;
        }

        .featured-card {
            background: #fff;
            padding: 24px;
            border-radius: 18px;
            border: 3px solid var(--dark);
            box-shadow: 6px 6px 0 var(--mint);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .featured-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--forest);
        }

        .featured-card h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.15rem;
            color: var(--dark);
        }

        .featured-card p {
            font-size: 0.9rem;
        }

        .featured-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 800;
            font-size: 0.85rem;
        }

        .featured-link {
            color: var(--forest);
            text-decoration: none;
        }

        .featured-empty {
            grid-column: 1 / -1;
            background: #fff;
            padding: 24px;
            border-radius: 18px;
            border: 3px dashed var(--sage);
            text-align: center;
            font-weight: 800;
        }

        @media (max-width: 1100px) {
            .nav-links {
                gap: 5px;
            }

            .drop-btn {
                font-size: 0.8rem;
                padding: 5px;
            }
        }

        /* ===== MODAL STYLES (new — does not affect anything above) ===== */
        .org-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(43, 61, 43, 0.55);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .org-modal-overlay.open {
            display: flex;
        }

        .org-modal {
            background: #fff;
            border: 3px solid var(--dark);
            border-radius: 20px;
            box-shadow: 8px 8px 0 var(--dark);
            width: 100%;
            max-width: 420px;
            padding: 40px 36px 32px;
            text-align: center;
            position: relative;
            animation: orgModalIn 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes orgModalIn {
            from {
                transform: scale(0.8) translateY(20px);
                opacity: 0;
            }

            to {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }

        .org-modal-close {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid var(--dark);
            background: var(--mint);
            font-size: 1rem;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s;
        }

        .org-modal-close:hover {
            background: var(--sage);
        }

        .org-modal-logo {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            border: 3px solid var(--dark);
            box-shadow: 4px 4px 0 var(--sage);
            background: var(--mint);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.6rem;
            margin: 0 auto 20px;
            overflow: hidden;
        }

        .org-modal-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .org-modal-name {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.25rem;
            font-weight: 900;
            color: var(--dark);
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .org-modal-category {
            display: inline-block;
            padding: 4px 14px;
            background: var(--mint);
            border: 2px solid var(--forest);
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--forest);
            margin-bottom: 24px;
        }

        .org-modal-divider {
            border: none;
            border-top: 2px solid var(--mint);
            margin: 0 0 20px;
        }

        .org-modal-join {
            display: inline-block;
            background: var(--forest);
            color: #fff;
            padding: 12px 32px;
            border-radius: 50px;
            border: 3px solid var(--dark);
            box-shadow: 4px 4px 0 var(--dark);
            font-weight: 900;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s;
            font-family: 'Montserrat', sans-serif;
        }

        .org-modal-join:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 var(--dark);
        }
    </style>
</head>

<body>

    <nav>
        <a href="{{ route('home') }}" class="logo">SO<span style="color:var(--sage)">Connect</span></a>

        <ul class="nav-links">
            @foreach ($organizationTypes as $typeKey => $typeName)
                @if (!empty($organizationsByType[$typeKey]))
                    <li class="dropdown">
                        <a href="#" class="drop-btn">{{ $typeName }} ▾</a>
                        <div class="dropdown-content">
                            @foreach ($organizationsByType[$typeKey] as $organization)
                                <a href="#" data-org-name="{{ $organization['name'] }}"
                                    data-org-category="{{ $typeName }}"
                                    data-logo-url="{{ $organization['logo_url'] ?? '' }}"
                                    data-feed-url="{{ route('organization-feed', ['organizationId' => $organization['id'], 'slug' => $organization['slug']]) }}"
                                    onclick="openOrgModal(this); return false;">
                                    {{ $organization['name'] }}
                                </a>
                            @endforeach
                        </div>
                    </li>
                @endif
            @endforeach
        </ul>
        <!-- <a href="#" class="nav-register-btn">Register Now </a> -->
    </nav>

    @php
        $spotlightPost = $featuredPosts->first();
        $spotlightOrg = $spotlightPost
            ? $organizationNameMap[$spotlightPost->organization] ?? 'Unknown Organization'
            : null;
    @endphp

    <section class="hero">
        <div class="hero-content">
            <h1>Campus Life <br><span class="highlight">Redefined.</span></h1>
            <p style="margin-top: 20px; font-size: 1.2rem; opacity: 0.8;">Explore the diverse range of student
                organizations. From socio-civic outreach to student government, find your community here.</p>
            <div style="margin-top: 30px;">
                <a href="#explore-categories"
                    style="background: var(--forest); color: white; padding: 15px 35px; border-radius: 50px; text-decoration: none; font-weight: 900; border: 3px solid var(--dark); box-shadow: 4px 4px 0 var(--dark);">Explore
                    All Orgs</a>
            </div>
        </div>
        <div class="hero-visual">
            <div
                style="background: white; padding: 40px; border-radius: 30px; border: 3px solid var(--dark); box-shadow: 10px 10px 0 var(--sage);">
                <h3 style="font-family: 'Montserrat', sans-serif; color: var(--forest);">Organization Spotlight</h3>
                @if ($spotlightPost)
                    <p style="margin: 15px 0;"><strong>{{ $spotlightPost->title }}</strong></p>
                    <p style="margin: 10px 0; font-size: 0.9rem;">{{ $spotlightPost->excerpt }}</p>
                    <div
                        style="height: 12px; background: var(--mint); border-radius: 10px; border: 2px solid var(--dark);">
                        <div style="width: 85%; height: 100%; background: var(--forest); border-radius: 8px;"></div>
                    </div>
                    <p style="font-size: 0.8rem; margin-top: 5px; font-weight: 800;">
                        {{ $spotlightOrg }} •
                        {{ $spotlightPost->published_at?->diffForHumans() ?? $spotlightPost->created_at?->diffForHumans() }}
                    </p>
                @else
                    <p style="margin: 15px 0;">Join a student organization and discover upcoming activities.</p>
                    <div
                        style="height: 12px; background: var(--mint); border-radius: 10px; border: 2px solid var(--dark);">
                        <div style="width: 35%; height: 100%; background: var(--forest); border-radius: 8px;"></div>
                    </div>
                    <p style="font-size: 0.8rem; margin-top: 5px; font-weight: 800;">Featured posts coming soon</p>
                @endif
            </div>
        </div>
    </section>

    <div class="search-container">
        <input type="text" class="search-bar" placeholder="🔍 Search for an organization (e.g. 'Math' or 'Arts')...">
    </div>

    <section class="stats-ribbon">
        <div class="stat-item">
            <h2>60+</h2>
            <p>Active Organizations</p>
        </div>
        <div class="stat-item">
            <h2>5,000+</h2>
            <p>Student Members</p>
        </div>
        <div class="stat-item">
            <h2>12</h2>
            <p>Annual Major Events</p>
        </div>
    </section>

    <section class="featured-posts" id="featured-posts">
        <h2 class="section-title">Featured <span class="highlight">Posts</span></h2>
        <div class="featured-grid">
            @forelse ($featuredPosts as $post)
                @php
                    $postOrgName = $organizationNameMap[$post->organization] ?? 'Unknown Organization';
                @endphp
                <article class="featured-card">
                    <div class="featured-meta">
                        <span>{{ $post->tag ?? 'Update' }}</span>
                        <span>
                            {{ $post->published_at?->diffForHumans() ?? $post->created_at?->diffForHumans() }}
                        </span>
                    </div>
                    <h3>{{ $post->title }}</h3>
                    <p>{{ $post->excerpt }}</p>
                    <div class="featured-footer">
                        <span>{{ $postOrgName }}</span>
                        @if ($post->organization)
                            <a class="featured-link"
                                href="{{ route('organization-feed', ['organizationId' => $post->organization]) }}">View
                                organization</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="featured-empty">
                    <p>No featured posts yet. Check back soon.</p>
                </div>
            @endforelse
        </div>
    </section>

    <section style="padding-top: 80px;" id="explore-categories">
        <h2 class="section-title">Explore by <span class="highlight">Category</span></h2>
        <div class="category-grid">
            @foreach ($organizationTypes as $typeKey => $typeName)
                @php
                    $categoryOrganizations = $organizationsByType[$typeKey] ?? [];
                @endphp
                @if (!empty($categoryOrganizations))
                    <div class="cat-card">
                        <h3>{{ $typeName }}</h3>
                        <p>Join specialized groups focused on {{ strtolower($typeName) }} community building.</p>
                        <div style="margin-bottom: 15px;">
                            @foreach (collect($categoryOrganizations)->take(3) as $organization)
                                <span class="cat-tag">{{ $organization['name'] }}</span>
                            @endforeach
                        </div>
                        <span style="color: var(--forest); font-weight: 900; text-decoration: none;">View All
                            {{ count($categoryOrganizations) }} Orgs →</span>
                    </div>
                @endif
            @endforeach
        </div>
    </section>

    <footer style="background: var(--dark); color: white; padding: 60px; text-align: center;">
        <h2 style="font-family: 'Montserrat', sans-serif; margin-bottom: 20px;">Ready to find your place?</h2>
        <a href="#" class="nav-register-btn" style="box-shadow: none;">Create Your Account Now</a>
        <p style="margin-top: 40px; opacity: 0.6; font-size: 0.8rem;">&copy; 2026 SOConnect — Tarlac Agricultural
            University</p>
    </footer>

    <!-- ===== MODAL (appended — nothing above changed) ===== -->
    <div class="org-modal-overlay" id="orgModalOverlay" onclick="handleOrgOverlayClick(event)">
        <div class="org-modal" id="orgModal">
            <button class="org-modal-close" onclick="closeOrgModal()" title="Close">✕</button>

            <!-- Logo circle: shows uploaded image if provided, otherwise initials -->
            <div class="org-modal-logo" id="orgModalLogo"></div>

            <div class="org-modal-name" id="orgModalName"></div>
            <div class="org-modal-category" id="orgModalCat"></div>

            <hr class="org-modal-divider">

            <a href="#" class="org-modal-join">Join Organization</a>
        </div>
    </div>

    <script>
        // Generates initials from org name (e.g. "TAU Math Society" -> "TM")
        function getInitials(name) {
            return name
                .split(' ')
                .filter(w => w.length > 2) // skip short words like "of", "the"
                .slice(0, 2)
                .map(w => w[0].toUpperCase())
                .join('');
        }

        function openOrgModal(trigger) {
            const orgName = trigger.getAttribute('data-org-name') || '';
            const category = trigger.getAttribute('data-org-category') || '';
            const logoUrl = trigger.getAttribute('data-logo-url') || '';
            const feedUrl = trigger.getAttribute('data-feed-url') || '#';
            const logoEl = document.getElementById('orgModalLogo');

            // Show logo image if mapped, otherwise show initials
            if (logoUrl) {
                logoEl.innerHTML = '<img src="' + logoUrl + '" alt="' + orgName + ' logo">';
            } else {
                logoEl.textContent = getInitials(orgName);
            }

            document.getElementById('orgModalName').textContent = orgName;
            document.getElementById('orgModalCat').textContent = category;

            const joinLink = document.querySelector('.org-modal-join');
            if (joinLink) {
                joinLink.setAttribute('href', feedUrl);
            }

            document.getElementById('orgModalOverlay').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeOrgModal() {
            document.getElementById('orgModalOverlay').classList.remove('open');
            document.body.style.overflow = '';
        }

        function handleOrgOverlayClick(e) {
            if (e.target === document.getElementById('orgModalOverlay')) closeOrgModal();
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeOrgModal();
        });
    </script>

</body>

</html>
