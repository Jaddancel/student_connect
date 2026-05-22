@extends('layouts.app')

@section('content')
<div class="p-5"
    x-data="{
        query: '',
        results: [],
        loading: false,
        searched: false,
        async search() {
            if (this.query.trim().length < 2) {
                this.results = [];
                this.searched = false;
                return;
            }
            this.loading = true;
            this.searched = false;
            try {
                const res = await fetch(`/superadmin/profiles/search?q=${encodeURIComponent(this.query)}&excludeSuperadmin=1`);
                const data = await res.json();
                this.results = data.data ?? [];
            } catch (e) {
                this.results = [];
            }
            this.loading = false;
            this.searched = true;
        },
        avatarColor(name) {
            const colors = [
                'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300',
                'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300',
                'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
                'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
                'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
                'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
                'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-300',
                'bg-teal-100 text-teal-700 dark:bg-teal-500/20 dark:text-teal-300',
            ];
            let hash = 0;
            for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
            return colors[Math.abs(hash) % colors.length];
        },
        initials(first, last) {
            return ((first?.[0] ?? '') + (last?.[0] ?? '')).toUpperCase();
        }
    }">

    <x-common.page-breadcrumb pageTitle="Profile Manager" />

    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">Profile Manager</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Search for a user's profile to view and edit their information.
        </p>
    </div>

    {{-- Search Card --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">

        {{-- Search Input --}}
        <div class="relative mb-6">
            <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M3.04175 9.37363C3.04175 5.87693 5.87711 3.04199 9.37508 3.04199C12.8731 3.04199 15.7084 5.87693 15.7084 9.37363C15.7084 12.8703 12.8731 15.7053 9.37508 15.7053C5.87711 15.7053 3.04175 12.8703 3.04175 9.37363ZM9.37508 1.54199C5.04902 1.54199 1.54175 5.04817 1.54175 9.37363C1.54175 13.6991 5.04902 17.2053 9.37508 17.2053C11.2674 17.2053 13.003 16.5344 14.357 15.4176L17.177 18.238C17.4699 18.5309 17.9448 18.5309 18.2377 18.238C18.5306 17.9451 18.5306 17.4703 18.2377 17.1774L15.418 14.3573C16.5365 13.0033 17.2084 11.2669 17.2084 9.37363C17.2084 5.04817 13.7011 1.54199 9.37508 1.54199Z"
                        fill="currentColor" />
                </svg>
            </span>

            <input
                type="text"
                x-model="query"
                @input.debounce.300ms="search()"
                placeholder="Search by first name, last name…"
                class="h-12 w-full rounded-lg border border-gray-300 bg-transparent pl-11 pr-12 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
            />

            {{-- Loading spinner --}}
            <span x-show="loading" class="absolute right-4 top-1/2 -translate-y-1/2">
                <svg class="h-4 w-4 animate-spin text-brand-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
            </span>
        </div>

        {{-- Initial state --}}
        <div x-show="!searched && !loading" x-transition class="flex flex-col items-center py-12 text-center">
            <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                <svg class="h-7 w-7 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Type a name above to search profiles</p>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Minimum 2 characters required</p>
        </div>

        {{-- No results --}}
        <div x-show="searched && !loading && results.length === 0" x-transition class="flex flex-col items-center py-12 text-center">
            <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                <svg class="h-7 w-7 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                No profiles found matching "<span x-text="query" class="text-gray-800 dark:text-white/80"></span>"
            </p>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Try a different name or check the spelling</p>
        </div>

        {{-- Results --}}
        <div x-show="results.length > 0" x-transition class="space-y-3">
            <p class="mb-4 text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                <span x-text="results.length"></span> result<span x-show="results.length !== 1">s</span> found
            </p>

            <template x-for="profile in results" :key="profile.profile_id">
                <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50 p-4 transition-all duration-150 hover:border-brand-200 hover:bg-brand-50/30 dark:border-gray-800 dark:bg-white/[0.02] dark:hover:border-brand-800 dark:hover:bg-brand-500/5">

                    <div class="flex min-w-0 items-center gap-4">
                        {{-- Avatar --}}
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                            :class="avatarColor((profile.first_name ?? '') + (profile.last_name ?? ''))">
                            <span x-text="initials(profile.first_name, profile.last_name)"></span>
                        </div>

                        {{-- Info --}}
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-800 dark:text-white/90"
                                x-text="[profile.first_name, profile.middle_name, profile.last_name].filter(Boolean).join(' ')">
                            </p>
                            <p x-show="profile.user_email" x-text="profile.user_email"
                                class="truncate text-xs text-gray-500 dark:text-gray-400"></p>

                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                <span x-show="profile.occupation"
                                    class="inline-flex items-center rounded-full border border-gray-200 bg-white px-2 py-0.5 text-xs font-medium text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                                    x-text="profile.occupation"></span>
                                <span x-show="profile.course_year"
                                    class="inline-flex items-center rounded-full border border-brand-200 bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 dark:border-brand-700 dark:bg-brand-500/10 dark:text-brand-400"
                                    x-text="profile.course_year"></span>
                                <span x-show="profile.sex"
                                    class="inline-flex items-center rounded-full border border-gray-200 bg-white px-2 py-0.5 text-xs font-medium text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                                    x-text="profile.sex"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Edit Button --}}
                    <a
                        :href="`/superadmin/profiles/${profile.profile_id}/edit`"
                        class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-3.5 py-2 text-xs font-medium text-white shadow-sm transition hover:bg-brand-600">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 18 18">
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                d="M15.0911 2.78206C14.2125 1.90338 12.7878 1.90338 11.9092 2.78206L4.57524 10.116C4.26682 10.4244 4.0547 10.8158 3.96468 11.2426L3.31231 14.3352C3.25997 14.5833 3.33653 14.841 3.51583 15.0203C3.69512 15.1996 3.95286 15.2761 4.20096 15.2238L7.29355 14.5714C7.72031 14.4814 8.11172 14.2693 8.42013 13.9609L15.7541 6.62695C16.6327 5.74827 16.6327 4.32365 15.7541 3.44497L15.0911 2.78206Z"
                                fill="currentColor"/>
                        </svg>
                        Edit Profile
                    </a>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection
