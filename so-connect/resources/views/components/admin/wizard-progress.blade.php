@props(['step', 'total' => 5, 'labels' => []])

<div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex items-center">
        @for ($i = 1; $i <= $total; $i++)
            {{-- Step circle --}}
            <div class="flex shrink-0 flex-col items-center">
                <div class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition-colors
                    @if($i < $step) bg-brand-500 text-white
                    @elseif($i == $step) bg-brand-500 text-white ring-4 ring-brand-100 dark:ring-brand-500/20
                    @else bg-gray-200 text-gray-400 dark:bg-gray-700 dark:text-gray-500 @endif">
                    @if($i < $step)
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    @else
                        {{ $i }}
                    @endif
                </div>
                @if(!empty($labels[$i]))
                    <span class="mt-1.5 hidden text-xs font-medium sm:block
                        @if($i == $step) text-brand-600 dark:text-brand-400
                        @elseif($i < $step) text-gray-500 dark:text-gray-400
                        @else text-gray-400 dark:text-gray-500 @endif">
                        {{ $labels[$i] }}
                    </span>
                @endif
            </div>

            {{-- Connector bar --}}
            @if($i < $total)
                <div class="flex-1 mx-2 h-0.5 @if($i < $step) bg-brand-500 @else bg-gray-200 dark:bg-gray-700 @endif"></div>
            @endif
        @endfor
    </div>
</div>
