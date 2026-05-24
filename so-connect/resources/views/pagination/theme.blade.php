@if ($paginator->hasPages())
    <nav class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 px-2 py-3 dark:border-gray-800"
        aria-label="Pagination">
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Showing
            <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $paginator->firstItem() ?? 0 }}</span>
            –
            <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $paginator->lastItem() ?? 0 }}</span>
            of
            <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $paginator->total() }}</span>
        </p>

        <div class="flex items-center gap-1">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span
                    class="inline-flex h-8 w-8 cursor-not-allowed items-center justify-center rounded-lg text-gray-300 dark:text-gray-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6" />
                    </svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:border-palette-lime hover:bg-palette-lime-pale hover:text-gray-900 dark:border-gray-700 dark:text-gray-400 dark:hover:border-palette-lime/40 dark:hover:bg-palette-lime/10 dark:hover:text-white">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6" />
                    </svg>
                </a>
            @endif

            {{-- Page numbers (max 3: current ± 1) --}}
            @php
                $current = $paginator->currentPage();
                $last = $paginator->lastPage();
                $allPages = [];
                foreach ($elements as $element) {
                    if (is_array($element)) {
                        $allPages += $element;
                    }
                }
                $visiblePages = array_filter($allPages, fn($page) => abs($page - $current) <= 1, ARRAY_FILTER_USE_KEY);
                $firstVisible = array_key_first($visiblePages) ?? $current;
                $lastVisible = array_key_last($visiblePages) ?? $current;
            @endphp

            @if ($firstVisible > 1)
                <span class="px-1 text-xs text-gray-400 dark:text-gray-600">…</span>
            @endif

            @foreach ($visiblePages as $page => $url)
                @if ($page == $current)
                    <span aria-current="page"
                        class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-lg bg-palette-lime px-2 text-xs font-semibold text-gray-900">
                        {{ $page }}
                    </span>
                @else
                    <a href="{{ $url }}"
                        class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-lg border border-gray-200 px-2 text-xs font-medium text-gray-600 transition hover:border-palette-lime hover:bg-palette-lime-pale hover:text-gray-900 dark:border-gray-700 dark:text-gray-400 dark:hover:border-palette-lime/40 dark:hover:bg-palette-lime/10 dark:hover:text-white">
                        {{ $page }}
                    </a>
                @endif
            @endforeach

            @if ($lastVisible < $last)
                <span class="px-1 text-xs text-gray-400 dark:text-gray-600">…</span>
            @endif

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:border-palette-lime hover:bg-palette-lime-pale hover:text-gray-900 dark:border-gray-700 dark:text-gray-400 dark:hover:border-palette-lime/40 dark:hover:bg-palette-lime/10 dark:hover:text-white">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6" />
                    </svg>
                </a>
            @else
                <span
                    class="inline-flex h-8 w-8 cursor-not-allowed items-center justify-center rounded-lg text-gray-300 dark:text-gray-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6" />
                    </svg>
                </span>
            @endif
        </div>
    </nav>
@endif
