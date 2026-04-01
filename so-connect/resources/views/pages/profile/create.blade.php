@extends ('layouts.app')

@section('content')
    <div class="grid grid-cols-12 p-5">
        <div class="col-span-12 xl:col-span-7">
            <div class="mb-5 sm:mb-8">
                <h1 class="text-title-sm sm:text-title-md mb-2 font-semibold text-gray-800 dark:text-white/90">
                    Setup Profile
                </h1>
            </div>
            <div>
                <form>
                    @csrf
                    <div class="space-y-5">
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <!-- First Name -->
                            <div class="sm:col-span-1">
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    First Name<span class="text-error-500">*</span>
                                </label>
                                <input type="text" id="fname" name="fname" placeholder="Enter your first name"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                            </div>
                            <!-- Last Name -->
                            <div class="sm:col-span-1">
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Last Name<span class="text-error-500">*</span>
                                </label>
                                <input type="text" id="lname" name="lname" placeholder="Enter your last name"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                            </div>
                        </div>
                        <!-- Email -->
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-1">
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Middle Name
                                </label>
                                <input type="email" id="email" name="email" placeholder="Enter your email"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                            </div>
                        </div>
                        <!-- Phone Number / Occupation -->
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-1">
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Contact Number<span class="text-error-500">*</span>
                                </label>
                                <input type="tel" id="phone_number" name="phone_number"
                                    placeholder="Enter your contact number"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                            </div>
                            <div class="sm:col-span-1">
                                <label for="occupation"
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Occupation<span class="text-error-500">*</span>
                                </label>
                                <x-form.profile.occupation-select />
                            </div>
                        </div>
                        {{-- Gender --}}
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-1">
                                <label for="occupation"
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Gender<span class="text-error-500">*</span>
                                </label>
                                <x-form.profile.gender-select />
                            </div>
                        </div>
                        {{-- Address --}}
                        <div class="relative py-3 sm:py-5">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-200 dark:border-gray-800"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="bg-white p-2 text-gray-400 sm:px-5 sm:py-2 dark:bg-gray-900">Address<span
                                        class="text-error-500">*</span></span>
                            </div>
                        </div>
                        <x-form.profile.address-field />
                        <!-- Button -->
                        <div>
                            <button
                                class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 flex w-full items-center justify-center rounded-lg px-4 py-3 text-sm font-medium text-white transition">
                                Save Profile
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-span-12 xl:col:span-5">
            {{-- Profile picture functionality goes here, God forbid. --}}
        </div>
    </div>
@endsection
