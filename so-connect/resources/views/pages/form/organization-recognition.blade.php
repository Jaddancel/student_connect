@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Application for Recognition/Renewal of Student Organization" />

    <div class="space-y-6">

        {{-- ── FORM HEADER ─────────────────────────────────────────────── --}}
        <div class="rounded-2xl border border-gray-200 bg-palette-lime-pale p-5 shadow-[inset_0_4px_0_var(--color-palette-lime)] dark:border-gray-800 dark:bg-white/[0.03] dark:shadow-[inset_0_4px_0_rgb(165_255_91_/_0.3)] lg:p-6">
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
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Please Check</h3>

                <div class="space-y-3">
                    <label class="flex cursor-pointer items-center gap-3">
                        <input type="radio" name="recognition_type" value="c1"
                            class="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600"
                            {{ old('recognition_type') === 'c1' ? 'checked' : '' }} />
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Recognition</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-3">
                        <input type="radio" name="recognition_type" value="c2"
                            class="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600"
                            {{ old('recognition_type') === 'c2' ? 'checked' : '' }} />
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Renewal</span>
                    </label>
                </div>
            </div>

            {{-- ── SECTION 2 · BASIC INFORMATION ───────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6"
                x-data="{
                    freshmanNumber: '',
                    sophomoreNumber: '',
                    juniorNumber: '',
                    get total() { return (parseInt(this.freshmanNumber) || 0) + (parseInt(this.sophomoreNumber) || 0) + (parseInt(this.juniorNumber) || 0); },
                    advisers: {{ Js::from(old('facultyAdvisers', [''])) }},
                    addAdviser() { this.advisers.push(''); },
                    removeAdviser(i) { if (this.advisers.length > 1) this.advisers.splice(i, 1); }
                }">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">I. Basic Information</h3>

                <div class="space-y-4">

                    {{-- Organization --}}
                    <div>
                        <label for="nameOfOrganization" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Name of Organization <span class="text-error-500">*</span>
                        </label>
                        @if (!$isAdmin && $organizations->count() > 1)
                            <select id="organization_id" name="organization_id"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90 mb-2"
                                x-on:change="document.getElementById('nameOfOrganization').value = $event.target.options[$event.target.selectedIndex].dataset.name">
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
                        <input type="text" id="nameOfOrganization" name="nameOfOrganization"
                            placeholder="Name of student organization"
                            value="{{ old('nameOfOrganization', $organizations->count() === 1 ? $organizations->first()->organization_name : '') }}"
                            {{ !$isAdmin && $organizations->count() === 1 ? 'readonly' : '' }}
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90 {{ !$isAdmin && $organizations->count() === 1 ? 'bg-gray-50 dark:bg-gray-900/20' : '' }}" />
                        @error('nameOfOrganization')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- President --}}
                    <div>
                        <label for="presidentName" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            President <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="presidentName" name="presidentName"
                            placeholder="Full name of president"
                            value="{{ old('presidentName', $presidentName) }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('presidentName')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Faculty Advisers --}}
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Faculty Adviser/s <span class="text-error-500">*</span>
                        </label>
                        <div class="space-y-2">
                            <template x-for="(adviser, index) in advisers" :key="index">
                                <div class="flex gap-2 items-center">
                                    <input type="text" :name="'facultyAdvisers[' + index + ']'"
                                        x-model="advisers[index]"
                                        :placeholder="'Adviser ' + (index + 1) + ' full name'"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    <button type="button" @click="removeAdviser(index)"
                                        x-show="advisers.length > 1"
                                        class="flex-shrink-0 rounded-lg border border-error-200 p-2 text-error-500 transition hover:bg-error-50 dark:border-error-500/30 dark:hover:bg-error-500/10">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                        <button type="button" @click="addAdviser()"
                            class="mt-2 flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Adviser
                        </button>
                        @error('facultyAdvisers')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Date of 1st Recognition --}}
                    <div>
                        <label for="recognitionDate" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Date of 1st Recognition
                        </label>
                        <input type="date" id="recognitionDate" name="recognitionDate"
                            value="{{ old('recognitionDate') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    {{-- Member Counts --}}
                    <div>
                        <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-400">No. of Members</p>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div>
                                <label for="freshmanNumber" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-500">Freshman</label>
                                <input type="number" id="freshmanNumber" name="freshmanNumber" min="0"
                                    placeholder="0"
                                    value="{{ old('freshmanNumber') }}"
                                    x-model="freshmanNumber"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            </div>
                            <div>
                                <label for="sophomoreNumber" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-500">Sophomore</label>
                                <input type="number" id="sophomoreNumber" name="sophomoreNumber" min="0"
                                    placeholder="0"
                                    value="{{ old('sophomoreNumber') }}"
                                    x-model="sophomoreNumber"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            </div>
                            <div>
                                <label for="juniorNumber" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-500">Junior</label>
                                <input type="number" id="juniorNumber" name="juniorNumber" min="0"
                                    placeholder="0"
                                    value="{{ old('juniorNumber') }}"
                                    x-model="juniorNumber"
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
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">II. Objectives</h3>
                <textarea id="objectives" name="objectives" rows="6"
                    placeholder="State the objectives of the organization..."
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ old('objectives') }}</textarea>
            </div>

            {{-- ── SECTION 4 · WORKPLAN ─────────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">III. Workplan</h3>
                <p class="mb-3 text-xs text-gray-400 dark:text-gray-500">(Attach additional sheets if necessary)</p>
                <textarea id="workplan" name="workplan" rows="8"
                    placeholder="Describe the planned activities and workplan..."
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ old('workplan') }}</textarea>
            </div>

            {{-- ── SECTION 5 · PRESIDENT ───────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">President</h3>

                <div>
                    <label for="president_name_display" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Signature over Printed Name
                    </label>
                    <input type="text" id="president_name_display" disabled
                        value="{{ old('presidentName', $presidentName) }}"
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/20 dark:text-gray-400" />
                </div>
            </div>

            {{-- ── SECTION 6 · ADVISERS ─────────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Adviser/s</h3>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="adviserLeft" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Adviser
                        </label>
                        <input type="text" id="adviserLeft" name="adviserLeft"
                            placeholder="Adviser name"
                            value="{{ old('adviserLeft') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div>
                        <label for="adviserRight" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Adviser
                        </label>
                        <input type="text" id="adviserRight" name="adviserRight"
                            placeholder="Adviser name"
                            value="{{ old('adviserRight') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                </div>
            </div>

            {{-- ── SECTION 7 · APPROVAL (Admin only) ───────────────────── --}}
            @if ($isAdmin)
                <div class="rounded-2xl border border-brand-200 bg-brand-50/50 p-5 dark:border-brand-800 dark:bg-brand-900/10 lg:p-6">
                    <div class="mb-4 flex items-center gap-2">
                        <h3 class="border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Approval</h3>
                        <span class="rounded bg-brand-100 px-1.5 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-400">Admin only</span>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="chair" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Chair, Student Organizations
                            </label>
                            <input type="text" id="chair" name="chair"
                                placeholder="Name of chair"
                                value="{{ old('chair') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                        <div>
                            <label for="director" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Director, Student Services and Development
                            </label>
                            <input type="text" id="director" name="director"
                                placeholder="Name of director"
                                value="{{ old('director') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                    </div>
                </div>
            @endif

            {{-- ── SECTION 8 · SUBMIT ───────────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
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
