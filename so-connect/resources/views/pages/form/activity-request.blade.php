@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Request for Organizational Activity" />

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
            <p class="text-center text-base font-bold text-gray-900 dark:text-white mt-3 tracking-widest uppercase">Request
                for Organizational Meeting / Services / Projects / Activities</p>
        </div>

        @php
            $alphaPresidentsByOrg = $presidentsByOrg;
            $alphaPresidentName    = old('presidentName', $presidentName);
            $alphaPresidentContact = old('presidentContactNo', $presidentContact);
        @endphp
        <form action="{{ route('activity-request.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6" x-data="{
            orgId: '{{ $organizations->first()?->organization_id ?? '' }}',
            orgName: '{{ addslashes($organizations->first()?->organization_name ?? '') }}',
            presidentsByOrg: {{ Js::from($alphaPresidentsByOrg) }},
            presidentName: {{ Js::from($alphaPresidentName) }},
            presidentContact: {{ Js::from($alphaPresidentContact) }},
            onOrgChange(el) {
                const opt = el.options[el.selectedIndex];
                this.orgName = opt ? opt.dataset.name : '';
                const pres = this.presidentsByOrg[el.value];
                this.presidentName    = pres ? pres.name    : '';
                this.presidentContact = pres ? pres.contact : '';
            },
            facilities: {{ Js::from(old('facilitiesOrEquipmentToBeUsedRow', ['', ''])) }},
            addFacility() {
                if (this.facilities.length < 10) this.facilities.push('');
            },
            removeFacility(i) {
                if (this.facilities.length > 1) this.facilities.splice(i, 1);
            },
            advisers: {{ Js::from(old('adviserRow', [''])) }},
            addAdviser() {
                this.advisers.push('');
            },
            removeAdviser(i) {
                if (this.advisers.length > 1) this.advisers.splice(i, 1);
            },
            activityTypes: {{ Js::from(old('activityTypes', [])) }},
            activityTypeOther: {{ Js::from(old('activityTypeOther', '')) }},
            areaScope: {{ Js::from(old('areaScope', '')) }},
            areaScopeOther: {{ Js::from(old('areaScopeOther', '')) }},
            sponsor: {{ Js::from(old('sponsor', '')) }},
            sponsorOther: {{ Js::from(old('sponsorOther', '')) }},
            extensionServices: {{ Js::from(old('extensionServices', '')) }},
            waiverFileName: '',
            waiverError: '',
            isDraggingWaiver: false,
            waiver: {
                studentName: '', studentId: '', parentName: '', relationship: '',
                activityName: '', activityDate: '', venue: '',
            },
            pickWaiver(fileList) {
                this.waiverError = '';
                const file = fileList && fileList.length ? fileList[0] : null;
                if (!file) { return; }
                const allowed = ['application/pdf', 'image/jpeg', 'image/png'];
                if (!allowed.includes(file.type)) {
                    this.waiverError = 'Accepted file types: PDF, JPG, PNG.';
                    this.waiverFileName = '';
                    this.$refs.waiverInput.value = '';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    this.waiverError = 'File exceeds the 5 MB limit.';
                    this.waiverFileName = '';
                    this.$refs.waiverInput.value = '';
                    return;
                }
                // Reflect a dropped file onto the real input so it submits.
                if (this.$refs.waiverInput.files !== fileList) {
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    this.$refs.waiverInput.files = dt.files;
                }
                this.waiverFileName = file.name;
            },
            clearWaiver() {
                this.waiverFileName = '';
                this.waiverError = '';
                this.$refs.waiverInput.value = '';
            },
            openWaiverGenerator() {
                const params = new URLSearchParams({
                    studentName: this.waiver.studentName,
                    studentId: this.waiver.studentId,
                    parentName: this.waiver.parentName,
                    relationship: this.waiver.relationship,
                    activityName: this.waiver.activityName || this.projectActivityValue(),
                    activityDate: this.waiver.activityDate || (document.querySelector('[name=date]')?.value ?? ''),
                    venue: this.waiver.venue || (document.querySelector('[name=placeAndVenue]')?.value ?? ''),
                });
                window.open('{{ route('activity-request.waiver') }}?' + params.toString(), '_blank');
            },
            projectActivityValue() {
                return document.querySelector('[name=projectActivity]')?.value ?? '';
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

            {{-- SECTION 1 · ORGANIZATION & DATE --}}
            <div
                class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3
                    class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                    Organization Details</h3>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-1">
                    {{-- Organization --}}
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Name of Organization <span class="text-error-500">*</span>
                        </label>
                        @if ($organizations->count() > 1)
                            <select name="organization_id"
                                @change="onOrgChange($el); $el.form.querySelector('[name=organization]').value = orgName"
                                x-init="orgId = '{{ old('organization_id', $organizations->first()?->organization_id ?? '') }}'"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
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
                                class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-white/70"
                                readonly />
                            <input type="hidden" name="organization_id"
                                value="{{ $organizations->first()?->organization_id ?? '' }}" />
                        @endif
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
                    {{-- Nature of Project/Activity --}}
                    <div>
                        <label for="projectActivity"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Nature of Project / Activity <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="projectActivity" name="projectActivity"
                            value="{{ old('projectActivity') }}"
                            placeholder="e.g. General Assembly, Sports Fest, Community Outreach"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('projectActivity') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('projectActivity')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Purpose of Activity --}}
                    <div>
                        <label for="purposed" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Purpose of Activity <span class="text-error-500">*</span>
                        </label>
                        <textarea id="purposed" name="purposed" rows="3" placeholder="Describe the purpose of the activity"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border {{ $errors->has('purposed') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ old('purposed') }}</textarea>
                        @error('purposed')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Date / Day / Time / Venue --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label for="activityDate"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Date <span class="text-error-500">*</span>
                            </label>
                            <input type="date" id="activityDate" name="date" value="{{ old('date') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('date') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            @error('date')
                                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                            @else
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Day of week will be auto-computed</p>
                            @enderror
                        </div>

                        <div>
                            <label for="time" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Time <span class="text-error-500">*</span>
                            </label>
                            <input type="text" id="time" name="time" value="{{ old('time') }}"
                                placeholder="e.g. 8:00 AM – 5:00 PM"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('time') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            @error('time')
                                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="placeAndVenue"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Place / Venue <span class="text-error-500">*</span>
                            </label>
                            <input type="text" id="placeAndVenue" name="placeAndVenue"
                                value="{{ old('placeAndVenue') }}" placeholder="e.g. TAU Gymnasium"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('placeAndVenue') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            @error('placeAndVenue')
                                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Facilities / Equipment --}}
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            University Facilities / Equipment to be Used
                        </label>
                        <div class="space-y-2">
                            <template x-for="(item, index) in facilities" :key="index">
                                <div class="flex gap-2 items-center">
                                    <input type="text" :name="'facilitiesOrEquipmentToBeUsedRow[' + index + ']'"
                                        x-model="facilities[index]" placeholder="e.g. Projector, Sound System, Chairs"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    <button type="button" @click="removeFacility(index)" x-show="facilities.length > 1"
                                        class="flex-shrink-0 rounded-lg border border-error-200 p-2 text-error-500 transition hover:bg-error-50 dark:border-error-500/30 dark:hover:bg-error-500/10">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                        <button type="button" @click="addFacility()" x-show="facilities.length < 10"
                            class="mt-2 flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                            Add Facility / Equipment
                        </button>
                    </div>
                </div>
            </div>

            {{-- SECTION 3 · PRESIDENT --}}
            <div
                class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3
                    class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                    President Information</h3>
                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Activity report will be submitted on: <span
                        class="font-medium text-gray-700 dark:text-gray-300">End of the 1st Semester</span></p>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="presidentName"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            President Name <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="presidentName" name="presidentName"
                            x-model="presidentName" placeholder="Full name of the President"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('presidentName') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('presidentName')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="presidentContactNo"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Contact Number <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="presidentContactNo" name="presidentContactNo"
                            x-model="presidentContact" placeholder="e.g. 09XX-XXX-XXXX"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('presidentContactNo') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('presidentContactNo')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- SECTION 4 · ADVISERS --}}
            <div
                class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3
                    class="mb-1 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                    Faculty Adviser/s</h3>
                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400 pl-4">As the adviser/s of this student
                    organization, I/we promise to attend the proposed activity and will strictly observe the diligence of a
                    good father/mother over our students (loco parentis role) to ensure their safety.</p>

                <div class="space-y-2">
                    <template x-for="(adviser, index) in advisers" :key="index">
                        <div class="flex gap-2 items-center">
                            <input type="text" :name="'adviserRow[' + index + ']'" x-model="advisers[index]"
                                :placeholder="'Adviser ' + (index + 1) + ' full name'"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('adviserRow') || $errors->has('adviserRow.*') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            <button type="button" @click="removeAdviser(index)" x-show="advisers.length > 1"
                                class="flex-shrink-0 rounded-lg border border-error-200 p-2 text-error-500 transition hover:bg-error-50 dark:border-error-500/30 dark:hover:bg-error-500/10">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
                @error('adviserRow')
                    <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                @enderror
                <button type="button" @click="addAdviser()"
                    class="mt-2 flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Adviser
                </button>
            </div>

            {{-- SECTION 5 · COLLEGE DEAN (optional) --}}
            <div
                class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3
                    class="mb-1 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                    College Dean</h3>
                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400 pl-4">For College-based Student Organizations and
                    Student Councils only.</p>

                <div>
                    <label for="collegeDean" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        College Dean Name <span class="text-xs font-normal text-gray-400 ml-1">(optional)</span>
                    </label>
                    <input type="text" id="collegeDean" name="collegeDean" value="{{ old('collegeDean') }}"
                        placeholder="Full name of the College Dean"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3
                    class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                    Additional Request Details</h3>

                <div class="space-y-6">
                    <div>
                        <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-400">Activity Types</p>
                        <div
                            class="space-y-3 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/30">
                            @foreach (['Seminar', 'Clean Up Drive', 'Donation', 'Conference', 'Workshop'] as $activityType)
                                <label class="flex cursor-pointer items-center gap-3">
                                    <input type="checkbox" name="activityTypes[]" value="{{ $activityType }}"
                                        x-model="activityTypes" @checked(in_array($activityType, (array) old('activityTypes', []), true))
                                        class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $activityType }}</span>
                                </label>
                            @endforeach

                            <div class="space-y-2">
                                <label class="flex cursor-pointer items-center gap-3">
                                    <input type="checkbox" name="activityTypes[]" value="others" x-model="activityTypes"
                                        @checked(in_array('others', (array) old('activityTypes', []), true))
                                        class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Others</span>
                                </label>
                                <div x-show="activityTypes.includes('others')" x-transition class="pl-7">
                                    <input type="text" name="activityTypeOther"
                                        value="{{ old('activityTypeOther') }}" placeholder="Specify other activity type"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="areaScope"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Area
                                Scope</label>
                            <select id="areaScope" name="areaScope" x-model="areaScope"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                <option value="">Select area scope</option>
                                <option value="Local" @selected(old('areaScope') === 'Local')>Local</option>
                                <option value="Provincial" @selected(old('areaScope') === 'Provincial')>Provincial</option>
                                <option value="Regional" @selected(old('areaScope') === 'Regional')>Regional</option>
                                <option value="National" @selected(old('areaScope') === 'National')>National</option>
                                <option value="International" @selected(old('areaScope') === 'International')>International</option>
                                <option value="others" @selected(old('areaScope') === 'others')>Others</option>
                            </select>
                            <div x-show="areaScope === 'others'" x-transition class="mt-2">
                                <input type="text" name="areaScopeOther" value="{{ old('areaScopeOther') }}"
                                    placeholder="Specify area scope"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                        </div>

                        <div>
                            <label for="sponsor"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Sponsor</label>
                            <select id="sponsor" name="sponsor" x-model="sponsor"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                <option value="">Select sponsor</option>
                                <option value="N/A" @selected(old('sponsor') === 'N/A')>N/A</option>
                                <option value="SSC" @selected(old('sponsor') === 'SSC')>SSC</option>
                                <option value="Admin" @selected(old('sponsor') === 'Admin')>Admin</option>
                                <option value="others" @selected(old('sponsor') === 'others')>Others</option>
                            </select>
                            <div x-show="sponsor === 'others'" x-transition class="mt-2">
                                <input type="text" name="sponsorOther" value="{{ old('sponsorOther') }}"
                                    placeholder="Specify sponsor"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-400">Extension Services</p>
                        <div class="flex flex-wrap gap-4">
                            <label class="flex cursor-pointer items-center gap-2">
                                <input type="radio" name="extensionServices" value="yes"
                                    x-model="extensionServices" @checked(old('extensionServices') === 'yes')
                                    class="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Yes</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2">
                                <input type="radio" name="extensionServices" value="no"
                                    x-model="extensionServices" @checked(old('extensionServices') === 'no')
                                    class="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">No</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SECTION 6 · PARENT / GUARDIAN WAIVER --}}
            <div
                class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3
                    class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                    Parent / Guardian Waiver</h3>

                <div
                    class="mb-5 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                    A signed waiver is <span class="font-semibold">required</span> before this activity request can be
                    submitted. Upload the signed waiver below, or use the generator to create one, print it, have it
                    signed, then upload it.
                </div>

                {{-- Upload area --}}
                <input type="file" name="parentGuardianWaiver" x-ref="waiverInput"
                    accept="application/pdf,image/jpeg,image/png" class="hidden"
                    @change="pickWaiver($event.target.files)" />

                <div @click="$refs.waiverInput.click()"
                    @drop.prevent="isDraggingWaiver = false; pickWaiver($event.dataTransfer.files)"
                    @dragover.prevent="isDraggingWaiver = true" @dragleave.prevent="isDraggingWaiver = false"
                    :class="isDraggingWaiver ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/10' : 'border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/40'"
                    class="cursor-pointer rounded-xl border border-dashed p-6 text-center transition hover:border-brand-500 dark:hover:border-brand-500">
                    <template x-if="!waiverFileName">
                        <div class="flex flex-col items-center">
                            <div
                                class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-200 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 16a4 4 0 01-.88-7.9A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Click to upload or drag and
                                drop</p>
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Accepted: PDF, JPG, PNG — max 5 MB</p>
                        </div>
                    </template>
                    <template x-if="waiverFileName">
                        <div class="flex items-center justify-center gap-3">
                            <svg class="h-5 w-5 text-success-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300" x-text="waiverFileName"></span>
                            <button type="button" @click.stop="clearWaiver()"
                                class="rounded-lg border border-error-200 p-1.5 text-error-500 transition hover:bg-error-50 dark:border-error-500/30 dark:hover:bg-error-500/10">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
                <p x-show="waiverError" x-cloak class="mt-1.5 text-xs text-error-500" x-text="waiverError"></p>
                @error('parentGuardianWaiver')
                    <p class="mt-1.5 text-xs text-error-500">{{ $message }}</p>
                @enderror

                {{-- Divider --}}
                <div class="my-6 flex items-center gap-3">
                    <div class="h-px flex-1 bg-gray-200 dark:bg-gray-800"></div>
                    <span class="text-xs font-medium text-gray-400 dark:text-gray-500">or generate one below</span>
                    <div class="h-px flex-1 bg-gray-200 dark:bg-gray-800"></div>
                </div>

                {{-- Waiver generator --}}
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-gray-900/30">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90">Waiver document generator</h4>
                    <p class="mb-4 mt-1 text-xs text-gray-500 dark:text-gray-400">Fill in the details below to generate an
                        official TAU parent/guardian waiver. Print it, have it signed, then upload it above.</p>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Student full
                                name</label>
                            <input type="text" x-model="waiver.studentName" placeholder="e.g. Juan Dela Cruz"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Student ID
                                number</label>
                            <input type="text" x-model="waiver.studentId" placeholder="e.g. 2021-00001"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Parent /
                                guardian name</label>
                            <input type="text" x-model="waiver.parentName" placeholder="e.g. Maria Dela Cruz"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Relationship to
                                student</label>
                            <input type="text" x-model="waiver.relationship" placeholder="e.g. Mother"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Activity
                                name</label>
                            <input type="text" x-model="waiver.activityName" :placeholder="projectActivityValue() || 'e.g. Sports Fest 2025'"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Activity
                                date</label>
                            <input type="date" x-model="waiver.activityDate"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Venue</label>
                            <input type="text" x-model="waiver.venue" placeholder="e.g. TAU Gymnasium"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </div>
                    </div>

                    <button type="button" @click="openWaiverGenerator()"
                        class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-white dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 17v-2a4 4 0 014-4h2m-6 6h6m-9 4h12a2 2 0 002-2V7a2 2 0 00-2-2h-5l-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        Generate &amp; download waiver
                    </button>
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="flex flex-col items-end gap-2">
                    <div class="flex justify-end gap-3">
                        <button type="reset" @click="clearWaiver()"
                            class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                            Clear
                        </button>
                        <button type="submit" :disabled="!waiverFileName"
                            class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-brand-500">
                            Submit &amp; Generate PDF
                        </button>
                    </div>
                    <p x-show="!waiverFileName" class="text-xs text-gray-400 dark:text-gray-500">Attach the signed
                        parent/guardian waiver to enable submission.</p>
                </div>
            </div>

        </form>
    </div>
@endsection
