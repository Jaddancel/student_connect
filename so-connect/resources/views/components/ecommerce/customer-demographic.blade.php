@props(['countries' => []])

@php
    $defaultCountries = [
        [
            'name' => 'Poblacion (Tarlac City)',
            'flag' => '/images/country/philippines.svg',
            'customers' => '420',
            'percentage' => 18
        ],
        [
            'name' => 'Calingcuan (Tarlac City)',
            'flag' => '/images/country/philippines.svg',
            'customers' => '365',
            'percentage' => 16
        ],
        [
            'name' => 'Matatalaib (Tarlac City)',
            'flag' => '/images/country/philippines.svg',
            'customers' => '330',
            'percentage' => 14
        ],
        [
            'name' => 'Culipat (Tarlac City)',
            'flag' => '/images/country/philippines.svg',
            'customers' => '290',
            'percentage' => 12
        ],
    ];

    $countriesList = !empty($countries) ? $countries : $defaultCountries;
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                Member Demographic
            </h3>
            <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                Number of customers by barangay in Tarlac Province
            </p>
        </div>

        <div class="flex items-center gap-2 sm:gap-3">
            <button type="button" @click="$dispatch('open-demographic-map-modal')"
                class="shadow-theme-xs inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-theme-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200"
                aria-label="Open full screen demographic map">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
                    class="text-current">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M2.66667 1.33398C1.93029 1.33398 1.33333 1.93094 1.33333 2.66732V5.33398H2.66667V2.66732H5.33333V1.33398H2.66667ZM10.6667 1.33398V2.66732H13.3333V5.33398H14.6667V2.66732C14.6667 1.93094 14.0697 1.33398 13.3333 1.33398H10.6667ZM14.6667 10.6673H13.3333V13.334H10.6667V14.6673H13.3333C14.0697 14.6673 14.6667 14.0704 14.6667 13.334V10.6673ZM5.33333 14.6673V13.334H2.66667V10.6673H1.33333V13.334C1.33333 14.0704 1.93029 14.6673 2.66667 14.6673H5.33333Z"
                        fill="currentColor" />
                </svg>
                <span class="hidden sm:inline">Full map</span>
            </button>

            <!-- Dropdown Menu -->
            <x-common.dropdown-menu />
            <!-- End Dropdown Menu -->
        </div>
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

@include('components.ecommerce.customer-demographic-map-modal')