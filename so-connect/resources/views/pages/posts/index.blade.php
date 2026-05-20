@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Posts" />

    @php
        $orgColorMap = [
            0 => 'bg-brand-500 text-white',
            1 => 'bg-violet-500 text-white',
            2 => 'bg-amber-500 text-gray-900',
            3 => 'bg-rose-500 text-white',
            4 => 'bg-teal-500 text-white',
        ];

        // Build a quick lookup: org_id → name for the view
        $orgNameMap = $orgs->mapWithKeys(fn($o) => [$o->organization_id => $o->org_name]);
    @endphp

    <div x-data="{
        view: 'cards',
        modalOpen: false,
        editMode: false,
        editFormAction: '{{ route('posts.store') }}',
        fOrg: '{{ $orgs->first()?->organization_id ?? '' }}',
        fTitle: '',
        fExcerpt: '',
        fBody: '',
        fTag: '',
        fFeatured: false,
        fHasImage: false,
        fHasVideo: false,
        /* media picker state */
        pickerOpen: false,
        pickerSearch: '',
        selectedLibraryPath: '',
        selectedLibraryTitle: '',
    
        openCreate() {
            this.editMode = false;
            this.editFormAction = '{{ route('posts.store') }}';
            this.fOrg = '{{ $orgs->first()?->organization_id ?? '' }}';
            this.fTitle = '';
            this.fExcerpt = '';
            this.fBody = '';
            this.fTag = '';
            this.fFeatured = false;
            this.fHasImage = false;
            this.fHasVideo = false;
            this.pickerOpen = false;
            this.selectedLibraryPath = '';
            this.selectedLibraryTitle = '';
            this.modalOpen = true;
        },

        openEdit(post) {
            this.editMode = true;
            this.editFormAction = '/posts/' + post.post_id;
            this.fOrg = post.organization;
            this.fTitle = post.title;
            this.fExcerpt = post.excerpt;
            this.fBody = post.body || '';
            this.fTag = post.tag || '';
            this.fFeatured = post.is_featured;
            this.fHasImage = !!post.image_path;
            this.fHasVideo = !!post.video_path;
            this.pickerOpen = false;
            this.selectedLibraryPath = post.image_from_library || '';
            this.selectedLibraryTitle = '';
            this.modalOpen = true;
        },
    
        closeModal() { this.modalOpen = false; },
    
        pickLibraryImage(path, title) {
            this.selectedLibraryPath = path;
            this.selectedLibraryTitle = title;
            this.pickerOpen = false;
        },
    
        clearLibrarySelection() {
            this.selectedLibraryPath = '';
            this.selectedLibraryTitle = '';
        },
    }"
        @keydown.escape.window="modalOpen ? closeModal() : (pickerOpen ? (pickerOpen = false) : null)"
        class="min-w-0 space-y-6">

        {{-- ── Gradient stripe ── --}}
        <div class="h-1 w-full rounded-full bg-gradient-to-r from-palette-lime via-palette-lime-light to-palette-lime-pale">
        </div>

        {{-- ── Flash messages ── --}}
        @if (session('success'))
            <div
                class="flex items-center gap-3 rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                {{ session('success') }}
            </div>
        @endif
        @if ($errors->any())
            <div
                class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- ── Toolbar ── --}}
        <div
            class="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <form method="GET" action="{{ route('posts.index') }}" class="flex flex-wrap items-center gap-3">

                <select name="org" onchange="this.form.submit()"
                    class="shadow-theme-xs h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-700 focus:border-palette-lime focus:ring-2 focus:ring-palette-lime/20 focus:outline-none dark:border-gray-700 dark:text-gray-300">
                    <option value="all" @selected($filterOrg === 'all')>All Organizations</option>
                    @foreach ($orgs as $org)
                        <option value="{{ $org->organization_id }}" @selected((string) $filterOrg === (string) $org->organization_id)>
                            {{ $org->org_name }}
                        </option>
                    @endforeach
                </select>

                <label
                    class="flex h-9 flex-1 min-w-[180px] items-center gap-2 rounded-lg border border-gray-300 bg-transparent px-3 shadow-theme-xs focus-within:border-palette-lime focus-within:ring-2 focus-within:ring-palette-lime/20 dark:border-gray-700">
                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <circle cx="11" cy="11" r="8" />
                        <path d="M21 21l-4.35-4.35" />
                    </svg>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search posts…"
                        class="grow bg-transparent text-sm text-gray-700 outline-none dark:text-gray-300" />
                </label>
                <button type="submit" class="hidden"></button>

                <div class="flex overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 ml-auto">
                    <button type="button" @click="view = 'cards'"
                        :class="view === 'cards' ? 'bg-palette-lime text-gray-900' :
                            'bg-white text-gray-500 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-white/5'"
                        class="flex h-9 w-9 items-center justify-center transition" title="Card view">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7" />
                            <rect x="14" y="3" width="7" height="7" />
                            <rect x="14" y="14" width="7" height="7" />
                            <rect x="3" y="14" width="7" height="7" />
                        </svg>
                    </button>
                    <button type="button" @click="view = 'table'"
                        :class="view === 'table' ? 'bg-palette-lime text-gray-900' :
                            'bg-white text-gray-500 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-white/5'"
                        class="flex h-9 w-9 items-center justify-center transition border-l border-gray-200 dark:border-gray-700"
                        title="Table view">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <line x1="8" y1="6" x2="21" y2="6" />
                            <line x1="8" y1="12" x2="21" y2="12" />
                            <line x1="8" y1="18" x2="21" y2="18" />
                            <line x1="3" y1="6" x2="3.01" y2="6" />
                            <line x1="3" y1="12" x2="3.01" y2="12" />
                            <line x1="3" y1="18" x2="3.01" y2="18" />
                        </svg>
                    </button>
                </div>

                <button type="button" @click="openCreate()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-palette-lime bg-palette-lime-pale px-4 py-2 text-sm font-semibold text-gray-800 transition hover:bg-palette-lime dark:border-palette-lime/30 dark:bg-palette-lime/10 dark:text-white dark:hover:bg-palette-lime/20">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path d="M12 4v16m8-8H4" />
                    </svg>
                    New Post
                </button>
            </form>
        </div>

        {{-- ── Cards View ── --}}
        <div x-show="view === 'cards'">
            @if ($posts->isEmpty())
                <div
                    class="rounded-2xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-gray-700 dark:bg-white/[0.02]">
                    <svg class="mx-auto mb-3 h-10 w-10 text-gray-300 dark:text-gray-600" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <p class="text-sm text-gray-400">No posts found.</p>
                    <button type="button" @click="openCreate()"
                        class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-palette-lime px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-palette-lime-light transition">
                        Create your first post
                    </button>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($posts as $i => $post)
                        @php
                            $orgName = $orgNameMap[$post->organization] ?? 'Organization #' . $post->organization;
                            $orgInit = collect(preg_split('/\s+/', $orgName))
                                ->filter(fn($w) => strlen($w) > 1)
                                ->take(2)
                                ->map(fn($w) => strtoupper($w[0]))
                                ->implode('');
                            $colorClass = $orgColorMap[$i % 5];
                            $postJson = [
                                'post_id' => $post->post_id,
                                'organization' => $post->organization,
                                'title' => $post->title,
                                'excerpt' => $post->excerpt,
                                'body' => $post->body,
                                'tag' => $post->tag,
                                'is_featured' => (bool) $post->is_featured,
                                'image_path' => $post->image_path,
                                'video_path' => $post->video_path,
                                'image_from_library' => str_starts_with(
                                    (string) $post->image_path,
                                    'posts/media/accomplishment/',
                                )
                                    ? $post->image_path
                                    : '',
                            ];
                        @endphp

                        <div
                            class="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex flex-1 min-w-0 items-start gap-3">
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-xs font-bold {{ $colorClass }}">
                                        {{ $orgInit ?: '?' }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold leading-tight text-gray-800 dark:text-white/90">
                                            {{ $orgName }}</p>
                                        <p class="mt-0.5 text-xs font-medium uppercase tracking-wide text-gray-400">
                                            {{ $post->published_at?->format('M d, Y') ?? $post->created_at?->format('M d, Y') }}
                                        </p>
                                        <h3 class="mt-1.5 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            {{ $post->title }}</h3>
                                        <p class="mt-1 line-clamp-2 text-sm text-gray-500 dark:text-gray-400">
                                            {{ $post->excerpt }}</p>
                                        <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs text-gray-400">
                                            @if ($post->image_path)
                                                <span class="inline-flex items-center gap-1">
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor">
                                                        <rect x="3" y="3" width="18" height="18" rx="2" />
                                                        <circle cx="8.5" cy="8.5" r="1.5" />
                                                        <polyline points="21 15 16 10 5 21" />
                                                    </svg>
                                                    @if (str_starts_with($post->image_path, 'posts/media/accomplishment/'))
                                                        Image (from accomplishment library)
                                                    @else
                                                        Image
                                                    @endif
                                                </span>
                                            @endif
                                            @if ($post->video_path)
                                                <span class="inline-flex items-center gap-1">
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor">
                                                        <polygon points="23 7 16 12 23 17 23 7" />
                                                        <rect x="1" y="5" width="15" height="14" rx="2" />
                                                    </svg>Video
                                                </span>
                                            @endif
                                            @if ($post->is_featured)
                                                <span class="inline-flex items-center gap-1 text-amber-500">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
                                                        <path
                                                            d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                                    </svg>Featured
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="mt-3 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3 dark:border-gray-800">
                                <button type="button" @click="openEdit({{ Js::from($postJson) }})"
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:border-palette-lime hover:bg-palette-lime-pale hover:text-gray-900 dark:border-gray-700 dark:text-gray-400">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                    </svg>
                                    Edit
                                </button>
                                <div x-data="{ confirmDel: false }" class="flex items-center gap-2">
                                    <button type="button" x-show="!confirmDel" @click="confirmDel = true"
                                        class="inline-flex items-center gap-1 rounded-lg border border-error-200 px-3 py-1.5 text-xs font-medium text-error-600 transition hover:bg-error-50 dark:border-error-500/30 dark:text-error-400">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6" />
                                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                            <path d="M10 11v6M14 11v6" />
                                            <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                                        </svg>
                                        Delete
                                    </button>
                                    <div x-show="confirmDel" x-cloak class="flex items-center gap-1.5">
                                        <span class="text-xs text-gray-500">Confirm?</span>
                                        <form method="POST" action="{{ route('posts.destroy', $post->post_id) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                class="rounded-lg bg-error-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-error-600">Yes,
                                                delete</button>
                                        </form>
                                        <button type="button" @click="confirmDel = false"
                                            class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-500 hover:bg-gray-50 dark:border-gray-700">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ── Table View ── --}}
        <div x-show="view === 'table'" x-cloak>
            <div
                class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                @if ($posts->isEmpty())
                    <div class="p-10 text-center text-sm text-gray-400">No posts found.</div>
                @else
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-5 py-3.5 font-semibold text-gray-700 dark:text-gray-300">Organization</th>
                                <th class="px-5 py-3.5 font-semibold text-gray-700 dark:text-gray-300">Title</th>
                                <th class="px-5 py-3.5 font-semibold text-gray-700 dark:text-gray-300">Date</th>
                                <th class="px-5 py-3.5 font-semibold text-gray-700 dark:text-gray-300">Media</th>
                                <th class="px-5 py-3.5 font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($posts as $i => $post)
                                @php
                                    $orgName =
                                        $orgNameMap[$post->organization] ?? 'Organization #' . $post->organization;
                                    $orgInit = collect(preg_split('/\s+/', $orgName))
                                        ->filter(fn($w) => strlen($w) > 1)
                                        ->take(2)
                                        ->map(fn($w) => strtoupper($w[0]))
                                        ->implode('');
                                    $colorClass = $orgColorMap[$i % 5];
                                    $status = $post->status ?? 'draft';
                                    $postJson = [
                                        'post_id' => $post->post_id,
                                        'organization' => $post->organization,
                                        'title' => $post->title,
                                        'excerpt' => $post->excerpt,
                                        'body' => $post->body,
                                        'tag' => $post->tag,
                                        'status' => $status,
                                        'is_featured' => (bool) $post->is_featured,
                                        'image_path' => $post->image_path,
                                        'video_path' => $post->video_path,
                                        'image_from_library' => str_starts_with(
                                            (string) $post->image_path,
                                            'posts/media/accomplishment/',
                                        )
                                            ? $post->image_path
                                            : '',
                                    ];
                                @endphp
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-2.5">
                                            <div
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-[0.65rem] font-bold {{ $colorClass }}">
                                                {{ $orgInit ?: '?' }}</div>
                                            <span
                                                class="max-w-[140px] truncate font-medium text-gray-700 dark:text-gray-300">{{ $orgName }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <p class="max-w-[200px] truncate font-medium text-gray-800 dark:text-white/90">
                                            {{ $post->title }}</p>
                                        <p class="max-w-[200px] truncate text-xs text-gray-400">{{ $post->excerpt }}</p>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-xs text-gray-400">
                                        {{ $post->published_at?->format('M d, Y') ?? $post->created_at?->format('M d, Y') }}
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-2 text-gray-400">
                                            @if ($post->image_path)
                                                <img src="{{ '/storage/' . $post->image_path }}" alt="{{ $post->title }}"
                                                    class="h-10 w-10 rounded-md object-cover ring-1 ring-gray-200 dark:ring-gray-700"
                                                    loading="lazy"
                                                    onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';">
                                                <span
                                                    class="hidden h-10 w-10 items-center justify-center rounded-md bg-gray-100 text-gray-400 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700"
                                                    title="Image unavailable">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor">
                                                        <rect x="3" y="3" width="18" height="18" rx="2" />
                                                        <circle cx="8.5" cy="8.5" r="1.5" />
                                                        <polyline points="21 15 16 10 5 21" />
                                                    </svg>
                                                </span>
                                            @endif
                                            @if ($post->video_path)
                                                <svg class="h-4 w-4" title="Video" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor">
                                                    <polygon points="23 7 16 12 23 17 23 7" />
                                                    <rect x="1" y="5" width="15" height="14" rx="2" />
                                                </svg>
                                            @endif
                                            @if (!$post->image_path && !$post->video_path)
                                                <span class="text-xs">—</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <button type="button" @click="openEdit({{ Js::from($postJson) }})"
                                                class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">Edit</button>
                                            <span class="text-gray-300 dark:text-gray-700">|</span>
                                            <form method="POST" action="{{ route('posts.destroy', $post->post_id) }}"
                                                onsubmit="return confirm('Delete this post?')">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                    class="text-xs font-medium text-error-500 hover:underline">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- ════════════════ CREATE / EDIT MODAL ════════════════ --}}
        <div x-show="modalOpen" x-cloak class="fixed inset-0 z-[100000] flex items-end sm:items-center justify-center p-4"
            :style="{
                paddingLeft: (window.innerWidth >= 1280) ?
                    (($store.sidebar.isExpanded || $store.sidebar.isHovered) ? 'calc(290px + 1rem)' :
                        'calc(90px + 1rem)') : '1rem'
            }">

            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="closeModal()"></div>

            <div class="relative z-10 flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 translate-y-4">

                <div
                    class="h-1 w-full bg-gradient-to-r from-palette-lime via-palette-lime-light to-palette-lime-pale shrink-0">
                </div>

                <div
                    class="flex shrink-0 items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h3 class="font-bold text-gray-800 dark:text-white/90"
                        x-text="editMode ? 'Edit Post' : 'Create Post'"></h3>
                    <button type="button" @click="closeModal()"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2.5">
                            <path d="M18 6L6 18M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto">
                    <form id="post-modal-form" method="POST" enctype="multipart/form-data" :action="editFormAction"
                        class="space-y-3 px-5 py-3">
                        @csrf
                        <input type="hidden" name="_method" :value="editMode ? 'PATCH' : ''">

                        {{-- Posting as --}}
                        <div>
                            <label
                                class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-400">Posting
                                as</label>
                            <select name="organization" x-model="fOrg"
                                class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-2.5 text-sm text-gray-700 focus:border-palette-lime focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                @foreach ($orgs as $org)
                                    <option value="{{ $org->organization_id }}">{{ $org->org_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Title --}}
                        <div>
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-400">Title
                                <span class="text-error-500">*</span></label>
                            <input type="text" name="title" x-model="fTitle" placeholder="Post title"
                                class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-2.5 text-sm text-gray-800 focus:border-palette-lime focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>

                        {{-- Excerpt --}}
                        <div>
                            <label
                                class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-400">Excerpt
                                <span class="text-error-500">*</span></label>
                            <textarea name="excerpt" rows="2" x-model="fExcerpt" maxlength="280"
                                placeholder="Short summary shown in feeds (max 280 chars)"
                                class="w-full resize-none rounded-lg border border-gray-300 bg-transparent px-2.5 py-2 text-sm text-gray-800 focus:border-palette-lime focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                        </div>

                        {{-- Body --}}
                        <div>
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-400">Body
                                <span class="font-normal normal-case text-gray-400">(optional)</span></label>
                            <textarea name="body" rows="2" x-model="fBody" placeholder="Full post content…"
                                class="w-full resize-none rounded-lg border border-gray-300 bg-transparent px-2.5 py-2 text-sm text-gray-800 focus:border-palette-lime focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                        </div>

                        {{-- Tag + Featured in one row --}}
                        <div class="flex items-end gap-3">
                            <div class="flex-1">
                                <label
                                    class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-400">Tag</label>
                                <input type="text" name="tag" x-model="fTag" placeholder="e.g. Announcement"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-2.5 text-sm text-gray-800 focus:border-palette-lime focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                            <label class="mb-1.5 flex cursor-pointer items-center gap-2 whitespace-nowrap">
                                <input type="checkbox" name="is_featured" value="1" :checked="fFeatured"
                                    @change="fFeatured = $event.target.checked"
                                    class="h-4 w-4 rounded border-gray-300 text-palette-lime focus:ring-palette-lime/30" />
                                <span class="text-sm text-gray-600 dark:text-gray-400">Featured</span>
                            </label>
                        </div>

                        {{-- ── IMAGE SECTION ── --}}
                        <div class="space-y-1.5">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Image</p>

                            {{-- Library selection display --}}
                            <div x-show="selectedLibraryPath" x-cloak
                                class="flex items-center gap-2.5 rounded-lg border border-palette-lime/40 bg-palette-lime-pale/50 px-3 py-2 dark:border-palette-lime/20 dark:bg-palette-lime/5">
                                <img :src="'/storage/' + selectedLibraryPath"
                                    class="h-10 w-14 shrink-0 rounded object-cover border border-gray-200" alt="">
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">From library</p>
                                    <p class="truncate text-xs text-gray-500" x-text="selectedLibraryTitle"></p>
                                </div>
                                <button type="button" @click="clearLibrarySelection()"
                                    class="shrink-0 text-xs text-error-500 hover:underline">Remove</button>
                                <input type="hidden" name="image_from_library" :value="selectedLibraryPath">
                            </div>

                            {{-- Upload + Library picker button side by side --}}
                            <div x-show="!selectedLibraryPath" class="flex gap-2">
                                <label
                                    class="flex flex-1 cursor-pointer items-center gap-2 rounded-lg border border-dashed border-gray-300 px-3 py-2 text-xs transition hover:border-palette-lime hover:bg-palette-lime-pale/20 dark:border-gray-700">
                                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <rect x="3" y="3" width="18" height="18" rx="2" />
                                        <circle cx="8.5" cy="8.5" r="1.5" />
                                        <polyline points="21 15 16 10 5 21" />
                                    </svg>
                                    <span class="text-gray-500 dark:text-gray-400"
                                        x-text="fHasImage ? 'Replace image' : 'Upload image'"></span>
                                    <input type="file" name="image"
                                        accept="image/jpeg,image/png,image/jpg,image/webp" class="sr-only"
                                        @change="fHasImage = $event.target.files.length > 0" />
                                </label>
                                @if ($accomplishmentMedia->isNotEmpty())
                                    <button type="button" @click="pickerOpen = true"
                                        class="flex items-center gap-1.5 rounded-lg border border-dashed border-brand-300 bg-brand-50/50 px-3 py-2 text-xs font-medium text-brand-600 transition hover:bg-brand-50 dark:border-brand-700 dark:bg-brand-900/10 dark:text-brand-400">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor" stroke-width="2">
                                            <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z" />
                                            <line x1="4" y1="22" x2="4" y2="15" />
                                        </svg>
                                        Library
                                        <span
                                            class="rounded-full bg-brand-100 px-1.5 text-[10px] font-semibold text-brand-700 dark:bg-brand-800 dark:text-brand-300">{{ $accomplishmentMedia->count() }}</span>
                                    </button>
                                @endif
                            </div>
                        </div>

                        {{-- Video upload --}}
                        <div>
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Video <span
                                    class="font-normal normal-case">(optional)</span></p>
                            <label
                                class="flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-gray-300 px-3 py-2 text-xs transition hover:border-palette-lime hover:bg-palette-lime-pale/20 dark:border-gray-700">
                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <polygon points="23 7 16 12 23 17 23 7" />
                                    <rect x="1" y="5" width="15" height="14" rx="2" />
                                </svg>
                                <span class="text-gray-500 dark:text-gray-400"
                                    x-text="fHasVideo ? 'Replace video' : 'Upload video (MP4, WebM, MOV — max 100 MB)'"></span>
                                <input type="file" name="video"
                                    accept="video/mp4,video/webm,video/quicktime,video/x-msvideo" class="sr-only"
                                    @change="fHasVideo = $event.target.files.length > 0" />
                            </label>
                        </div>
                    </form>
                </div>

                <div
                    class="flex shrink-0 items-center justify-end gap-2 border-t border-gray-100 px-5 py-4 dark:border-gray-800">
                    <button type="button" @click="closeModal()"
                        class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800">
                        Cancel
                    </button>
                    <button type="submit" form="post-modal-form"
                        class="rounded-lg bg-palette-lime px-5 py-2 text-sm font-semibold text-gray-900 transition hover:bg-palette-lime-light"
                        x-text="editMode ? 'Save Changes' : 'Publish Post'">
                        Publish Post
                    </button>
                </div>
            </div>
        </div>

        {{-- ════════════════ ACCOMPLISHMENT MEDIA PICKER ════════════════ --}}
        @if ($accomplishmentMedia->isNotEmpty())
            <div x-show="pickerOpen" x-cloak
                class="fixed inset-0 z-[100001] flex items-end sm:items-center justify-center p-4"
                :style="{
                    paddingLeft: (window.innerWidth >= 1280) ?
                        (($store.sidebar.isExpanded || $store.sidebar.isHovered) ? 'calc(290px + 1rem)' :
                            'calc(90px + 1rem)') : '1rem'
                }">

                <div class="absolute inset-0 bg-gray-900/70 backdrop-blur-sm" @click="pickerOpen = false"></div>

                <div class="relative z-10 flex max-h-[88vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900"
                    x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">

                    {{-- Picker header --}}
                    <div class="h-1 w-full bg-gradient-to-r from-brand-400 via-brand-500 to-brand-600 shrink-0"></div>
                    <div
                        class="flex shrink-0 items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <div>
                            <h3 class="font-bold text-gray-800 dark:text-white/90">Accomplishment Library</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Select a photo from submitted
                                accomplishment reports</p>
                        </div>
                        <button type="button" @click="pickerOpen = false"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.5">
                                <path d="M18 6L6 18M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Picker search --}}
                    <div class="shrink-0 border-b border-gray-100 px-5 py-3 dark:border-gray-800">
                        <label
                            class="flex h-9 items-center gap-2 rounded-lg border border-gray-300 bg-transparent px-3 focus-within:border-brand-400 focus-within:ring-2 focus-within:ring-brand-400/20 dark:border-gray-700">
                            <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <circle cx="11" cy="11" r="8" />
                                <path d="M21 21l-4.35-4.35" />
                            </svg>
                            <input type="text" x-model="pickerSearch" placeholder="Search by activity title…"
                                class="grow bg-transparent text-sm text-gray-700 outline-none dark:text-gray-300" />
                        </label>
                    </div>

                    {{-- Picker grid --}}
                    <div class="flex-1 overflow-y-auto px-5 py-4">
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($accomplishmentMedia as $media)
                                @php
                                    $mediaOrgName =
                                        $orgNameMap[$media->organization_id] ??
                                        'Organization #' . $media->organization_id;
                                @endphp
                                <button type="button"
                                    x-show="pickerSearch === '' || '{{ strtolower($media->activity_title) }}'.includes(pickerSearch.toLowerCase()) || '{{ strtolower($mediaOrgName) }}'.includes(pickerSearch.toLowerCase())"
                                    @click="pickLibraryImage('{{ $media->file_path }}', '{{ addslashes($media->activity_title) }}')"
                                    class="group relative overflow-hidden rounded-xl border-2 border-transparent bg-gray-50 transition hover:border-brand-400 focus:border-brand-500 focus:outline-none dark:bg-gray-800"
                                    :class="selectedLibraryPath === '{{ $media->file_path }}' ?
                                        'border-brand-500 ring-2 ring-brand-400/30' : 'border-transparent'">
                                    <img src="{{ '/storage/' . $media->file_path }}" alt="{{ $media->activity_title }}"
                                        class="aspect-square w-full object-cover" loading="lazy">
                                    {{-- Overlay with info --}}
                                    <div
                                        class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-gray-900/80 to-transparent px-2 pb-2 pt-6 opacity-0 transition group-hover:opacity-100">
                                        <p class="line-clamp-2 text-left text-xs font-medium text-white">
                                            {{ $media->activity_title }}</p>
                                        <p class="text-left text-[0.65rem] text-gray-300">{{ $mediaOrgName }}</p>
                                    </div>
                                    {{-- Selected checkmark --}}
                                    <div x-show="selectedLibraryPath === '{{ $media->file_path }}'"
                                        class="absolute right-1.5 top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-brand-500 shadow">
                                        <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                </button>
                            @endforeach
                        </div>

                        {{-- Empty search state --}}
                        <p class="hidden py-6 text-center text-sm text-gray-400"
                            x-show="{{ $accomplishmentMedia->count() }} > 0 && pickerSearch !== '' && document.querySelectorAll('[x-show*=pickerSearch]:not([style*=none])').length === 0">
                            No matching images found.
                        </p>
                    </div>

                    {{-- Picker footer --}}
                    <div
                        class="flex shrink-0 items-center justify-between border-t border-gray-100 px-5 py-3 dark:border-gray-800">
                        <span class="text-xs text-gray-400">{{ $accomplishmentMedia->count() }}
                            photo{{ $accomplishmentMedia->count() !== 1 ? 's' : '' }} available</span>
                        <div class="flex gap-2">
                            <button type="button" @click="pickerOpen = false"
                                class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400">
                                Cancel
                            </button>
                            <button type="button" @click="pickerOpen = false" :disabled="!selectedLibraryPath"
                                class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:opacity-40 disabled:cursor-not-allowed">
                                Use Selected Photo
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>{{-- end x-data --}}
@endsection
