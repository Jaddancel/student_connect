{{--
    Account-section password change (Settings). Posts to settings.password, which
    verifies the current password, enforces App\Rules\StrongPassword and rejects
    reusing the current password. Client hints come from the shared
    passwordPolicyTools() factory + <x-password-requirements /> so they stay in
    lockstep with the first-login wizard.
--}}
@include('partials.password-policy-script')

<form method="POST" action="{{ route('settings.password') }}" class="space-y-5"
    x-data="passwordPolicyTools({ currentPassword: '', showCurrent: false })">
    @csrf

    <div>
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Password</p>
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Choose a strong password you don't use anywhere else.</p>
    </div>

    {{-- Current password --}}
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Current password <span class="text-error-500">*</span>
        </label>
        <div class="relative">
            <input
                :type="showCurrent ? 'text' : 'password'"
                name="current_password"
                x-model="currentPassword"
                autocomplete="current-password"
                placeholder="Enter your current password"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border bg-transparent py-2.5 pr-11 pl-4 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 {{ $errors->has('current_password') ? 'border-error-400 dark:border-error-500' : 'border-gray-300 dark:border-gray-700' }}"
            />
            <span @click="showCurrent = !showCurrent"
                class="absolute top-1/2 right-4 z-30 -translate-y-1/2 cursor-pointer text-xs font-medium text-gray-500 dark:text-gray-400"
                x-text="showCurrent ? 'Hide' : 'Show'"></span>
        </div>
        @error('current_password')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    {{-- New password --}}
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            New password <span class="text-error-500">*</span>
        </label>
        <div class="relative">
            <input
                :type="showPassword ? 'text' : 'password'"
                name="password"
                x-model="password"
                autocomplete="new-password"
                placeholder="Create a strong password"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border bg-transparent py-2.5 pr-11 pl-4 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 {{ $errors->has('password') ? 'border-error-400 dark:border-error-500' : 'border-gray-300 dark:border-gray-700' }}"
            />
            <span @click="showPassword = !showPassword"
                class="absolute top-1/2 right-4 z-30 -translate-y-1/2 cursor-pointer text-xs font-medium text-gray-500 dark:text-gray-400"
                x-text="showPassword ? 'Hide' : 'Show'"></span>
        </div>
        @error('password')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    {{-- Requirements checklist (shared) --}}
    <x-password-requirements />

    {{-- Confirm new password --}}
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Confirm new password <span class="text-error-500">*</span>
        </label>
        <div class="relative">
            <input
                :type="showConfirm ? 'text' : 'password'"
                name="password_confirmation"
                x-model="confirmPassword"
                autocomplete="new-password"
                placeholder="Repeat your new password"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border bg-transparent py-2.5 pr-11 pl-4 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                :class="confirmPassword.length > 0 ? (passwordsMatch ? 'border-success-400 dark:border-success-500' : 'border-error-400 dark:border-error-500') : 'border-gray-300 dark:border-gray-700'"
            />
            <span @click="showConfirm = !showConfirm"
                class="absolute top-1/2 right-4 z-30 -translate-y-1/2 cursor-pointer text-xs font-medium text-gray-500 dark:text-gray-400"
                x-text="showConfirm ? 'Hide' : 'Show'"></span>
        </div>
        <p x-show="confirmPassword.length > 0 && !passwordsMatch" x-transition class="mt-1 text-xs text-error-500">
            Passwords do not match.
        </p>
        <p x-show="confirmPassword.length > 0 && passwordsMatch" x-transition class="mt-1 text-xs text-success-600 dark:text-success-400">
            Passwords match.
        </p>
    </div>

    <button
        type="submit"
        :disabled="!allMet || !passwordsMatch || currentPassword.length === 0"
        :class="(allMet && passwordsMatch && currentPassword.length > 0)
            ? 'bg-brand-500 hover:bg-brand-600 cursor-pointer'
            : 'cursor-not-allowed bg-gray-200 text-gray-400 dark:bg-gray-700 dark:text-gray-500'"
        class="rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition-colors">
        Update password
    </button>
</form>
