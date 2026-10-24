@php
    $cycle = $accreditationCycle ?? [];
    $organization = $cycle['organization'] ?? null;
    $deadline = $cycle['deadline'] ?? null;
    $standing = $cycle['standing'] ?? null;
    $isCompliant = (bool) ($standing['compliant'] ?? false);
    $organizationName = $organization?->detail()->first()?->name;
    $journey = [
        ['Monitor the announcement', 'Confirm the official OSSD period, deadline, forms, and updated requirements.'],
        ['Update organization records', 'Review officers, members, adviser, contact details, and election results.'],
        ['Complete requirements', 'Prepare the current GPOA, budget, governing documents, and required reports.'],
        ['Submit the application', 'Send the complete renewal application to OSSD before the deadline.'],
        ['OSSD evaluation', 'Respond to requested corrections or missing compliance documents.'],
        ['Renewed accreditation', 'Receive confirmation and comply with conditions for the new academic year.'],
    ];
@endphp

<section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="bg-gradient-to-br from-[#15382b] via-[#1d513d] to-[#35631f] px-5 py-6 text-white sm:px-7 sm:py-8">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-3xl">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-lime-200">Existing student organizations</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">Organization accreditation renewal</h1>
                <p class="mt-2 text-sm leading-6 text-emerald-50/85">
                    Renew your organization's recognition for the next academic year and keep its records, plans, and accountability documents current.
                </p>
            </div>
            <div class="shrink-0 rounded-xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-100">Current standing</p>
                <p class="mt-1 text-sm font-semibold">
                    {{ $isCompliant ? 'Accreditation requirements satisfied' : 'Renewal application required' }}
                </p>
                @if ($organizationName)
                    <p class="mt-1 text-xs text-emerald-100/80">{{ $organizationName }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="p-5 sm:p-7">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Accreditation journey</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Follow all six stages through formal renewal.</p>
            </div>
            <div class="rounded-xl border px-3 py-2 text-sm {{ $deadline ? 'border-warning-200 bg-warning-50 text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400' : 'border-gray-200 bg-gray-50 text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                @if ($deadline)
                    <span class="font-semibold">Deadline:</span> {{ $deadline->format('F j, Y') }}
                @else
                    Official deadline not yet scheduled
                @endif
            </div>
        </div>

        <ol class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($journey as $index => [$title, $description])
                <li class="flex gap-3 rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-700 dark:bg-white/[0.02]">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#e9f5cf] text-xs font-bold text-[#35631f] dark:bg-lime-400/15 dark:text-lime-300">
                        {{ $index + 1 }}
                    </span>
                    <span>
                        <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $title }}</span>
                        <span class="mt-1 block text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $description }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    </div>
</section>
