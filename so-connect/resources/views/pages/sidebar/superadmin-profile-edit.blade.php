@extends('layouts.app')

@section('content')
<div class="p-5">

    <x-common.page-breadcrumb pageTitle="Edit Profile" />

    {{-- Top bar --}}
    <div class="mb-5 flex items-center gap-3">
        <a href="/superadmin/profiles"
            class="inline-flex items-center gap-1.5 text-sm text-gray-500 transition hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/80">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Profile Manager
        </a>
    </div>

    {{-- Alerts --}}
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-success-300 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/40 dark:bg-success-500/10 dark:text-success-400">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-error-300 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/40 dark:bg-error-500/10 dark:text-error-400">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid grid-cols-12 gap-6">

        {{-- ── Left: Form ── --}}
        <div class="col-span-12 xl:col-span-7">
            <form
                x-ref="profileForm"
                method="POST"
                action="{{ route('superadmin.profiles.update', $profile->profile_id) }}"
                x-data="{
                    confirmOpen: false,
                    sex: '{{ old('sex', $profile->sex ?? '') }}',
                    birthday: '{{ old('birthday', $profile->birthday ?? '') }}',
                    age: '{{ old('age', $profile->age ?? '') }}',
                    firstName: '{{ old('first_name', $profile->first_name ?? '') }}',
                    lastName: '{{ old('last_name', $profile->last_name ?? '') }}',
                    courseYear: '{{ old('course_year', $profile->course_year ?? '') }}',
                    computeAge() {
                        if (!this.birthday) return;
                        const today = new Date(), dob = new Date(this.birthday);
                        let y = today.getFullYear() - dob.getFullYear();
                        if (today.getMonth() - dob.getMonth() < 0 || (today.getMonth() - dob.getMonth() === 0 && today.getDate() < dob.getDate())) y--;
                        this.age = y > 0 ? y : '';
                    }
                }">
                @csrf
                @method('PATCH')

                <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">

                    {{-- ── Section 1: Name ── --}}
                    <div class="p-5 lg:p-6">
                        <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Name</p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="first_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    First Name <span class="text-error-500">*</span>
                                </label>
                                <input type="text" id="first_name" name="first_name"
                                    x-model="firstName"
                                    value="{{ old('first_name', $profile->first_name) }}"
                                    required
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                            </div>
                            <div>
                                <label for="last_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Last Name <span class="text-error-500">*</span>
                                </label>
                                <input type="text" id="last_name" name="last_name"
                                    x-model="lastName"
                                    value="{{ old('last_name', $profile->last_name) }}"
                                    required
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                            </div>
                            <div class="sm:col-span-2">
                                <label for="middle_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Middle Name
                                    <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">(optional)</span>
                                </label>
                                <input type="text" id="middle_name" name="middle_name"
                                    value="{{ old('middle_name', $profile->middle_name) }}"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 dark:border-gray-800"></div>

                    {{-- ── Section 2: Personal Details ── --}}
                    <div class="p-5 lg:p-6">
                        <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Personal Details</p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                            {{-- Sex pill toggles --}}
                            <div class="sm:col-span-2">
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400">Sex</label>
                                <div class="flex gap-2">
                                    @foreach (['Male', 'Female'] as $option)
                                        <label class="relative cursor-pointer">
                                            <input type="radio" name="sex" value="{{ $option }}" class="sr-only" x-model="sex" />
                                            <span
                                                class="inline-flex items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-medium transition-all duration-150"
                                                :class="sex === '{{ $option }}'
                                                    ? 'border-brand-300 bg-brand-50 text-brand-700 dark:border-brand-700 dark:bg-brand-500/10 dark:text-brand-400'
                                                    : 'border-gray-300 bg-white text-gray-600 hover:border-gray-400 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 dark:hover:border-gray-600'">
                                                <svg x-show="sex === '{{ $option }}'" class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                                {{ $option }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Birthday --}}
                            <div>
                                <label for="birthday" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Birthday</label>
                                <input type="date" id="birthday" name="birthday"
                                    x-model="birthday"
                                    @change="computeAge()"
                                    max="{{ date('Y-m-d', strtotime('-1 day')) }}"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800" />
                            </div>

                            {{-- Age --}}
                            <div>
                                <label for="age" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Age
                                    <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">(auto-filled)</span>
                                </label>
                                <input type="number" id="age" name="age"
                                    x-model="age"
                                    min="1" max="120" placeholder="—"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                            </div>

                            {{-- Religion --}}
                            <div>
                                <label for="religion" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Religion</label>
                                <input type="text" id="religion" name="religion"
                                    value="{{ old('religion', $profile->religion) }}"
                                    placeholder="e.g. Roman Catholic"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                            </div>

                            {{-- Nationality --}}
                            <div>
                                <label for="nationality" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nationality</label>
                                <input type="text" id="nationality" name="nationality"
                                    value="{{ old('nationality', $profile->nationality) }}"
                                    placeholder="e.g. Filipino"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 dark:border-gray-800"></div>

                    {{-- ── Section 3: Academic & Role ── --}}
                    <div class="p-5 lg:p-6">
                        <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Academic & Role</p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="course_year" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Course &amp; Year</label>
                                <input type="text" id="course_year" name="course_year"
                                    x-model="courseYear"
                                    value="{{ old('course_year', $profile->course_year) }}"
                                    placeholder="e.g. BSCS - 3rd Year"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                            </div>
                            <div>
                                <label for="occupation" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Occupation</label>
                                <input type="text" id="occupation" name="occupation"
                                    value="{{ old('occupation', $profile->occupation) }}"
                                    placeholder="e.g. Student"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800" />
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 dark:border-gray-800"></div>

                    {{-- ── Section 4: Contact ── --}}
                    <div class="p-5 lg:p-6">
                        <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Contact</p>
                        <div>
                            <label for="contact_number" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Contact Number</label>
                            <div class="flex h-11 overflow-hidden rounded-lg border border-gray-300 shadow-theme-xs transition-colors focus-within:border-brand-300 focus-within:ring-3 focus-within:ring-brand-500/10 dark:border-gray-700 dark:focus-within:border-brand-800">
                                <span class="flex items-center border-r border-gray-300 bg-gray-50 px-3 text-sm font-medium text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                    0
                                </span>
                                <input type="text" id="contact_number" name="contact_number"
                                    value="{{ old('contact_number', $profile->contact_number) }}"
                                    placeholder="9XXXXXXXXX" maxlength="10" inputmode="numeric"
                                    class="h-full w-full bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:outline-none dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 dark:border-gray-800"></div>

                    {{-- ── Submit ── --}}
                    <div class="p-5 lg:p-6">
                        <button type="button" @click="confirmOpen = true"
                            class="flex w-full items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            Save Changes
                        </button>
                    </div>
                </div>

                {{-- ── Confirmation Modal ── --}}
                <div
                    x-show="confirmOpen"
                    x-transition:enter="transition duration-200 ease-out"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition duration-150 ease-in"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
                    @click.self="confirmOpen = false">

                    <div
                        x-show="confirmOpen"
                        x-transition:enter="transition duration-200 ease-out"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition duration-150 ease-in"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 mx-4">

                        {{-- Modal Header --}}
                        <div class="mb-5 flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 dark:bg-brand-500/10">
                                <svg class="h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Confirm Profile Changes</h3>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Review the changes before saving.</p>
                            </div>
                        </div>

                        {{-- Summary --}}
                        <div class="mb-6 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-white/[0.03]">
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500 dark:text-gray-400">Name</dt>
                                    <dd class="font-medium text-gray-800 dark:text-white/90 text-right"
                                        x-text="[firstName, lastName].filter(Boolean).join(' ') || '—'"></dd>
                                </div>
                                <div class="flex justify-between gap-4" x-show="courseYear">
                                    <dt class="text-gray-500 dark:text-gray-400">Course & Year</dt>
                                    <dd class="font-medium text-gray-800 dark:text-white/90 text-right" x-text="courseYear"></dd>
                                </div>
                                <div class="flex justify-between gap-4" x-show="sex">
                                    <dt class="text-gray-500 dark:text-gray-400">Sex</dt>
                                    <dd class="font-medium text-gray-800 dark:text-white/90 text-right" x-text="sex"></dd>
                                </div>
                            </dl>
                        </div>

                        {{-- Actions --}}
                        <div class="flex gap-3">
                            <button type="button" @click="confirmOpen = false"
                                class="flex flex-1 justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                                Cancel
                            </button>
                            <button type="button" @click="$refs.profileForm.submit()"
                                class="flex flex-1 justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">
                                Confirm &amp; Save
                            </button>
                        </div>
                    </div>
                </div>

            </form>
        </div>

        {{-- ── Right: Info Card ── --}}
        <div class="col-span-12 xl:col-span-5 flex flex-col gap-4">

            {{-- Profile meta --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">Profile Details</h3>

                <div class="space-y-3">
                    <div>
                        <p class="mb-0.5 text-xs text-gray-400 dark:text-gray-500">Profile ID</p>
                        <p class="font-mono text-sm font-medium text-gray-800 dark:text-white/90">#{{ $profile->profile_id }}</p>
                    </div>

                    @if ($linkedUser)
                        <div>
                            <p class="mb-0.5 text-xs text-gray-400 dark:text-gray-500">Linked Account</p>
                            <p class="text-sm font-medium text-gray-800 dark:text-white/90 break-all">{{ $linkedUser->user_email }}</p>
                        </div>
                    @else
                        <div class="rounded-lg border border-warning-200 bg-warning-50 px-3 py-2.5 dark:border-warning-500/30 dark:bg-warning-500/10">
                            <p class="text-xs font-medium text-warning-700 dark:text-warning-400">No user linked to this profile</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Warning notice --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="flex gap-3">
                    <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-warning-50 dark:bg-warning-500/10">
                        <svg class="h-4 w-4 text-warning-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Immediate effect</p>
                        <p class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                            Changes take effect immediately. The user will see updated information on their profile page.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
