@extends ('layouts.app')

@section('content')
    <div class="grid grid-cols-12 p-5">
        <div class="col-span-12 xl:col-span-7">
            <div class="mb-5 sm:mb-8">
                <h1 class="text-title-sm sm:text-title-md mb-2 font-semibold text-gray-800 dark:text-white/90">
                    Setup Profile
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Only your name is required. Your profile will be reviewed and matched by a superadmin.
                </p>
            </div>

            @if (session('status'))
                <div
                    class="mb-4 rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/40 dark:bg-warning-500/10 dark:text-warning-400">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div
                    class="mb-4 rounded-lg border border-error-300 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/40 dark:bg-error-500/10 dark:text-error-400">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="post" action="{{ route('profile.store') }}"
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                @csrf

                <div class="space-y-5">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                First Name<span class="text-error-500">*</span>
                            </label>
                            <input type="text" id="fname" name="fname" value="{{ old('fname') }}"
                                placeholder="Enter your first name"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Last Name<span class="text-error-500">*</span>
                            </label>
                            <input type="text" id="lname" name="lname" value="{{ old('lname') }}"
                                placeholder="Enter your last name"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                        </div>
                    </div>

                    <div>
                        <label for="mname" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Middle Name
                        </label>
                        <input type="text" id="mname" name="mname" value="{{ old('mname') }}" placeholder="Optional"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                    </div>

                    <button type="submit"
                        class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 flex w-full items-center justify-center rounded-lg px-4 py-3 text-sm font-medium text-white transition">
                        Submit Profile Request
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection