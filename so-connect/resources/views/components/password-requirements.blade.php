{{--
    Live password-requirements checklist. Shared by the first-login wizard
    (pages/auth/change-password) and the Settings password form so the visible
    policy never drifts from App\Rules\StrongPassword.

    Expects the surrounding Alpine scope (see partials/password-policy-script)
    to expose: password, minLength, hasUpper, hasLower, hasNumber, hasSpecial.
--}}
<div x-show="password.length > 0"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 -translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Requirements</p>
    <ul class="space-y-1.5">
        @foreach ([
            'minLength' => 'At least 8 characters',
            'hasUpper' => 'One uppercase letter (A–Z)',
            'hasLower' => 'One lowercase letter (a–z)',
            'hasNumber' => 'One number (0–9)',
            'hasSpecial' => 'One special character (!@#$…)',
        ] as $flag => $label)
            <li class="flex items-center gap-2 text-sm transition-colors duration-150"
                :class="{{ $flag }} ? 'text-success-600 dark:text-success-400' : 'text-gray-400 dark:text-gray-500'">
                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path x-show="{{ $flag }}" stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    <path x-show="!{{ $flag }}" stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                {{ $label }}
            </li>
        @endforeach
    </ul>
</div>
