<div class="grid grid-cols-1 gap-5 sm:grid-cols-2 ">
    <div class="sm:col-span-1">
        <label for="country" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Country
        </label>
        <div x-data="{ isOptionSelected: false, countries: ['North Korea', 'Agartha', 'Taiwan'] }" class="relative z-20 bg-transparent">
            <select
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                :class="isOptionSelected && 'text-gray-800 dark:text-white/90'" @change="isOptionSelected = true">
                <option value="" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                    Select Option
                </option>
                <template x-for="country in countries">
                    <option x-text="country" value=""
                        class="text-gray-700 dark:bg-gray-900 dark:text-gray-400 capitalize">
                    </option>
                </template>
            </select>
            <span
                class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-700 dark:text-gray-400">
                <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5"
                        stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
        </div>
    </div>

    <div class="sm:col-span-1">
        <label for="province" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Province/State
        </label>
        <div x-data="{ isOptionSelected: false, provinces: ['Tarlac', 'Metro Manila', 'Pampanga'] }" class="relative z-20 bg-transparent">
            <select
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                id = "province" :class="isOptionSelected && 'text-gray-800 dark:text-white/90'"
                @change="isOptionSelected = true">
                <option value="" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                    Select Option
                </option>
                <template x-for="province in provinces">
                    <option x-text="province" value=""
                        class="text-gray-700 dark:bg-gray-900 dark:text-gray-400 capitalize">
                    </option>
                </template>
            </select>
            <span
                class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-700 dark:text-gray-400">
                <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5"
                        stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
        </div>
    </div>
    <div class="sm:col-span-1">
        <label for="city" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Municipality/City
        </label>
        <div x-data="{ isOptionSelected: false, cities: ['Biringan', 'Manila', 'Tarlac City'] }" class="relative z-20 bg-transparent">
            <select
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                id = "city" :class="isOptionSelected && 'text-gray-800 dark:text-white/90'"
                @change="isOptionSelected = true">
                <option value="" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                    Select Option
                </option>
                <template x-for="city in cities">
                    <option x-text="city" value=""
                        class="text-gray-700 dark:bg-gray-900 dark:text-gray-400 capitalize">
                    </option>
                </template>
            </select>
            <span
                class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-700 dark:text-gray-400">
                <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5"
                        stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
        </div>
    </div>
    <div class="sm:col-span-1">
        <label for="barangay" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Barangay
        </label>
        <div x-data="{ isOptionSelected: false, barangays: ['1', '2', '3'] }" class="relative z-20 bg-transparent">
            <select
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                id = "barangay" :class="isOptionSelected && 'text-gray-800 dark:text-white/90'"
                @change="isOptionSelected = true">
                <option value="" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                    Select Option
                </option>
                <template x-for="barangay in barangays">
                    <option x-text="barangay" value=""
                        class="text-gray-700 dark:bg-gray-900 dark:text-gray-400 capitalize">
                    </option>
                </template>
            </select>
            <span
                class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-700 dark:text-gray-400">
                <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5"
                        stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
        </div>
    </div>
</div>
