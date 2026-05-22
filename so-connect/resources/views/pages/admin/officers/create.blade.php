@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Create Officer Account" />

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

        <form action="{{ route('admin.officers.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            {{-- Account & Organization --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Account &amp; Organization</h3>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="organization_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Organization <span class="text-error-500">*</span>
                        </label>
                        <select id="organization_id" name="organization_id"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                            <option value="">Select organization</option>
                            @foreach ($organizations as $org)
                                <option value="{{ $org->organization_id }}"
                                    {{ old('organization_id') == $org->organization_id ? 'selected' : '' }}>
                                    {{ $org->organization_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Login Email <span class="text-error-500">*</span>
                        </label>
                        <input type="email" id="email" name="email" placeholder="Officer's login email"
                            value="{{ old('email') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                </div>
            </div>

            {{-- Personal Information --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Personal Information</h3>

                <div class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label for="first_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">First Name <span class="text-error-500">*</span></label>
                            <input type="text" id="first_name" name="first_name" placeholder="First name"
                                value="{{ old('first_name') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                        <div>
                            <label for="middle_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" placeholder="Middle name (optional)"
                                value="{{ old('middle_name') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                        <div>
                            <label for="last_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Last Name <span class="text-error-500">*</span></label>
                            <input type="text" id="last_name" name="last_name" placeholder="Last name"
                                value="{{ old('last_name') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="position" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Position <span class="text-error-500">*</span></label>
                            <input type="text" id="position" name="position" placeholder="e.g. Secretary"
                                value="{{ old('position') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                        <div>
                            <label for="contact_number" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Contact Number <span class="text-error-500">*</span></label>
                            <input type="tel" id="contact_number" name="contact_number" placeholder="e.g. 09XX-XXX-XXXX"
                                value="{{ old('contact_number') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label for="age" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Age <span class="text-error-500">*</span></label>
                            <input type="number" id="age" name="age" min="1" max="99" placeholder="Age"
                                value="{{ old('age') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                        <div>
                            <label for="sex" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Sex <span class="text-error-500">*</span></label>
                            <select id="sex" name="sex"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                <option value="">Select</option>
                                <option value="Male" {{ old('sex') === 'Male' ? 'selected' : '' }}>Male</option>
                                <option value="Female" {{ old('sex') === 'Female' ? 'selected' : '' }}>Female</option>
                            </select>
                        </div>
                        <div>
                            <label for="birthday" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Birthday <span class="text-error-500">*</span></label>
                            <input type="date" id="birthday" name="birthday"
                                value="{{ old('birthday') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="religious_affiliation" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Religious Affiliation</label>
                            <input type="text" id="religious_affiliation" name="religious_affiliation" placeholder="e.g. Roman Catholic"
                                value="{{ old('religious_affiliation') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                        <div>
                            <label for="nationality" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nationality <span class="text-error-500">*</span></label>
                            <input type="text" id="nationality" name="nationality" placeholder="e.g. Filipino"
                                value="{{ old('nationality') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                    </div>

                    <div>
                        <label for="birthplace" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Birthplace</label>
                        <input type="text" id="birthplace" name="birthplace" placeholder="City / Province"
                            value="{{ old('birthplace') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="course" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Course <span class="text-error-500">*</span></label>
                            <input type="text" id="course" name="course" placeholder="e.g. BS Computer Science"
                                value="{{ old('course') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                        <div>
                            <label for="year_level" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Year Level <span class="text-error-500">*</span></label>
                            <input type="text" id="year_level" name="year_level" placeholder="e.g. 3rd Year"
                                value="{{ old('year_level') }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                    </div>

                    {{-- Photo --}}
                    <div x-data="{ preview: null }">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Photo <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">(JPG/PNG, optional)</span>
                        </label>
                        <label for="photo"
                            class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-5 transition hover:border-brand-400 hover:bg-brand-50 dark:border-gray-700 dark:bg-gray-900/30 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                            <template x-if="preview">
                                <img :src="preview" class="mb-2 h-24 w-20 object-cover rounded-lg" alt="Photo preview" />
                            </template>
                            <template x-if="!preview">
                                <svg class="mb-2 h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 16v-8m-4 4h8M20.25 6.375c0 .621-.504 1.125-1.125 1.125H4.875A1.125 1.125 0 013.75 6.375V5.625A1.125 1.125 0 014.875 4.5h14.25A1.125 1.125 0 0120.25 5.625v.75zM4.5 7.5h15V18a1.5 1.5 0 01-1.5 1.5h-12A1.5 1.5 0 014.5 18V7.5z" />
                                </svg>
                            </template>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400" x-text="preview ? 'Change photo' : 'Click to upload photo'"></span>
                            <span class="mt-1 text-xs text-gray-400 dark:text-gray-500">JPG, PNG — max 2 MB</span>
                            <input id="photo" name="photo" type="file" accept="image/jpeg,image/png" class="hidden"
                                @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                        </label>
                    </div>
                </div>
            </div>

            {{-- Address --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Address</h3>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="country" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Country <span class="text-error-500">*</span></label>
                        <input type="text" id="country" name="country" placeholder="e.g. Philippines"
                            value="{{ old('country') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div>
                        <label for="province" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Province <span class="text-error-500">*</span></label>
                        <input type="text" id="province" name="province" placeholder="Province"
                            value="{{ old('province') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div>
                        <label for="town" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Town / City <span class="text-error-500">*</span></label>
                        <input type="text" id="town" name="town" placeholder="Town or City"
                            value="{{ old('town') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div>
                        <label for="barangay" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Barangay <span class="text-error-500">*</span></label>
                        <input type="text" id="barangay" name="barangay" placeholder="Barangay"
                            value="{{ old('barangay') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ url()->previous() }}"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                    Cancel
                </a>
                <button type="submit"
                    class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                    Create Officer Account
                </button>
            </div>
        </form>
    </div>
@endsection
