@extends ('layouts.app')

@section('content')
    <div class="grid grid-cols-12 gap-6 p-5"
        x-data="{
            sex: '{{ old('sex', '') }}',
            birthday: '{{ old('birthday', '') }}',
            age: '{{ old('age', '') }}',
            computeAge() {
                if (!this.birthday) return;
                const today = new Date();
                const dob = new Date(this.birthday);
                let years = today.getFullYear() - dob.getFullYear();
                const m = today.getMonth() - dob.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) years--;
                this.age = years > 0 ? years : '';
            }
        }">

        {{-- Left column: heading + form --}}
        <div class="col-span-12 xl:col-span-7">

            {{-- Page heading --}}
            <div class="mb-6">
                <h1 class="text-title-sm sm:text-title-md mb-1.5 font-semibold text-gray-800 dark:text-white/90">
                    Setup Your Profile
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Your name is required. All other fields are optional and help us keep your record accurate.
                </p>
            </div>

            {{-- Alerts --}}
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/40 dark:bg-warning-500/10 dark:text-warning-400">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-error-300 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/40 dark:bg-error-500/10 dark:text-error-400">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Form card --}}
            <form method="post" action="{{ route('profile.store') }}"
                class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                @csrf

                {{-- ── Section 1: Name ── --}}
                <div class="p-5 lg:p-6">
                    <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                        Name
                    </p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="fname" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                First Name <span class="text-error-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="fname"
                                name="fname"
                                value="{{ old('fname') }}"
                                placeholder="e.g. Maria"
                                required
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            />
                        </div>

                        <div>
                            <label for="lname" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Last Name <span class="text-error-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="lname"
                                name="lname"
                                value="{{ old('lname') }}"
                                placeholder="e.g. Santos"
                                required
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <label for="mname" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Middle Name
                                <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">(optional)</span>
                            </label>
                            <input
                                type="text"
                                id="mname"
                                name="mname"
                                value="{{ old('mname') }}"
                                placeholder="e.g. dela Cruz"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            />
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-100 dark:border-gray-800"></div>

                {{-- ── Section 2: Personal Details ── --}}
                <div class="p-5 lg:p-6">
                    <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                        Personal Details
                    </p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                        {{-- Sex pill toggle --}}
                        <div class="sm:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400">Sex</label>
                            <div class="flex gap-2">
                                @foreach (['Male', 'Female'] as $option)
                                    <label
                                        class="relative cursor-pointer"
                                        :class="sex === '{{ $option }}'
                                            ? 'text-brand-600 dark:text-brand-400'
                                            : 'text-gray-600 dark:text-gray-400'">
                                        <input
                                            type="radio"
                                            name="sex"
                                            value="{{ $option }}"
                                            class="sr-only"
                                            x-model="sex"
                                        />
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-medium transition-all duration-150"
                                            :class="sex === '{{ $option }}'
                                                ? 'border-brand-300 bg-brand-50 text-brand-700 dark:border-brand-700 dark:bg-brand-500/10 dark:text-brand-400'
                                                : 'border-gray-300 bg-white text-gray-600 hover:border-gray-400 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:bg-white/[0.05]'">
                                            <svg x-show="sex === '{{ $option }}'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                            {{ $option }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Birthday --}}
                        <div>
                            <label for="birthday" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Birthday
                            </label>
                            <input
                                type="date"
                                id="birthday"
                                name="birthday"
                                x-model="birthday"
                                @change="computeAge()"
                                max="{{ date('Y-m-d', strtotime('-1 day')) }}"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            />
                        </div>

                        {{-- Age --}}
                        <div>
                            <label for="age" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Age
                                <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">(auto-filled from birthday)</span>
                            </label>
                            <input
                                type="number"
                                id="age"
                                name="age"
                                x-model="age"
                                min="1"
                                max="120"
                                placeholder="—"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            />
                        </div>

                        {{-- Religion --}}
                        <div>
                            <label for="religion" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Religion
                            </label>
                            <input
                                type="text"
                                id="religion"
                                name="religion"
                                value="{{ old('religion') }}"
                                placeholder="e.g. Roman Catholic"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            />
                        </div>

                        {{-- Nationality --}}
                        <div>
                            <label for="nationality" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Nationality
                            </label>
                            <input
                                type="text"
                                id="nationality"
                                name="nationality"
                                value="{{ old('nationality') }}"
                                placeholder="e.g. Filipino"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            />
                        </div>

                    </div>
                </div>

                <div class="border-t border-gray-100 dark:border-gray-800"></div>

                {{-- ── Section 3: Academic ── --}}
                <div class="p-5 lg:p-6">
                    <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                        Academic
                    </p>
                    <div>
                        <label for="course_year" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Course &amp; Year
                        </label>
                        <input
                            type="text"
                            id="course_year"
                            name="course_year"
                            value="{{ old('course_year') }}"
                            placeholder="e.g. BSCS - 3rd Year"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                        />
                    </div>
                </div>

                <div class="border-t border-gray-100 dark:border-gray-800"></div>

                {{-- ── Section 4: Contact ── --}}
                <div class="p-5 lg:p-6">
                    <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                        Contact
                    </p>
                    <div>
                        <label for="contact_number" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Contact Number
                        </label>
                        <div class="flex h-11 overflow-hidden rounded-lg border border-gray-300 shadow-theme-xs transition-colors focus-within:border-brand-300 focus-within:ring-3 focus-within:ring-brand-500/10 dark:border-gray-700 dark:focus-within:border-brand-800">
                            <span class="flex items-center border-r border-gray-300 bg-gray-50 px-3 text-sm font-medium text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                0
                            </span>
                            <input
                                type="text"
                                id="contact_number"
                                name="contact_number"
                                value="{{ old('contact_number') }}"
                                placeholder="9XXXXXXXXX"
                                maxlength="10"
                                inputmode="numeric"
                                class="h-full w-full bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:outline-none dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            />
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-100 dark:border-gray-800"></div>

                {{-- ── Submit ── --}}
                <div class="p-5 lg:p-6">
                    <button
                        type="submit"
                        class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 flex w-full items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        Submit Profile Request
                    </button>
                    <p class="mt-3 text-center text-xs text-gray-400 dark:text-gray-500">
                        Your request will be reviewed by a superadmin before your profile is activated.
                    </p>
                </div>

            </form>
        </div>

        {{-- Right column: hint card (visible xl+) --}}
        <div class="col-span-12 hidden xl:col-span-5 xl:flex xl:flex-col xl:gap-4 xl:pt-16">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="mb-3 flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 dark:bg-brand-500/10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-brand-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h3 class="mb-1.5 text-sm font-semibold text-gray-800 dark:text-white/90">How this works</h3>
                <p class="text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                    Submit your profile request and a superadmin will review and match it to your record. You'll be notified once it's approved.
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="mb-3 flex h-9 w-9 items-center justify-center rounded-lg bg-success-50 dark:bg-success-500/10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-success-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h3 class="mb-1.5 text-sm font-semibold text-gray-800 dark:text-white/90">Your data is safe</h3>
                <p class="text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                    Only authorized staff can view your personal information. It is used solely to verify your student records.
                </p>
            </div>
        </div>

    </div>
@endsection
