@props(['approval'])

@php
    $isSystem = $approval->admin !== null && (int) $approval->admin === 0;
    if (!$isSystem) {
        $approval->loadMissing('decidingUser.profile');
    }
    $user = $isSystem ? null : $approval->decidingUser;
    $profile = $user?->getRelation('profile');
    $name = trim(implode(' ', array_filter([
        $profile?->first_name,
        $profile?->middle_name,
        $profile?->last_name,
    ], fn ($part) => $part !== null && trim($part) !== '')));
    $decisionMaker = $isSystem ? 'System' : ($name !== '' ? $name : ($user?->user_email ?? 'Unavailable'));
@endphp

<dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
    <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Decided By</dt>
        <dd class="mt-1 text-gray-800 dark:text-white/90">{{ $decisionMaker }}</dd>
    </div>
    <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Decision Date</dt>
        <dd class="mt-1 text-gray-800 dark:text-white/90">{{ $approval->approved_at?->timezone(config('app.display_timezone'))->format('M d, Y h:i A') ?? 'Unavailable' }}</dd>
    </div>
</dl>
