<section class="mt-8" aria-labelledby="next-event-heading"
    x-data="eventCountdown({{ Illuminate\Support\Js::from($upcomingEvents) }})">
    <h2 id="next-event-heading" class="brand-font text-xl text-slate-800 dark:text-slate-100">Next Event</h2>
    <div class="mt-4 rounded-3xl border border-emerald-200 bg-[color:var(--card-bg)] p-6 shadow-sm sm:p-8 dark:border-emerald-900/50">
        <template x-if="nextEvent">
            <div>
                <div class="mb-8 flex flex-wrap items-start justify-center gap-3 sm:gap-5" role="timer" aria-label="Time until the next event">
                    @foreach (['hours' => 'Hours', 'minutes' => 'Minutes', 'seconds' => 'Seconds'] as $unit => $label)
                        <div class="text-center">
                            <div class="flex justify-center gap-1 sm:gap-2">
                                <template x-for="(digit, index) in {{ $unit }}.split('')" :key="index">
                                    <span class="flex h-14 w-9 items-center justify-center rounded-xl border border-emerald-200 bg-[color:var(--brand-cream)] font-mono text-3xl font-semibold tabular-nums sm:h-20 sm:w-14 sm:text-4xl dark:border-emerald-800"
                                        x-text="digit"></span>
                                </template>
                            </div>
                            <p class="mt-2 text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $label }}</p>
                        </div>
                        @if (! $loop->last)
                            <span aria-hidden="true" class="pt-3 text-2xl text-emerald-600 sm:pt-5 sm:text-3xl dark:text-emerald-400">:</span>
                        @endif
                    @endforeach
                </div>
                <h3 class="brand-font break-words text-2xl text-slate-800 dark:text-slate-100" x-text="nextEvent.title"></h3>
                <p class="mt-3 break-words text-sm text-slate-600 dark:text-slate-300" x-text="nextEvent.location"></p>
                <p class="mt-1 text-sm font-semibold text-emerald-700 dark:text-emerald-400" x-text="nextEvent.date"></p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400" x-text="nextEvent.start_time + ' – ' + nextEvent.end_time"></p>
            </div>
        </template>
        <p x-show="!nextEvent" class="text-center text-sm text-slate-500 dark:text-slate-400">No upcoming events.</p>
    </div>
</section>

<section class="mt-8" aria-labelledby="organization-events-heading">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 id="organization-events-heading" class="brand-font text-xl text-slate-800 dark:text-slate-100">Events</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">Nearest to the current date first</p>
    </div>
    @if ($events->isEmpty())
        <div class="rounded-2xl border border-dashed border-emerald-200 bg-[color:var(--card-bg)] p-10 text-center dark:border-emerald-900/50">
            <p class="text-sm text-slate-500 dark:text-slate-400">No events yet.</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($events as $event)
                <article data-event-id="{{ $event['id'] }}" class="flex min-h-60 flex-col rounded-2xl border border-emerald-200 bg-[color:var(--card-bg)] p-6 shadow-sm dark:border-emerald-900/50">
                    <h3 class="brand-font break-words text-xl text-slate-800 dark:text-slate-100">{{ $event['title'] }}</h3>
                    <div class="mt-auto pt-8">
                        <p class="break-words text-sm text-slate-600 dark:text-slate-300">{{ $event['location'] }}</p>
                        <time datetime="{{ $event['start'] }}" class="mt-2 block text-sm font-semibold text-emerald-700 dark:text-emerald-400">{{ $event['date'] }}</time>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $event['start_time'] }} &ndash; {{ $event['end_time'] }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>
