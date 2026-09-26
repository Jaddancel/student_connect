@php
    $items = $organizationSwitcherItems ?? collect();
    $activeOrganizationId = (int) ($activeOrganizationId ?? 0);
    $activeOrganization = $items->firstWhere('id', $activeOrganizationId) ?? $items->first();
@endphp

@if ($items->count() > 1)
    <div class="relative" x-data="{ open: false }" @click.away="open = false">
        <button type="button" @click="open = !open" class="flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:border-gray-300 hover:text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-700">
            @if ($activeOrganization)
                @if (!empty($activeOrganization['logo']))
                    <img src="{{ $activeOrganization['logo'] }}" alt="{{ $activeOrganization['name'] }} logo" class="h-8 w-8 rounded-full object-cover" />
                @else
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-200 text-xs font-bold text-gray-700 dark:bg-gray-700 dark:text-gray-200">{{ $activeOrganization['initials'] }}</span>
                @endif
                <span class="max-w-[120px] truncate">{{ $activeOrganization['name'] }}</span>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            @endif
        </button>

        <div x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute right-0 z-50 mt-3 w-[310px] rounded-2xl border border-gray-200 bg-white p-2 shadow-lg dark:border-gray-800 dark:bg-gray-900" style="display:none">
            <div class="max-h-80 overflow-y-auto">
                @foreach ($items as $item)
                    @php
                        $isActive = (int) $item['id'] === $activeOrganizationId;
                    @endphp
                    <form method="POST" action="{{ route('organizations.switch', $item['id']) }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-2 py-2 text-left transition hover:bg-gray-100 dark:hover:bg-white/5 {{ $isActive ? 'bg-gray-100 dark:bg-white/5' : '' }}">
                            @if (!empty($item['logo']))
                                <img src="{{ $item['logo'] }}" alt="{{ $item['name'] }} logo" class="h-10 w-10 rounded-full object-cover" />
                            @else
                                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 text-xs font-bold text-gray-700 dark:bg-gray-700 dark:text-gray-200">{{ $item['initials'] }}</span>
                            @endif

                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium text-gray-800 dark:text-gray-200">{{ $item['name'] }}</div>
                            </div>

                            @if ((int) $item['alert_count'] > 0)
                                <span class="inline-flex min-w-6 items-center justify-center rounded-full bg-red-500 px-1.5 py-0.5 text-xs font-semibold text-white">
                                    {{ $item['alert_count'] }}
                                </span>
                            @endif
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    </div>
@endif
