{{--
    Pre-deadline accreditation danger card. Shows for members (user_type 3) whose
    org is inside the warning window and not yet accredited. Read-only nudge —
    the actual suspension happens after the deadline via accreditation:enforce.
--}}
@php
    $accreditationDaysLeft = auth()->check() && (int) auth()->user()->user_type === 3
        ? app(\App\Services\AccreditationService::class)->warningDaysLeftForUser((int) auth()->id())
        : null;
@endphp

@if ($accreditationDaysLeft !== null)
    <div class="mb-5 flex items-start gap-3 rounded-xl border border-warning-300 bg-warning-50 px-4 py-3 dark:border-warning-500/40 dark:bg-warning-500/10">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-warning-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>
        <div class="text-sm text-warning-700 dark:text-warning-400">
            <p class="font-semibold">Accreditation due in {{ $accreditationDaysLeft }} day{{ $accreditationDaysLeft === 1 ? '' : 's' }}</p>
            <p class="mt-0.5">
                Your organization is not yet accredited for this cycle. Submit the accreditation
                form and get it approved before the deadline to avoid suspension.
            </p>
        </div>
    </div>
@endif
