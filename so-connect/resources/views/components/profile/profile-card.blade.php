@php
    $authUser    = auth()->user();
    $authProfile = $authUser?->profile()->first();
    $authAddress = $authProfile?->addressOfUser()->first();

    $fullName  = trim(implode(' ', array_filter([
        $authProfile?->first_name,
        $authProfile?->last_name,
    ])));
    $avatarName = $fullName ?: 'User';

    $occupation = $authProfile?->occupation ?? null;
    $location   = $authAddress?->province ?? null;
@endphp

<div class="mb-6 rounded-2xl border border-gray-200 p-5 lg:p-6 dark:border-gray-800">
    <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">

        {{-- Avatar + identity --}}
        <div class="flex w-full flex-col items-center gap-5 xl:flex-row">
            <div class="h-20 w-20 shrink-0 overflow-hidden rounded-full border border-gray-200 dark:border-gray-800">
                <x-common.initial-avatar :name="$avatarName" size="h-20 w-20" text="text-2xl" class="h-full w-full" />
            </div>

            <div class="text-center xl:text-left">
                <h4 class="mb-2 text-lg font-semibold text-gray-800 dark:text-white/90">
                    {{ $fullName ?: '—' }}
                </h4>

                <div class="flex flex-wrap items-center justify-center gap-2 xl:justify-start">
                    @if ($occupation)
                        <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-medium text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                            {{ $occupation }}
                        </span>
                    @endif

                    @if ($authProfile?->course_year)
                        <span class="inline-flex items-center rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700 dark:border-brand-700 dark:bg-brand-500/10 dark:text-brand-400">
                            {{ $authProfile->course_year }}
                        </span>
                    @endif

                    @if ($authProfile?->sex)
                        <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-medium text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                            {{ $authProfile->sex }}
                        </span>
                    @endif

                    @if ($location)
                        <span class="inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                            </svg>
                            {{ $location }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Edit button (superadmin only) --}}
        @if((int) auth()->user()?->user_type === 1)
        <button @click="$dispatch('open-profile-info-modal')"
            class="shadow-theme-xs flex w-full items-center justify-center gap-2 rounded-full border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 lg:inline-flex lg:w-auto dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
            <svg class="fill-current" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd"
                    d="M15.0911 2.78206C14.2125 1.90338 12.7878 1.90338 11.9092 2.78206L4.57524 10.116C4.26682 10.4244 4.0547 10.8158 3.96468 11.2426L3.31231 14.3352C3.25997 14.5833 3.33653 14.841 3.51583 15.0203C3.69512 15.1996 3.95286 15.2761 4.20096 15.2238L7.29355 14.5714C7.72031 14.4814 8.11172 14.2693 8.42013 13.9609L15.7541 6.62695C16.6327 5.74827 16.6327 4.32365 15.7541 3.44497L15.0911 2.78206ZM12.9698 3.84272C13.2627 3.54982 13.7376 3.54982 14.0305 3.84272L14.6934 4.50563C14.9863 4.79852 14.9863 5.2734 14.6934 5.56629L14.044 6.21573L12.3204 4.49215L12.9698 3.84272ZM11.2597 5.55281L5.6359 11.1766C5.53309 11.2794 5.46238 11.4099 5.43238 11.5522L5.01758 13.5185L6.98394 13.1037C7.1262 13.0737 7.25666 13.003 7.35947 12.9002L12.9833 7.27639L11.2597 5.55281Z"
                    fill="" />
            </svg>
            Edit
        </button>
        @endif
    </div>
</div>

@if((int) auth()->user()?->user_type === 1)
{{-- Profile Info Modal (UI-only — no backend save yet) --}}
<x-ui.modal x-data="{ open: false }" @open-profile-info-modal.window="open = true" :isOpen="false" class="max-w-[700px]">
    <div class="no-scrollbar relative w-full max-w-[700px] overflow-y-auto rounded-3xl bg-white p-4 dark:bg-gray-900 lg:p-11">
        <div class="px-2 pr-14">
            <h4 class="mb-2 text-2xl font-semibold text-gray-800 dark:text-white/90">Edit Profile</h4>
            <p class="mb-6 text-sm text-gray-500 dark:text-gray-400 lg:mb-7">
                Update your details to keep your profile up-to-date.
            </p>
        </div>
        <div class="custom-scrollbar max-h-[500px] overflow-y-auto px-2">
            <div class="grid grid-cols-1 gap-x-6 gap-y-5 lg:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">First Name</label>
                    <input type="text" value="{{ $authProfile?->first_name }}"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Last Name</label>
                    <input type="text" value="{{ $authProfile?->last_name }}"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800" />
                </div>
                <div class="col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Course &amp; Year</label>
                    <input type="text" value="{{ $authProfile?->course_year }}"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800" />
                </div>
            </div>
        </div>
        <div class="mt-6 flex items-center gap-3 px-2 lg:justify-end">
            <button @click="open = false" type="button"
                class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] sm:w-auto">
                Close
            </button>
        </div>
    </div>
</x-ui.modal>
@endif
