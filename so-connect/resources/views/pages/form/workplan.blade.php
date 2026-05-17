@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Workplan" />

    <div class="space-y-6">

        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-palette-lime-pale p-5 shadow-[inset_0_4px_0_var(--color-palette-lime)] dark:border-gray-800 dark:bg-white/[0.03] dark:shadow-[inset_0_4px_0_rgb(165_255_91_/_0.3)] lg:p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-white uppercase tracking-wide text-center">Republic of the Philippines</h2>
            <p class="text-center text-sm font-semibold text-gray-800 dark:text-white/90 mt-0.5">TARLAC AGRICULTURAL UNIVERSITY</p>
            <p class="text-center text-xs text-gray-500 dark:text-gray-400">Camiling, Tarlac</p>
            <p class="text-center text-sm font-medium text-gray-700 dark:text-gray-300 mt-2">OFFICE OF STUDENT SERVICES AND DEVELOPMENT</p>
            <p class="text-center text-xs text-gray-500 dark:text-gray-400">Student Development Unit</p>
            <p class="text-center text-lg font-bold text-gray-900 dark:text-white mt-3 tracking-widest">WORKPLAN</p>
        </div>

        <form action="{{ route('workplan.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6"
            x-data="{
                activities: [{ title: '', targetDate: '', resources: '', people: '' }],
                addRow() { this.activities.push({ title: '', targetDate: '', resources: '', people: '' }); },
                removeRow(i) { if (this.activities.length > 1) this.activities.splice(i, 1); },
            }">
            @csrf

            @if ($errors->any())
                <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                    @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            {{-- SECTION 1 · ORGANIZATION & SCHOOL YEAR --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Organization Details</h3>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {{-- Organization --}}
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
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

                    {{-- School Year --}}
                    <div>
                        <label for="schoolYear" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            School Year <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="schoolYear" name="schoolYear"
                            value="{{ old('schoolYear') }}"
                            placeholder="e.g. 2024–2025"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('schoolYear')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- SECTION 2 · PLANNED ACTIVITIES --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Planned Activities</h3>
                    <button type="button" @click="addRow()"
                        class="flex items-center gap-1.5 rounded-lg border border-brand-300 px-3 py-1.5 text-xs font-medium text-brand-600 transition hover:bg-brand-50 dark:border-brand-700 dark:text-brand-400 dark:hover:bg-brand-900/20">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Row
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                                <th class="pb-2 pr-3 text-xs font-medium text-gray-500 dark:text-gray-400 w-2/5">Activity / Title</th>
                                <th class="pb-2 pr-3 text-xs font-medium text-gray-500 dark:text-gray-400 w-1/6">Target Date</th>
                                <th class="pb-2 pr-3 text-xs font-medium text-gray-500 dark:text-gray-400">Resources Needed</th>
                                <th class="pb-2 pr-3 text-xs font-medium text-gray-500 dark:text-gray-400">Person/s Responsible</th>
                                <th class="pb-2 w-8"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <template x-for="(row, i) in activities" :key="i">
                                <tr class="group">
                                    <td class="py-2 pr-3">
                                        <input type="text" :name="`activities[${i}][title]`"
                                            x-model="row.title"
                                            placeholder="Activity title"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="py-2 pr-3">
                                        <input type="text" :name="`activities[${i}][targetDate]`"
                                            x-model="row.targetDate"
                                            placeholder="e.g. Jan 2025"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="py-2 pr-3">
                                        <input type="text" :name="`activities[${i}][resources]`"
                                            x-model="row.resources"
                                            placeholder="Materials, budget, etc."
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="py-2 pr-3">
                                        <input type="text" :name="`activities[${i}][people]`"
                                            x-model="row.people"
                                            placeholder="Officer/s in charge"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    </td>
                                    <td class="py-2 text-center">
                                        <button type="button" @click="removeRow(i)"
                                            x-show="activities.length > 1"
                                            class="rounded p-1 text-gray-400 transition hover:text-error-500 dark:text-gray-600 dark:hover:text-error-400">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- SECTION 3 · SIGNATURES --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Signatures</h3>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    {{-- President --}}
                    <div class="space-y-3">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-400">Prepared by</p>

                        <div>
                            <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Name <span class="text-error-500">*</span>
                            </label>
                            <input type="text" id="name" name="name"
                                value="{{ old('name', $presidentName) }}"
                                placeholder="Full name"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            @error('name')
                                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div x-data="{ preview: null }">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Signature <span class="ml-1 text-xs font-normal text-gray-400">(JPG/PNG, max 2 MB)</span>
                            </label>
                            <label for="signature"
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
                                <input id="signature" name="signature" type="file" accept="image/jpeg,image/png" class="hidden"
                                    @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                            </label>
                            <p class="mt-1 text-center text-xs text-gray-400 dark:text-gray-500">Signature over Printed Name</p>
                        </div>
                    </div>

                    {{-- Adviser --}}
                    <div class="space-y-3">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-400">Noted by</p>

                        <div>
                            <label for="adviserName" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Adviser Name
                            </label>
                            <input type="text" id="adviserName" name="adviserName"
                                value="{{ old('adviserName') }}"
                                placeholder="Faculty adviser's full name"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>

                        <div x-data="{ preview: null }">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Adviser Signature <span class="ml-1 text-xs font-normal text-gray-400">(JPG/PNG, max 2 MB)</span>
                            </label>
                            <label for="adviserSignature"
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
                                    x-text="preview ? 'Change signature' : 'Click to upload adviser signature'"></span>
                                <input id="adviserSignature" name="adviserSignature" type="file" accept="image/jpeg,image/png" class="hidden"
                                    @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                            </label>
                            <p class="mt-1 text-center text-xs text-gray-400 dark:text-gray-500">Signature over Printed Name of the Adviser</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
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
