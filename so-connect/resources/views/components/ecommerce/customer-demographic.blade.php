@props(['countries' => []])

@php
    $defaultCountries = [
        [
            'name' => 'Tarlac City',
            'flag' => '/images/country/philippines.svg',
            'customers' => '1,542',
            'percentage' => 34
        ],
        [
            'name' => 'Capas',
            'flag' => '/images/country/philippines.svg',
            'customers' => '1,220',
            'percentage' => 27
        ],
        [
            'name' => 'Concepcion',
            'flag' => '/images/country/philippines.svg',
            'customers' => '1,008',
            'percentage' => 22
        ],
        [
            'name' => 'Bamban',
            'flag' => '/images/country/philippines.svg',
            'customers' => '790',
            'percentage' => 17
        ],
    ];

    $countriesList = !empty($countries) ? $countries : $defaultCountries;
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
    <div class="flex justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                Customer Demographic
            </h3>
            <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                Number of customers by municipality in Tarlac Province
            </p>
        </div>

        <!-- Dropdown Menu -->
        <x-common.dropdown-menu />
        <!-- End Dropdown Menu -->
    </div>

    <div
        class="my-6 overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
        <div id="mapOne" class="mapOne map-btn h-[212px] w-full">
        </div>
    </div>

    <div class="space-y-5">
        @foreach($countriesList as $country)
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-full max-w-8 items-center rounded-full">
                        <img src="{{ $country['flag'] }}" alt="{{ strtolower($country['name']) }}" />
                    </div>
                    <div>
                        <p class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">
                            {{ $country['name'] }}
                        </p>
                        <span class="block text-theme-xs text-gray-500 dark:text-gray-400">
                            {{ $country['customers'] }} Customers
                        </span>
                    </div>
                </div>

                <div class="flex w-full max-w-[140px] items-center gap-3">
                    <div class="relative block h-2 w-full max-w-[100px] rounded-sm bg-gray-200 dark:bg-gray-800">
                        <div class="absolute left-0 top-0 flex h-full items-center justify-center rounded-sm bg-brand-500 text-xs font-medium text-white"
                            style="width: {{ $country['percentage'] }}%"></div>
                    </div>
                    <p class="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                        {{ $country['percentage'] }}%
                    </p>
                </div>
            </div>
        @endforeach
    </div>
</div>