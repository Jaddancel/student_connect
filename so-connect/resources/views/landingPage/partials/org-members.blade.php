{{-- Members tab: officer cards on top, then the regular member list. --}}
<section class="mt-8" aria-labelledby="officers-heading">
    <h2 id="officers-heading" class="brand-font text-xl text-slate-800 dark:text-slate-100">Officers</h2>

    @if ($officers->isEmpty())
        <p class="mt-4 rounded-2xl border border-dashed border-emerald-200 bg-white p-8 text-center text-sm font-semibold text-slate-400 dark:border-emerald-900/50 dark:bg-[#16241d] dark:text-slate-500">
            No officers listed yet.
        </p>
    @else
        <ul class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($officers as $officer)
                <li data-officer
                    class="flex flex-col items-center rounded-2xl border border-slate-200 bg-[color:var(--card-bg)] px-4 py-6 text-center shadow-sm dark:border-slate-700">
                    <div class="org-avatar h-24 w-24 text-2xl shadow-sm">
                        @if ($officer['photo_url'])
                            <img src="{{ $officer['photo_url'] }}" alt="{{ $officer['name'] }}" loading="lazy"
                                onerror="this.style.display='none';this.nextElementSibling.style.display='inline';" />
                            <span style="display:none;">{{ $officer['initials'] }}</span>
                        @else
                            {{ $officer['initials'] }}
                        @endif
                    </div>
                    <p class="mt-4 font-bold leading-snug text-slate-800 dark:text-slate-100">{{ $officer['name'] }}</p>
                    <p class="mt-1 text-xs font-bold uppercase tracking-[0.12em] text-emerald-700 dark:text-emerald-400">{{ $officer['title'] }}</p>
                    @if ($officer['course_year'])
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $officer['course_year'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>

<section class="mt-10" aria-labelledby="members-heading">
    <h2 id="members-heading" class="brand-font text-xl text-slate-800 dark:text-slate-100">Members</h2>

    @if ($members->isEmpty())
        <p class="mt-4 rounded-2xl border border-dashed border-emerald-200 bg-white p-8 text-center text-sm font-semibold text-slate-400 dark:border-emerald-900/50 dark:bg-[#16241d] dark:text-slate-500">
            No members listed yet.
        </p>
    @else
        <ul class="mt-4 space-y-3">
            @foreach ($members as $member)
                <li data-member
                    class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-[color:var(--card-bg)] px-5 py-3 shadow-sm dark:border-slate-700">
                    <div class="org-avatar h-11 w-11 text-sm">
                        @if ($member['photo_url'])
                            <img src="{{ $member['photo_url'] }}" alt="{{ $member['name'] }}" loading="lazy"
                                onerror="this.style.display='none';this.nextElementSibling.style.display='inline';" />
                            <span style="display:none;">{{ $member['initials'] }}</span>
                        @else
                            {{ $member['initials'] }}
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-slate-800 dark:text-slate-100">{{ $member['name'] }}</p>
                        @if ($member['course_year'])
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $member['course_year'] }}</p>
                        @endif
                    </div>
                    @if ($member['member_since'])
                        <p class="hidden text-xs text-slate-400 sm:block dark:text-slate-500">
                            Member since {{ $member['member_since']->format('M Y') }}
                        </p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
