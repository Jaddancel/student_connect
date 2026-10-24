<aside class="lg:sticky lg:top-6">
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#2d6a4f] dark:text-emerald-400">Application journey</p>
        <ol class="mt-5 space-y-1" aria-label="New organization application steps">
            @foreach ([
                ['Organize the founders', 'Define your name, purpose, members, and officers.', 'applicant'],
                ['Prepare documents', 'Attach the complete OSSD document set.', 'applicant'],
                ['Submit to OSSD', 'Send the application and receive confirmation.', 'applicant'],
                ['Initial screening', 'OSSD checks completeness and policy alignment.', 'future'],
                ['Provisional requirements', 'Complete these only when OSSD requires them.', 'future'],
                ['Formal recognition', 'Receive the official approval and operating terms.', 'future'],
            ] as $index => [$title, $description, $state])
                <li class="relative flex gap-3 pb-4 last:pb-0">
                    @if ($index < 5)
                        <span class="absolute left-[15px] top-8 h-[calc(100%-1rem)] w-px {{ $state === 'future' ? 'bg-gray-200 dark:bg-gray-700' : 'bg-[#8fba43]' }}" aria-hidden="true"></span>
                    @endif
                    <span class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold
                        {{ $state === 'applicant' ? 'bg-[#1d513d] text-white ring-4 ring-emerald-100 dark:ring-emerald-500/10' : 'bg-gray-100 text-gray-400 dark:bg-gray-800' }}">
                        {{ $index + 1 }}
                    </span>
                    <div class="pt-0.5">
                        <p class="text-sm font-semibold {{ $state === 'future' ? 'text-gray-500 dark:text-gray-400' : 'text-gray-800 dark:text-white/90' }}">{{ $title }}</p>
                        <p class="mt-0.5 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $description }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>

    <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/20 dark:bg-amber-500/10">
        <p class="text-sm font-semibold text-amber-900 dark:text-amber-300">Before you submit</p>
        <p class="mt-1 text-xs leading-5 text-amber-800/80 dark:text-amber-200/70">Check the proposed name and purpose with OSSD. Recognition rules may vary; do not assume a probationary period unless OSSD confirms it.</p>
    </div>
</aside>
