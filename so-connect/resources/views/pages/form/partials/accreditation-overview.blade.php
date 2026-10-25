@php
    $cycle = $accreditationCycle ?? [];
    $organization = $cycle['organization'] ?? null;
    $deadline = $cycle['deadline'] ?? null;
    $standing = $cycle['standing'] ?? null;
    $isCompliant = (bool) ($standing['compliant'] ?? false);
    $organizationName = $organization?->detail()->first()?->name;
    $journey = [
        'Monitor the announcement',
        'Update organization records',
        'Complete requirements',
        'Submit the application',
        'OSSD evaluation',
        'Renewed accreditation',
    ];
@endphp

<section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-700 dark:bg-gray-900">
    <div class="bg-gradient-to-br from-[#15382b] via-[#1d513d] to-[#35631f] px-5 py-6 text-white dark:from-gray-950 dark:via-emerald-950 dark:to-gray-900 sm:px-7 sm:py-8">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-3xl">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-lime-200">Existing student organizations</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">Organization accreditation renewal</h1>
                <p class="mt-2 text-sm leading-6 text-emerald-50/85">
                    Renew your organization's recognition for the next academic year and keep its records, plans, and accountability documents current.
                </p>
            </div>
            <div class="shrink-0 rounded-xl border border-white/20 bg-white/10 px-4 py-3 backdrop-blur-sm dark:border-white/10 dark:bg-black/20">
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

        <div class="-mx-5 mt-6 overflow-x-auto px-5 pb-2 sm:-mx-7 sm:px-7">
            <ol class="relative grid min-w-[54rem] grid-cols-6 before:absolute before:left-[8.333%] before:right-[8.333%] before:top-4 before:h-px before:bg-gray-200 dark:before:bg-gray-700"
                aria-label="Accreditation renewal journey" data-accreditation-journey="linear">
                @foreach ($journey as $index => $title)
                    <li class="relative px-2 text-center">
                        <span class="relative z-10 mx-auto flex h-8 w-8 items-center justify-center rounded-full bg-[#dff0b8] text-xs font-bold text-[#315d24] ring-4 ring-white dark:bg-emerald-500/20 dark:text-emerald-300 dark:ring-gray-900">
                            {{ $index + 1 }}
                        </span>
                        <span class="mx-auto mt-3 block max-w-32 text-xs font-semibold leading-4 text-gray-700 dark:text-gray-200">{{ $title }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</section>
