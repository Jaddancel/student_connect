@props(['pageTitle' => 'Page'])

@php
    $sectionTitle = null;
    $currentPath = request()->path();
    foreach (\App\Helpers\MenuHelper::getMenuGroups() as $group) {
        foreach ($group['items'] as $item) {
            if (ltrim($item['path'], '/') === $currentPath) {
                $sectionTitle = $group['title'];
                break 2;
            }
        }
    }
@endphp

@php
    $chevron = '<svg class="stroke-current" width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366" stroke="" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">
        {{ $pageTitle }}
    </h2>
    <nav>
        <ol class="flex items-center gap-1.5">
            <li>
                <a
                    class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400"
                    href="{{ route('dashboard') }}"
                >
                    Dashboard
                    {!! $chevron !!}
                </a>
            </li>
            @if ($sectionTitle)
                <li>
                    <span class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                        {{ $sectionTitle }}
                        {!! $chevron !!}
                    </span>
                </li>
            @endif
            <li class="text-sm text-gray-800 dark:text-white/90">
                {{ $pageTitle }}
            </li>
        </ol>
    </nav>
</div>
