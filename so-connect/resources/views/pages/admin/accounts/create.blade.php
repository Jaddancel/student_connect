@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Create Admin Account" />

    <div class="space-y-6">

        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('superadmin.accounts.store') }}" method="POST" enctype="multipart/form-data"
            class="space-y-6"
            x-data="{ advisers: {{ Js::from(old('faculty_advisers', [''])) }}, addAdviser() { this.advisers.push(''); }, removeAdviser(i) { if (this.advisers.length > 1) this.advisers.splice(i, 1); } }">
            @csrf

            {{-- PART 1: Personal Information --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                    Personal Information
                </h3>

                <div class="space-y-4">
                    {{-- Name --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label for="first_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                First Name <span class="text-error-500">*</span>
                            </label>
                            <input type="text" id="first_name" name="first_name" placeholder="First name"
                                value="{{ old('first_name') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('first_name') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                        <div>
                            <label for="middle_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" placeholder="Middle name (optional)"
                                value="{{ old('middle_name') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                        <div>
                            <label for="last_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Last Name <span class="text-error-500">*</span>
                            </label>
                            <input type="text" id="last_name" name="last_name" placeholder="Last name"
                                value="{{ old('last_name') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('last_name') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                    </div>

                    {{-- Contact & Age & Sex --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label for="contact_number" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Contact Number <span class="text-error-500">*</span>
                            </label>
                            <input type="text" id="contact_number" name="contact_number" placeholder="09XXXXXXXXX"
                                value="{{ old('contact_number') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('contact_number') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                        <div>
                            <label for="age" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Age</label>
                            <input type="number" id="age" name="age" min="1" max="99"
                                value="{{ old('age') }}" placeholder="Age"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                        <div>
                            <label for="sex" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Sex</label>
                            <select id="sex" name="sex"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                <option value="">Select</option>
                                <option value="Male" @selected(old('sex') === 'Male')>Male</option>
                                <option value="Female" @selected(old('sex') === 'Female')>Female</option>
                            </select>
                        </div>
                    </div>

                    {{-- Nationality, Religion, Birthplace, Birthday --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="nationality" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nationality</label>
                            <input type="text" id="nationality" name="nationality" placeholder="e.g. Filipino"
                                value="{{ old('nationality') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                        <div>
                            <label for="religious_affiliation" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Religious Affiliation</label>
                            <input type="text" id="religious_affiliation" name="religious_affiliation" placeholder="Optional"
                                value="{{ old('religious_affiliation') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                        <div>
                            <label for="birthplace" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Birthplace</label>
                            <input type="text" id="birthplace" name="birthplace" placeholder="City/Municipality"
                                value="{{ old('birthplace') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                        <div>
                            <label for="birthday" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Birthday</label>
                            <input type="date" id="birthday" name="birthday" value="{{ old('birthday') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                    </div>

                    {{-- Course & Year --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="course" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Course</label>
                            <input type="text" id="course" name="course" placeholder="e.g. BS Agriculture"
                                value="{{ old('course') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                        <div>
                            <label for="year_level" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Year Level</label>
                            <input type="text" id="year_level" name="year_level" placeholder="e.g. 3rd Year"
                                value="{{ old('year_level') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                    </div>

                    {{-- Parents/Guardian --}}
                    <div>
                        <label for="parents_guardian" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Parents / Guardian</label>
                        <input type="text" id="parents_guardian" name="parents_guardian" placeholder="Full name (optional)"
                            value="{{ old('parents_guardian') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    {{-- Addresses --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="present_address" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Present Address</label>
                            <textarea id="present_address" name="present_address" rows="2" placeholder="Current address"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ old('present_address') }}</textarea>
                        </div>
                        <div>
                            <label for="home_address" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Home Address</label>
                            <textarea id="home_address" name="home_address" rows="2" placeholder="Permanent/home address"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ old('home_address') }}</textarea>
                        </div>
                    </div>

                    {{-- Faculty Advisers --}}
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Faculty Advisers</label>
                        <div class="space-y-2">
                            <template x-for="(adviser, index) in advisers" :key="index">
                                <div class="flex items-center gap-2">
                                    <input type="text" :name="'faculty_advisers[' + index + ']'" x-model="advisers[index]"
                                        :placeholder="'Adviser ' + (index + 1) + ' full name'"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    <button type="button" @click="removeAdviser(index)" x-show="advisers.length > 1"
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
                    </div>

                    {{-- Photo --}}
                    <div x-data="{ preview: null }">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Profile Photo <span class="ml-1 text-xs font-normal text-gray-400">(JPG/PNG, max 2MB — optional)</span>
                        </label>
                        <label for="photo"
                            class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed {{ $errors->has('photo') ? 'border-error-500 bg-error-50' : 'border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/30' }} px-4 py-5 transition hover:border-brand-400 hover:bg-brand-50 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                            <template x-if="preview">
                                <img :src="preview" class="mb-2 h-20 w-20 rounded-full object-cover" alt="Photo preview" />
                            </template>
                            <template x-if="!preview">
                                <svg class="mb-2 h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                            </template>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400"
                                x-text="preview ? 'Change photo' : 'Click to upload profile photo'"></span>
                            <input id="photo" name="photo" type="file" accept="image/jpeg,image/png" class="hidden"
                                @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                        </label>
                        @error('photo')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Signature --}}
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Signature <span class="ml-1 text-xs font-normal text-gray-400">(draw or upload photo — optional)</span>
                        </label>
                        <div x-data="signatureField()" class="space-y-2">
                            <canvas x-ref="canvas" width="500" height="160"
                                class="w-full rounded-lg border border-gray-300 bg-white touch-none dark:border-gray-700"></canvas>
                            <input type="hidden" name="signature" x-ref="input" />
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" @click="clear()"
                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                                    Clear signature
                                </button>
                                <label class="cursor-pointer rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                                    Upload photo instead
                                    <input type="file" name="signature_file" accept="image/jpeg,image/jpg,image/png,image/heic" class="hidden"
                                        @change="onUpload($event)" />
                                </label>
                            </div>
                            <p x-cloak x-show="extractError" x-text="extractError"
                                class="rounded-lg bg-error-50 px-3 py-2 text-xs text-error-600 dark:bg-error-500/15 dark:text-error-500"></p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                Draw signature above or upload a photo of it on plain paper.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- PART 2: Account Credentials --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">
                    Account Credentials
                </h3>

                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Email Address <span class="text-error-500">*</span>
                        </label>
                        <input type="email" id="email" name="email" placeholder="admin@example.com"
                            value="{{ old('email') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('email') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('email')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                            <svg class="h-3.5 w-3.5 shrink-0 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                            </svg>
                            An invitation email will be sent here. The new admin will set their own password upon first login.
                        </p>
                    </div>

                    <x-google-link-field />
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex justify-end gap-3">
                <button type="reset"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                    Clear
                </button>
                <button type="submit"
                    class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                    Create Admin Account
                </button>
            </div>
        </form>
    </div>
@endsection
