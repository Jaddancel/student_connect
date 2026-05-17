@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Directory of Student Leader" />

    <div class="space-y-6">

        {{-- ── FORM HEADER ─────────────────────────────────────────────── --}}
        <div class="rounded-2xl border border-gray-200 bg-palette-lime-pale p-5 shadow-[inset_0_4px_0_var(--color-palette-lime)] dark:border-gray-800 dark:bg-white/[0.03] dark:shadow-[inset_0_4px_0_rgb(165_255_91_/_0.3)] lg:p-6">
            <div class="mb-1 text-center">
                <h2 class="text-xl font-bold uppercase tracking-widest text-gray-900 dark:text-white">
                    Directory of Student Leader
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Complete all fields accurately. Fields marked <span class="text-error-500">*</span> are required.
                </p>
            </div>
        </div>

        <form action="#" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            {{-- ── SECTION 1 · PERIOD & IDENTITY ───────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Period &amp; Identity</h3>

                {{-- Semester / Season / School Year --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="semester" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Semester <span class="text-error-500">*</span>
                        </label>
                        <select id="semester" name="semester"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                            <option value="">Select semester</option>
                            <option value="1st">1st Semester</option>
                            <option value="2nd">2nd Semester</option>
                            <option value="summer">Summer</option>
                        </select>
                    </div>

                    <div>
                        <label for="season" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Season <span class="text-error-500">*</span>
                        </label>
                        <select id="season" name="season"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                            <option value="">Select season</option>
                            <option value="summer">Summer</option>
                            <option value="fall">Fall</option>
                        </select>
                    </div>

                    <div>
                        <label for="school_year" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            First School Year <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="school_year" name="school_year" placeholder="e.g. 2024–2025"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                </div>

                {{-- Name / Position / Contact --}}
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Name <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="name" name="name" placeholder="Full name"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    <div>
                        <label for="position" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Position <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="position" name="position" placeholder="e.g. President"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    <div>
                        <label for="contact_number"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Contact Number <span class="text-error-500">*</span>
                        </label>
                        <input type="tel" id="contact_number" name="contact_number" placeholder="e.g. 09XX-XXX-XXXX"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                </div>

                {{-- Photo --}}
                <div class="mt-4" x-data="{ preview: null }">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Photo <span class="text-error-500">*</span>
                        <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">(2×2 ID photo,
                            JPG/PNG)</span>
                    </label>
                    <div class="flex items-start gap-4">
                        <div
                            class="flex h-28 w-24 shrink-0 items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/30">
                            <template x-if="preview">
                                <img :src="preview" class="h-full w-full object-cover" alt="Photo preview" />
                            </template>
                            <template x-if="!preview">
                                <span class="text-center text-xs text-gray-400 dark:text-gray-500 px-2">No photo</span>
                            </template>
                        </div>
                        <div class="flex-1">
                            <label for="photo"
                                class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-5 transition hover:border-brand-400 hover:bg-brand-50 dark:border-gray-700 dark:bg-gray-900/30 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                                <svg class="mb-2 h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M12 16v-8m-4 4h8M20.25 6.375c0 .621-.504 1.125-1.125 1.125H4.875A1.125 1.125 0 013.75 6.375V5.625A1.125 1.125 0 014.875 4.5h14.25A1.125 1.125 0 0120.25 5.625v.75zM4.5 7.5h15V18a1.5 1.5 0 01-1.5 1.5h-12A1.5 1.5 0 014.5 18V7.5z" />
                                </svg>
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Click to upload
                                    photo</span>
                                <span class="mt-1 text-xs text-gray-400 dark:text-gray-500">JPG, PNG — max 2 MB</span>
                                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png"
                                    class="hidden"
                                    @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Organization / Faculty Advisers --}}
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="organization" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Name of Organization <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="organization" name="organization" placeholder="Organization name"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    <div>
                        <label for="faculty_advisers"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Name of Faculty Advisers
                        </label>
                        <input type="text" id="faculty_advisers" name="faculty_advisers"
                            placeholder="Faculty adviser name(s)"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                </div>
            </div>

            {{-- ── SECTION 2 · BASIC INFORMATION ───────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Basic Information</h3>

                <div class="space-y-4">

                    {{-- 1. Age / Sex / Religion --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label for="age"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                <span class="mr-1 font-semibold text-gray-500 dark:text-gray-500">1.</span>
                                Age <span class="text-error-500">*</span>
                            </label>
                            <input type="number" id="age" name="age" min="1" max="99"
                                placeholder="Age"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>

                        <div>
                            <label for="sex"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Sex <span class="text-error-500">*</span>
                            </label>
                            <select id="sex" name="sex"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>

                        <div>
                            <label for="religious_affiliation"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Religious Affiliation
                            </label>
                            <input type="text" id="religious_affiliation" name="religious_affiliation"
                                placeholder="e.g. Roman Catholic"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                    </div>

                    {{-- 2. Nationality --}}
                    <div>
                        <label for="nationality"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            <span class="mr-1 font-semibold text-gray-500 dark:text-gray-500">2.</span>
                            Nationality <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="nationality" name="nationality" placeholder="e.g. Filipino"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    {{-- 3. Birthplace --}}
                    <div>
                        <label for="birthplace" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            <span class="mr-1 font-semibold text-gray-500 dark:text-gray-500">3.</span>
                            Birthplace
                        </label>
                        <input type="text" id="birthplace" name="birthplace" placeholder="City / Province"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    {{-- 4. Birthday --}}
                    <div>
                        <label for="birthday" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            <span class="mr-1 font-semibold text-gray-500 dark:text-gray-500">4.</span>
                            Birthday <span class="text-error-500">*</span>
                        </label>
                        <input type="date" id="birthday" name="birthday"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    {{-- 5. Present Address --}}
                    <div>
                        <label for="present_address"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            <span class="mr-1 font-semibold text-gray-500 dark:text-gray-500">5.</span>
                            Present Address <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="present_address" name="present_address"
                            placeholder="Street, Barangay, City / Municipality"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    {{-- 6. Home Address --}}
                    <div>
                        <label for="home_address"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            <span class="mr-1 font-semibold text-gray-500 dark:text-gray-500">6.</span>
                            Home Address
                        </label>
                        <input type="text" id="home_address" name="home_address"
                            placeholder="Permanent home address (if different)"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    {{-- 7. Parents / Guardian --}}
                    <div>
                        <label for="parents_guardian"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            <span class="mr-1 font-semibold text-gray-500 dark:text-gray-500">7.</span>
                            Parents / Guardian <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="parents_guardian" name="parents_guardian"
                            placeholder="Name of parent or guardian"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    {{-- 8. Course / Year Level --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="course"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                <span class="mr-1 font-semibold text-gray-500 dark:text-gray-500">8.</span>
                                Course <span class="text-error-500">*</span>
                            </label>
                            <input type="text" id="course" name="course"
                                placeholder="e.g. BS Computer Science"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>

                        <div>
                            <label for="year_level"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Year Level <span class="text-error-500">*</span>
                            </label>
                            <input type="text" id="year_level" name="year_level"
                                placeholder="e.g. 3rd Year"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                    </div>

                    {{-- 9. Talents and Hobbies --}}
                    <div>
                        <label for="talents_hobbies"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            <span class="mr-1 font-semibold text-gray-500 dark:text-gray-500">9.</span>
                            Talents and Hobbies
                        </label>
                        <textarea id="talents_hobbies" name="talents_hobbies" rows="3" placeholder="List your talents and hobbies"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90"></textarea>
                    </div>

                    {{-- 10. Source of Financial Support --}}
                    <div x-data="{
                        scholarship: false,
                        others: false
                    }">
                        <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-400">
                            <span class="mr-1 font-semibold text-gray-500 dark:text-gray-500">10.</span>
                            Source of Financial Support
                            <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">(Please check all that
                                apply)</span>
                        </p>

                        <div
                            class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/30 space-y-3">

                            {{-- C1: Parents/Guardians --}}
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="financial_support[]" value="parents_guardians"
                                    class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Parents / Guardians</span>
                            </label>

                            {{-- C2: Scholarship --}}
                            <div class="space-y-2">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="financial_support[]" value="scholarship"
                                        x-model="scholarship"
                                        class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Scholarship</span>
                                </label>
                                <div x-show="scholarship" x-transition class="pl-7">
                                    <input type="text" name="scholar_provider"
                                        placeholder="Specify scholarship provider"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                </div>
                            </div>

                            {{-- C3: Assistantship --}}
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="financial_support[]" value="assistantship"
                                    class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Assistantship</span>
                            </label>

                            {{-- C4: Others --}}
                            <div class="space-y-2">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="financial_support[]" value="others" x-model="others"
                                        class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Others</span>
                                </label>
                                <div x-show="others" x-transition class="pl-7">
                                    <input type="text" name="others_specify" placeholder="Specify other source"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>

            {{-- ── SECTION 3 · SUBMISSION ───────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 sm:items-end">

                    {{-- Date Filed --}}
                    <div>
                        <label for="date_filed" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Date Filed <span class="text-error-500">*</span>
                        </label>
                        <input type="date" id="date_filed" name="date_filed"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    {{-- Signature --}}
                    <div x-data="{ preview: null }">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Signature <span class="text-error-500">*</span>
                            <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">(photo of signature,
                                JPG/PNG)</span>
                        </label>
                        <label for="signature"
                            class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-4 transition hover:border-brand-400 hover:bg-brand-50 dark:border-gray-700 dark:bg-gray-900/30 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                            <template x-if="preview">
                                <img :src="preview" class="mb-2 max-h-16 object-contain"
                                    alt="Signature preview" />
                            </template>
                            <template x-if="!preview">
                                <svg class="mb-2 h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M16.862 3.487a2.25 2.25 0 113.182 3.182L8.5 18.213l-4.5 1 1-4.5L16.862 3.487z" />
                                </svg>
                            </template>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400"
                                x-text="preview ? 'Change signature' : 'Click to upload signature'"></span>
                            <span class="mt-1 text-xs text-gray-400 dark:text-gray-500">JPG, PNG — max 2 MB</span>
                            <input id="signature" name="signature" type="file" accept="image/jpeg,image/png"
                                class="hidden"
                                @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                        </label>
                        <p class="mt-1 text-center text-xs text-gray-400 dark:text-gray-500">Signature over Printed Name
                        </p>
                    </div>

                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="reset"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        Clear
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Submit
                    </button>
                </div>
            </div>

        </form>
    </div>
@endsection
