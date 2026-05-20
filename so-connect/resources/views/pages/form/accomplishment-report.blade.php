@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Accomplishment Report" />

    <div class="space-y-6">

        @if (session('success'))
            <div
                class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif
        @if (session('status'))
            <div
                class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm font-medium text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                {{ session('status') }}
            </div>
        @endif

        <div
            class="rounded-2xl border border-gray-200 bg-palette-lime-pale p-5 shadow-[inset_0_4px_0_var(--color-palette-lime)] dark:border-gray-800 dark:bg-white/[0.03] dark:shadow-[inset_0_4px_0_rgb(165_255_91_/_0.3)] lg:p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-white uppercase tracking-wide text-center">Republic of the
                Philippines</h2>
            <p class="text-center text-sm font-semibold text-gray-800 dark:text-white/90 mt-0.5">TARLAC AGRICULTURAL
                UNIVERSITY</p>
            <p class="text-center text-xs text-gray-500 dark:text-gray-400">Camiling, Tarlac</p>
            <p class="text-center text-sm font-medium text-gray-700 dark:text-gray-300 mt-2">OFFICE OF STUDENT SERVICES AND
                DEVELOPMENT</p>
            <p class="text-center text-xs text-gray-500 dark:text-gray-400">Student Development Unit</p>
            <p class="text-center text-lg font-bold text-gray-900 dark:text-white mt-3 tracking-widest">ACCOMPLISHMENT
                REPORT</p>
        </div>

        <form action="{{ route('accomplishment-report.store') }}" method="POST" enctype="multipart/form-data"
            class="space-y-6" x-data="{
                orgId: '{{ $organizations->first()?->organization_id ?? '' }}',
                orgName: '{{ addslashes($organizations->first()?->organization_name ?? '') }}',
                events: {{ Js::from($events) }},
                title: '',
                date: '',
                hasRewards: {{ old('has_rewards') ? 'true' : 'false' }},
                advisers: {{ Js::from(old('adviserRow', [''])) }},
                addAdviser() { this.advisers.push(''); },
                removeAdviser(i) { if (this.advisers.length > 1) this.advisers.splice(i, 1); },
                get filteredEvents() {
                    return this.events.filter(e => String(e.org_id) === String(this.orgId));
                },
                onOrgChange(el) {
                    const opt = el.options[el.selectedIndex];
                    this.orgName = opt ? opt.dataset.name : '';
                    this.title = '';
                    this.date = '';
                },
                onEventSelect(eventId) {
                    const ev = this.filteredEvents.find(e => String(e.event_id) === String(eventId));
                    if (ev) {
                        this.title = ev.title;
                        this.date = ev.date ?? '';
                    }
                },
            }">
            @csrf

            @if ($errors->any())
                <div
                    class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- SECTION 1 · ORGANIZATION & SCHOOL YEAR --}}
            <div
                class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3
                    class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                    Organization Details</h3>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {{-- Organization --}}
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Name of Organization <span class="text-error-500">*</span>
                        </label>
                        @if ($organizations->count() > 1)
                            <select name="organization_id"
                                @change="onOrgChange($el); $el.form.querySelector('[name=organization]').value = orgName"
                                x-init="orgId = '{{ old('organization_id', $organizations->first()?->organization_id ?? '') }}'"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('organization_id') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                @foreach ($organizations as $org)
                                    <option value="{{ $org->organization_id }}" data-name="{{ $org->organization_name }}"
                                        @selected(old('organization_id', $organizations->first()?->organization_id) == $org->organization_id)>
                                        {{ $org->organization_name }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="organization" x-bind:value="orgName"
                                value="{{ old('organization', $organizations->first()?->organization_name ?? '') }}" />
                        @else
                            <input type="text" name="organization"
                                value="{{ old('organization', $organizations->first()?->organization_name ?? '') }}"
                                class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-white/70"
                                readonly />
                            <input type="hidden" name="organization_id"
                                value="{{ $organizations->first()?->organization_id ?? '' }}" />
                        @endif
                    </div>

                    {{-- School Year --}}
                    <div>
                        <label for="schoolYear" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            School Year <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="schoolYear" name="schoolYear"
                            value="{{ old('schoolYear', $currentSchoolYear ?? '') }}" placeholder="e.g. 2024–2025"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('schoolYear') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('schoolYear')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- SECTION 2 · ACTIVITY DETAILS --}}
            <div
                class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3
                    class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                    Activity Details</h3>

                <div class="space-y-4">
                    {{-- Event Select --}}
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Event
                            <span class="ml-1 text-xs font-normal text-gray-400">(select to auto-fill title and date)</span>
                        </label>
                        <select @change="onEventSelect($el.value)"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                            <option value="">— Select an event (optional) —</option>
                            <template x-for="ev in filteredEvents" :key="ev.event_id">
                                <option :value="ev.event_id" x-text="ev.title"></option>
                            </template>
                        </select>
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500" x-show="filteredEvents.length === 0">
                            No events found for this organization.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {{-- Title --}}
                        <div>
                            <label for="title" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Title of Activity <span class="text-error-500">*</span>
                            </label>
                            <input type="text" id="title" name="title" :value="title || '{{ old('title') }}'"
                                x-model="title" placeholder="Activity title"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('title') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            @error('title')
                                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Date --}}
                        <div>
                            <label for="date" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Date <span class="text-error-500">*</span>
                            </label>
                            <input type="date" id="date" name="date" x-model="date" value="{{ old('date') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('date') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            @error('date')
                                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Persons Involved --}}
                    <div>
                        <label for="people" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Persons Involved <span class="text-error-500">*</span>
                        </label>
                        <textarea id="people" name="people" rows="3" placeholder="List all persons involved"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border {{ $errors->has('people') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ old('people') }}</textarea>
                        @error('people')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Problem/s Encountered --}}
                    <div>
                        <label for="problem" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Problem/s Encountered
                        </label>
                        <textarea id="problem" name="problem" rows="3" placeholder="Describe any problems encountered (optional)"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ old('problem') }}</textarea>
                    </div>

                    {{-- Documentation (photos) --}}
                    <div x-data="{ preview: null }">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Documentation
                            <span class="ml-1 text-xs font-normal text-gray-400">(photo/PDF, optional)</span>
                        </label>
                        <label for="photos"
                            class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed {{ $errors->has('photos') ? 'border-error-500 bg-error-50 dark:border-error-500/40 dark:bg-error-500/5' : 'border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/30' }} px-4 py-5 transition hover:border-brand-400 hover:bg-brand-50 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                            <template x-if="preview">
                                <img :src="preview" class="mb-2 max-h-24 object-contain rounded"
                                    alt="Documentation preview" />
                            </template>
                            <template x-if="!preview">
                                <svg class="mb-2 h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                </svg>
                            </template>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400"
                                x-text="preview ? 'Change file' : 'Click to upload documentation'"></span>
                            <span class="mt-1 text-xs text-gray-400 dark:text-gray-500">JPG, PNG, PDF — max 5 MB</span>
                            <input id="photos" name="photos" type="file"
                                accept="image/jpeg,image/png,application/pdf" class="hidden"
                                @change="preview = ($event.target.files[0] && $event.target.files[0].type.startsWith('image/')) ? URL.createObjectURL($event.target.files[0]) : null" />
                        </label>
                    </div>
                </div>
            </div>

            {{-- SECTION 3 · ADDITIONAL INFORMATION --}}
            <div
                class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3
                    class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                    Additional Information</h3>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="minutes_of_meeting"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Minutes of Meeting
                        </label>
                        <input type="number" id="minutes_of_meeting" name="minutes_of_meeting" min="0"
                            value="{{ old('minutes_of_meeting') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('minutes_of_meeting') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('minutes_of_meeting')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="summary_of_expenses"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Summary of Expenses (₱)
                        </label>
                        <input type="number" id="summary_of_expenses" name="summary_of_expenses" step="0.01"
                            min="0" value="{{ old('summary_of_expenses') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('summary_of_expenses') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('summary_of_expenses')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-400">
                            <input type="checkbox" name="has_rewards" value="1" x-model="hasRewards"
                                @checked(old('has_rewards'))
                                class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                            Received Award/s?
                        </label>
                    </div>

                    <div x-show="hasRewards" x-transition class="sm:col-span-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-400">Individual Award?</p>
                            <div class="flex flex-wrap gap-4">
                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="radio" name="is_individual" value="yes" :required="hasRewards"
                                        @checked(old('is_individual') === 'yes')
                                        class="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                    Yes
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="radio" name="is_individual" value="no" :required="hasRewards"
                                        @checked(old('is_individual') === 'no')
                                        class="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                    No
                                </label>
                            </div>
                        </div>

                        <div>
                            <label for="area_scope_of_award"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Area Scope of Award
                            </label>
                            <select id="area_scope_of_award" name="area_scope_of_award" :required="hasRewards"
                                :disabled="!hasRewards"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('area_scope_of_award') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                <option value="">Select area scope</option>
                                <option value="Local" @selected(old('area_scope_of_award') === 'Local')>Local</option>
                                <option value="Provincial" @selected(old('area_scope_of_award') === 'Provincial')>Provincial</option>
                                <option value="Regional" @selected(old('area_scope_of_award') === 'Regional')>Regional</option>
                                <option value="International" @selected(old('area_scope_of_award') === 'International')>International</option>
                            </select>
                            @error('area_scope_of_award')
                                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- SECTION 4 · SIGNATURES --}}
            <div
                class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3
                    class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                    Signatures</h3>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    {{-- Prepared By --}}
                    <div class="space-y-3">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-400">Prepared by <span
                                class="text-error-500">*</span></p>

                        <div>
                            <label for="name"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Name <span class="text-error-500">*</span>
                            </label>
                            <input type="text" id="name" name="name"
                                value="{{ old('name', $presidentName) }}" placeholder="Full name"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('name') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            @error('name')
                                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div x-data="{ preview: null }">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Signature <span class="text-error-500">*</span>
                                <span class="ml-1 text-xs font-normal text-gray-400">(photo of signature)</span>
                            </label>
                            <label for="signature"
                                class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed {{ $errors->has('signature') ? 'border-error-500 bg-error-50 dark:border-error-500/40 dark:bg-error-500/5' : 'border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/30' }} px-4 py-4 transition hover:border-brand-400 hover:bg-brand-50 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
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
                            @error('signature')
                                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-center text-xs text-gray-400 dark:text-gray-500">Signature over Printed
                                Name</p>
                        </div>
                    </div>

                    {{-- Noted By --}}
                    <div>
                        <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-400">Noted by</p>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Name of Faculty Adviser/s
                            </label>
                            <div class="space-y-2">
                                <template x-for="(adviser, index) in advisers" :key="index">
                                    <div class="flex gap-2 items-center">
                                        <input type="text" :name="'adviserRow[' + index + ']'"
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
                            @error('adviserRow')
                                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                            @enderror
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
                        Submit &amp; Generate PDF
                    </button>
                </div>
            </div>

        </form>
    </div>
@endsection
