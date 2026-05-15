@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Joint Statement of Involvement/Commitment" />

    <div class="space-y-6">

        {{-- ── FORM HEADER ─────────────────────────────────────────────── --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="mb-1 text-center">
                <h2 class="text-xl font-bold uppercase tracking-widest text-gray-900 dark:text-white">
                    Joint Statement of Involvement/Commitment
                </h2>
                <p class="mt-1 text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">
                    by Adviser/s and President of Student Organization
                </p>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Fields marked <span class="text-error-500">*</span> are required.
                </p>
            </div>
        </div>

        <form action="#" method="POST" class="space-y-6">
            @csrf

            {{-- ── SECTION 1 · DATE & ORGANIZATION ────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Date &amp; Organization</h3>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Date <span class="text-error-500">*</span>
                        </label>
                        <x-form.date-picker id="date" name="date" placeholder="Select date" />
                    </div>

                    <div>
                        <label for="organization" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Organization <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="organization" name="organization" placeholder="Name of student organization"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                </div>

                <div class="mt-4">
                    <label for="category" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Category <span class="text-error-500">*</span>
                    </label>
                    <input type="text" id="category" name="category" placeholder="e.g. Academic, Cultural, Religious"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                </div>

                {{-- Statement preview --}}
                <div class="mt-5 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900/30 dark:text-gray-400">
                    We, the Adviser/s and President of <span class="font-medium italic text-gray-800 dark:text-white/80">[Organization]</span>
                    under <span class="font-medium italic text-gray-800 dark:text-white/80">[Category]</span> (Category) shall abide by all provisions in the *** particularly:
                    <ul class="mt-2 list-none space-y-0.5 pl-4 text-xs text-gray-500 dark:text-gray-500">
                        <li>A. Chapter 5, Article 1 – Student Organizations</li>
                        <li>B. Chapter 5, Article 2 – Recognition and Accreditation of Student Organizations</li>
                        <li>C. Chapter 5, Article 3 – The Faculty Adviser</li>
                        <li>D. Chapter 5, Article 4 – Operation of Student Organization Activities</li>
                    </ul>
                </div>
            </div>

            {{-- ── SECTION 2 · PRESIDENT ────────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">President</h3>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="president_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Full Name <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="president_name" name="president_name" placeholder="President's full name"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    <div>
                        <label for="president_contact" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Contact # <span class="text-error-500">*</span>
                        </label>
                        <input type="tel" id="president_contact" name="president_contact" placeholder="e.g. 09XX-XXX-XXXX"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                </div>

                <div class="mt-4" x-data="{ preview: null }">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Signature <span class="text-error-500">*</span>
                        <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">(photo of signature, JPG/PNG)</span>
                    </label>
                    <label for="president_signature"
                        class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-4 transition hover:border-brand-400 hover:bg-brand-50 dark:border-gray-700 dark:bg-gray-900/30 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                        <template x-if="preview">
                            <img :src="preview" class="mb-2 max-h-16 object-contain" alt="Signature preview" />
                        </template>
                        <template x-if="!preview">
                            <svg class="mb-2 h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M16.862 3.487a2.25 2.25 0 113.182 3.182L8.5 18.213l-4.5 1 1-4.5L16.862 3.487z" />
                            </svg>
                        </template>
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400"
                            x-text="preview ? 'Change signature' : 'Click to upload signature'"></span>
                        <span class="mt-1 text-xs text-gray-400 dark:text-gray-500">JPG, PNG — max 2 MB</span>
                        <input id="president_signature" name="president_signature" type="file" accept="image/jpeg,image/png"
                            class="hidden"
                            @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                    </label>
                </div>
            </div>

            {{-- ── SECTION 3 · ADVISER 1 ────────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Adviser 1 <span class="text-error-500">*</span></h3>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="adviser1_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Full Name <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="adviser1_name" name="adviser1_name" placeholder="Adviser's full name"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    <div>
                        <label for="adviser1_contact" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Contact # <span class="text-error-500">*</span>
                        </label>
                        <input type="tel" id="adviser1_contact" name="adviser1_contact" placeholder="e.g. 09XX-XXX-XXXX"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                </div>

                <div class="mt-4" x-data="{ preview: null }">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Signature <span class="text-error-500">*</span>
                        <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">(photo of signature, JPG/PNG)</span>
                    </label>
                    <label for="adviser1_signature"
                        class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-4 transition hover:border-brand-400 hover:bg-brand-50 dark:border-gray-700 dark:bg-gray-900/30 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                        <template x-if="preview">
                            <img :src="preview" class="mb-2 max-h-16 object-contain" alt="Signature preview" />
                        </template>
                        <template x-if="!preview">
                            <svg class="mb-2 h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M16.862 3.487a2.25 2.25 0 113.182 3.182L8.5 18.213l-4.5 1 1-4.5L16.862 3.487z" />
                            </svg>
                        </template>
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400"
                            x-text="preview ? 'Change signature' : 'Click to upload signature'"></span>
                        <span class="mt-1 text-xs text-gray-400 dark:text-gray-500">JPG, PNG — max 2 MB</span>
                        <input id="adviser1_signature" name="adviser1_signature" type="file" accept="image/jpeg,image/png"
                            class="hidden"
                            @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                    </label>
                </div>
            </div>

            {{-- ── SECTION 4 · ADVISER 2 (optional) ───────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6"
                x-data="{ enabled: false }">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Adviser 2</h3>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <span>Add second adviser</span>
                        <button type="button" @click="enabled = !enabled"
                            :class="enabled ? 'bg-brand-500' : 'bg-gray-200 dark:bg-gray-700'"
                            class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors duration-200 focus:outline-none">
                            <span :class="enabled ? 'translate-x-5' : 'translate-x-1'"
                                class="mt-1 inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200"></span>
                        </button>
                    </label>
                </div>

                <div x-show="enabled" x-transition class="mt-4 space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="adviser2_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Full Name
                            </label>
                            <input type="text" id="adviser2_name" name="adviser2_name" placeholder="Adviser's full name"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>

                        <div>
                            <label for="adviser2_contact" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Contact #
                            </label>
                            <input type="tel" id="adviser2_contact" name="adviser2_contact" placeholder="e.g. 09XX-XXX-XXXX"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                    </div>

                    <div x-data="{ preview: null }">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Signature
                            <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">(photo of signature, JPG/PNG)</span>
                        </label>
                        <label for="adviser2_signature"
                            class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-4 transition hover:border-brand-400 hover:bg-brand-50 dark:border-gray-700 dark:bg-gray-900/30 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                            <template x-if="preview">
                                <img :src="preview" class="mb-2 max-h-16 object-contain" alt="Signature preview" />
                            </template>
                            <template x-if="!preview">
                                <svg class="mb-2 h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M16.862 3.487a2.25 2.25 0 113.182 3.182L8.5 18.213l-4.5 1 1-4.5L16.862 3.487z" />
                                </svg>
                            </template>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400"
                                x-text="preview ? 'Change signature' : 'Click to upload signature'"></span>
                            <span class="mt-1 text-xs text-gray-400 dark:text-gray-500">JPG, PNG — max 2 MB</span>
                            <input id="adviser2_signature" name="adviser2_signature" type="file" accept="image/jpeg,image/png"
                                class="hidden"
                                @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                        </label>
                    </div>
                </div>
            </div>

            {{-- ── SECTION 5 · ACTIONS ──────────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="flex justify-end gap-3">
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
