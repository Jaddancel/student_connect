@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Accomplishment Report" />

    <div class="space-y-6">

        @if(session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif
        @if(session('status'))
            <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm font-medium text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-palette-lime-pale p-5 shadow-[inset_0_4px_0_var(--color-palette-lime)] dark:border-gray-800 dark:bg-white/[0.03] dark:shadow-[inset_0_4px_0_rgb(165_255_91_/_0.3)] lg:p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-white uppercase tracking-wide text-center">Republic of the Philippines</h2>
            <p class="text-center text-sm font-semibold text-gray-800 dark:text-white/90 mt-0.5">TARLAC AGRICULTURAL UNIVERSITY</p>
            <p class="text-center text-xs text-gray-500 dark:text-gray-400">Camiling, Tarlac</p>
            <p class="text-center text-sm font-medium text-gray-700 dark:text-gray-300 mt-2">OFFICE OF STUDENT SERVICES AND DEVELOPMENT</p>
            <p class="text-center text-xs text-gray-500 dark:text-gray-400">Student Development Unit</p>
            <p class="text-center text-lg font-bold text-gray-900 dark:text-white mt-3 tracking-widest">ACCOMPLISHMENT REPORT</p>
        </div>

        <form action="{{ route('accomplishment-report.store') }}" method="POST" enctype="multipart/form-data"
            class="space-y-6"
            x-data="{
                orgId: '{{ $organizations->first()?->organization_id ?? '' }}',
                orgName: '{{ addslashes($organizations->first()?->organization_name ?? '') }}',
                events: {{ Js::from($events) }},
                title: '',
                date: '',
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

            @if($errors->any())
                <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
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
                        @if($organizations->count() > 1)
                            <select name="organization_id"
                                @change="onOrgChange($el); $el.form.querySelector('[name=organization]').value = orgName"
                                x-init="orgId = '{{ old('organization_id', $organizations->first()?->organization_id ?? '') }}'"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                @foreach($organizations as $org)
                                    <option value="{{ $org->organization_id }}"
                                        data-name="{{ $org->organization_name }}"
                                        @selected(old('organization_id', $organizations->first()?->organization_id) == $org->organization_id)>
                                        {{ $org->organization_name }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="organization" x-bind:value="orgName" value="{{ old('organization', $organizations->first()?->organization_name ?? '') }}" />
                        @else
                            <input type="text" name="organization" value="{{ old('organization', $organizations->first()?->organization_name ?? '') }}"
                                class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-white/70"
                                readonly />
                            <input type="hidden" name="organization_id" value="{{ $organizations->first()?->organization_id ?? '' }}" />
                        @endif
                    </div>

                    {{-- School Year --}}
                    <div>
                        <label for="schoolYear" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            School Year <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="schoolYear" name="schoolYear" value="{{ old('schoolYear') }}"
                            placeholder="e.g. 2024–2025"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                </div>
            </div>

            {{-- SECTION 2 · ACTIVITY DETAILS --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Activity Details</h3>

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
                            <input type="text" id="title" name="title"
                                :value="title || '{{ old('title') }}'"
                                x-model="title"
                                placeholder="Activity title"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>

                        {{-- Date --}}
                        <div>
                            <label for="date" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Date <span class="text-error-500">*</span>
                            </label>
                            <input type="date" id="date" name="date"
                                x-model="date"
                                value="{{ old('date') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                    </div>

                    {{-- Persons Involved --}}
                    <div>
                        <label for="people" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Persons Involved <span class="text-error-500">*</span>
                        </label>
                        <textarea id="people" name="people" rows="3" placeholder="List all persons involved"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ old('people') }}</textarea>
                    </div>

                    {{-- Problem/s Encountered --}}
                    <div>
                        <label for="problem" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Problem/s Encountered
                        </label>
                        <textarea id="problem" name="problem" rows="3" placeholder="Describe any problems encountered (optional)"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ old('problem') }}</textarea>
                    </div>

                    {{-- Documentation (phots) --}}
                    <div x-data="{ preview: null }">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Documentation
                            <span class="ml-1 text-xs font-normal text-gray-400">(photo/PDF, optional)</span>
                        </label>
                        <label for="phots"
                            class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-5 transition hover:border-brand-400 hover:bg-brand-50 dark:border-gray-700 dark:bg-gray-900/30 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                            <template x-if="preview">
                                <img :src="preview" class="mb-2 max-h-24 object-contain rounded" alt="Documentation preview" />
                            </template>
                            <template x-if="!preview">
                                <svg class="mb-2 h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                </svg>
                            </template>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400"
                                x-text="preview ? 'Change file' : 'Click to upload documentation'"></span>
                            <span class="mt-1 text-xs text-gray-400 dark:text-gray-500">JPG, PNG, PDF — max 5 MB</span>
                            <input id="phots" name="phots" type="file" accept="image/jpeg,image/png,application/pdf" class="hidden"
                                @change="preview = ($event.target.files[0] && $event.target.files[0].type.startsWith('image/')) ? URL.createObjectURL($event.target.files[0]) : null" />
                        </label>
                    </div>
                </div>
            </div>

            {{-- SECTION 3 · SIGNATURES --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Signatures</h3>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    {{-- Prepared By --}}
                    <div class="space-y-3">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-400">Prepared by <span class="text-error-500">*</span></p>

                        <div>
                            <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Name <span class="text-error-500">*</span>
                            </label>
                            <input type="text" id="name" name="name"
                                value="{{ old('name', $presidentName) }}"
                                placeholder="Full name"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>

                        <div x-data="{ preview: null }">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Signature <span class="text-error-500">*</span>
                                <span class="ml-1 text-xs font-normal text-gray-400">(photo of signature)</span>
                            </label>
                            <label for="signature1"
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
                                <input id="signature1" name="signature1" type="file" accept="image/jpeg,image/png" class="hidden"
                                    @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                            </label>
                            <p class="mt-1 text-center text-xs text-gray-400 dark:text-gray-500">Signature over Printed Name</p>
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
