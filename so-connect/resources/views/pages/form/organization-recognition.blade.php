@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Application for Recognition/Renewal of Student Organization" />

    <div class="space-y-6">

        {{-- ── FORM HEADER ─────────────────────────────────────────────── --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="mb-1 text-center">
                <h2 class="text-xl font-bold uppercase tracking-widest text-gray-900 dark:text-white">
                    Application for Recognition/Renewal of Student Organization
                </h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Fields marked <span class="text-error-500">*</span> are required.
                </p>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-800 dark:bg-success-900/20 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('organization-recognition.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            {{-- ── SECTION 1 · PLEASE CHECK ────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Please Check</h3>

                <div class="space-y-3">
                    @if ($isAdmin)
                        <label class="flex cursor-pointer items-center gap-3">
                            <input type="checkbox" name="c1" value="1"
                                class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600"
                                {{ old('c1') ? 'checked' : '' }} />
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                <span class="font-semibold">Recognition</span>
                                <span class="ml-2 rounded bg-brand-100 px-1.5 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-400">Admin only</span>
                            </span>
                        </label>
                    @endif

                    <label class="flex cursor-pointer items-center gap-3">
                        <input type="checkbox" name="c2" value="1"
                            class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600"
                            {{ old('c2') ? 'checked' : '' }} />
                        <span class="text-sm text-gray-700 dark:text-gray-300 font-semibold">Renewal</span>
                    </label>
                </div>
            </div>

            {{-- ── SECTION 2 · BASIC INFORMATION ───────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6"
                x-data="{
                    freshman: '',
                    sophomore: '',
                    junior: '',
                    get total() { return (parseInt(this.freshman) || 0) + (parseInt(this.sophomore) || 0) + (parseInt(this.junior) || 0); }
                }">
                <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">I. Basic Information</h3>

                <div class="space-y-4">

                    {{-- Organization --}}
                    <div>
                        <label for="organization" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Name of Organization <span class="text-error-500">*</span>
                        </label>
                        @if (!$isAdmin && $organizations->count() > 1)
                            <select id="organization_id" name="organization_id"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90 mb-2"
                                x-on:change="document.getElementById('organization').value = $event.target.options[$event.target.selectedIndex].dataset.name">
                                <option value="">Select organization</option>
                                @foreach ($organizations as $org)
                                    <option value="{{ $org->organization_id }}"
                                        data-name="{{ $org->organization_name }}"
                                        {{ old('organization_id') == $org->organization_id ? 'selected' : '' }}>
                                        {{ $org->organization_name }}
                                    </option>
                                @endforeach
                            </select>
                        @elseif (!$isAdmin && $organizationId)
                            <input type="hidden" name="organization_id" value="{{ $organizationId }}" />
                        @endif
                        <input type="text" id="organization" name="organization"
                            placeholder="Name of student organization"
                            value="{{ old('organization', $organizations->count() === 1 ? $organizations->first()->organization_name : '') }}"
                            {{ !$isAdmin && $organizations->count() === 1 ? 'readonly' : '' }}
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90 {{ !$isAdmin && $organizations->count() === 1 ? 'bg-gray-50 dark:bg-gray-900/20' : '' }}" />
                        @error('organization')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- President --}}
                    <div>
                        <label for="name_of_president" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            President <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="name_of_president" name="name_of_president"
                            placeholder="Full name of president"
                            value="{{ old('name_of_president', $presidentName) }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('name_of_president')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Faculty Advisers --}}
                    <div>
                        <label for="name_of_adviser_s" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Faculty Adviser/s
                        </label>
                        <input type="text" id="name_of_adviser_s" name="name_of_adviser_s"
                            placeholder="Name(s) of faculty adviser(s)"
                            value="{{ old('name_of_adviser_s') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    {{-- Date of 1st Recognition --}}
                    <div>
                        <label for="date" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Date of 1st Recognition
                        </label>
                        <input type="date" id="date" name="date"
                            value="{{ old('date') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    {{-- Member Counts --}}
                    <div>
                        <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-400">No. of Members</p>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div>
                                <label for="freshman" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-500">Freshman</label>
                                <input type="number" id="freshman" name="freshman" min="0"
                                    placeholder="0"
                                    value="{{ old('freshman') }}"
                                    x-model="freshman"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            </div>
                            <div>
                                <label for="sophomore" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-500">Sophomore</label>
                                <input type="number" id="sophomore" name="sophomore" min="0"
                                    placeholder="0"
                                    value="{{ old('sophomore') }}"
                                    x-model="sophomore"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            </div>
                            <div>
                                <label for="junior" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-500">Junior</label>
                                <input type="number" id="junior" name="junior" min="0"
                                    placeholder="0"
                                    value="{{ old('junior') }}"
                                    x-model="junior"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            </div>
                            <div>
                                <label for="total" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-500">Total</label>
                                <input type="number" id="total" name="total" min="0"
                                    placeholder="0"
                                    :value="total"
                                    readonly
                                    class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-2.5 text-sm text-gray-800 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900/20 dark:text-white/90" />
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ── SECTION 3 · OBJECTIVES ───────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">II. Objectives</h3>
                <textarea id="objectives" name="objectives" rows="6"
                    placeholder="State the objectives of the organization..."
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ old('objectives') }}</textarea>
            </div>

            {{-- ── SECTION 4 · WORKPLAN ─────────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">III. Workplan</h3>
                <p class="mb-3 text-xs text-gray-400 dark:text-gray-500">(Attach additional sheets if necessary)</p>
                <textarea id="workplan" name="workplan" rows="8"
                    placeholder="Describe the planned activities and workplan..."
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ old('workplan') }}</textarea>
            </div>

            {{-- ── SECTION 5 · PRESIDENT SIGNATURE ─────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">President</h3>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:items-end">
                    <div>
                        <label for="president_name_display" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Name of President
                        </label>
                        <input type="text" id="president_name_display" disabled
                            value="{{ old('name_of_president', $presidentName) }}"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/20 dark:text-gray-400" />
                        <p class="mt-1 text-center text-xs text-gray-400 dark:text-gray-500">Signature over Printed Name</p>
                    </div>

                    <div x-data="{ preview: null }">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Signature
                            <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">(JPG/PNG, max 2 MB)</span>
                        </label>
                        <label for="signature_president"
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
                            <input id="signature_president" name="signature_president" type="file" accept="image/jpeg,image/png"
                                class="hidden"
                                @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                        </label>
                        @error('signature_president')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- ── SECTION 6 · ADVISERS ─────────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Adviser/s</h3>

                {{-- Adviser 1 --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:items-end">
                    <div>
                        <label for="name_of_adviser_1" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Adviser 1 – Full Name
                        </label>
                        <input type="text" id="name_of_adviser_1" name="name_of_adviser_1"
                            placeholder="Adviser's full name"
                            value="{{ old('name_of_adviser_1') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    <div x-data="{ preview: null }">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Adviser 1 – Signature
                        </label>
                        <label for="signature_1"
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
                            <input id="signature_1" name="signature_1" type="file" accept="image/jpeg,image/png"
                                class="hidden"
                                @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                        </label>
                        @error('signature_1')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Adviser 2 (optional) --}}
                <div class="mt-6" x-data="{ enabled: false }">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-400">Adviser 2 (optional)</p>
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

                    <div x-show="enabled" x-transition class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 sm:items-end">
                        <div>
                            <label for="name_of_adviser_2" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Adviser 2 – Full Name
                            </label>
                            <input type="text" id="name_of_adviser_2" name="name_of_adviser_2"
                                placeholder="Adviser's full name"
                                value="{{ old('name_of_adviser_2') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>

                        <div x-data="{ preview: null }">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Adviser 2 – Signature
                            </label>
                            <label for="signature_2"
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
                                <input id="signature_2" name="signature_2" type="file" accept="image/jpeg,image/png"
                                    class="hidden"
                                    @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                            </label>
                            @error('signature_2')
                                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── SECTION 7 · APPROVAL SIGNATURES (Admin only) ────────── --}}
            @if ($isAdmin)
                <div class="rounded-2xl border border-brand-200 bg-brand-50/50 p-5 dark:border-brand-800 dark:bg-brand-900/10 lg:p-6">
                    <div class="mb-4 flex items-center gap-2">
                        <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Approval Signatures</h3>
                        <span class="rounded bg-brand-100 px-1.5 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-400">Admin only</span>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">

                        {{-- Recommending Approval --}}
                        <div class="space-y-3">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-400">Recommending Approval</p>
                            <div>
                                <label for="chair" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-500">
                                    Chair, Student Organizations
                                </label>
                                <input type="text" id="chair" name="chair"
                                    placeholder="Name of chair"
                                    value="{{ old('chair') }}"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            </div>
                            <div x-data="{ preview: null }">
                                <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-500">
                                    Signature <span class="font-normal text-gray-400">(JPG/PNG, max 2 MB)</span>
                                </label>
                                <label for="signature_3"
                                    class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-white px-4 py-4 transition hover:border-brand-400 hover:bg-brand-50 dark:border-gray-700 dark:bg-gray-900/30 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
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
                                        x-text="preview ? 'Change signature' : 'Click to upload'"></span>
                                    <input id="signature_3" name="signature_3" type="file" accept="image/jpeg,image/png"
                                        class="hidden"
                                        @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                                </label>
                                @error('signature_3')
                                    <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Approved --}}
                        <div class="space-y-3">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-400">Approved</p>
                            <div>
                                <label for="director" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-500">
                                    Director, Student Services and Development
                                </label>
                                <input type="text" id="director" name="director"
                                    placeholder="Name of director"
                                    value="{{ old('director') }}"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            </div>
                            <div x-data="{ preview: null }">
                                <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-500">
                                    Signature <span class="font-normal text-gray-400">(JPG/PNG, max 2 MB)</span>
                                </label>
                                <label for="signature_4"
                                    class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-white px-4 py-4 transition hover:border-brand-400 hover:bg-brand-50 dark:border-gray-700 dark:bg-gray-900/30 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
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
                                        x-text="preview ? 'Change signature' : 'Click to upload'"></span>
                                    <input id="signature_4" name="signature_4" type="file" accept="image/jpeg,image/png"
                                        class="hidden"
                                        @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                                </label>
                                @error('signature_4')
                                    <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                    </div>
                </div>
            @endif

            {{-- ── SECTION 8 · SUBMIT ───────────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="flex justify-end gap-3">
                    <button type="reset"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        Clear
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        {{ $isAdmin ? 'Submit & Auto-Approve' : 'Submit for Approval' }}
                    </button>
                </div>
            </div>

        </form>
    </div>
@endsection
