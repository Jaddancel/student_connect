<section class="overflow-hidden rounded-3xl border border-emerald-200 bg-white shadow-sm dark:border-emerald-500/20 dark:bg-gray-900" role="status">
    <div class="bg-emerald-50 px-6 py-7 dark:bg-emerald-500/10 sm:px-8">
        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-[#1d513d] text-white shadow-sm">
            <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0l-3.5-3.5a1 1 0 1 1 1.4-1.4l2.8 2.79 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
        </span>
        <p class="mt-5 text-xs font-bold uppercase tracking-[0.16em] text-[#2d6a4f] dark:text-emerald-400">Step 3 complete</p>
        <h2 class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">Application received</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600 dark:text-gray-300">Your new organization application was sent to OSSD for initial screening. Keep your submitted documents available in case a correction or additional requirement is requested.</p>
    </div>
    <div class="grid gap-4 px-6 py-6 sm:grid-cols-3 sm:px-8">
        @foreach ([
            ['4', 'Initial screening', 'OSSD checks the documents, objectives, membership, adviser, and policy alignment.'],
            ['5', 'Requirements, if any', 'If OSSD assigns orientation, observation, or provisional items, complete only those confirmed requirements.'],
            ['6', 'Formal recognition', 'Wait for the official recognition document before operating as an approved organization.'],
        ] as [$number, $title, $description])
            <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $number }}</span>
                <h3 class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $description }}</p>
            </div>
        @endforeach
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 dark:border-gray-800 sm:px-8">
        <p class="text-xs text-gray-500 dark:text-gray-400">OSSD will provide the official decision and any next instructions.</p>
        <a href="{{ route('home') }}" class="rounded-xl bg-[#1d513d] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#15382b]">Return to home</a>
    </div>
</section>
