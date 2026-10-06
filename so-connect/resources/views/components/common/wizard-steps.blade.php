@props(['steps' => []])

{{--
    Compact 3-step stepper for the form-creation wizard. Renders inside a
    formBuilder Alpine scope and reads/writes its `step` state (1-indexed);
    clicking a step calls `goToStep()` so guards (e.g. "add a field first")
    still apply.
--}}
<div class="flex items-center gap-2 rounded-2xl border border-gray-200 bg-palette-surface p-3 dark:border-gray-800 dark:bg-white/[0.03]">
    @foreach ($steps as $i => $label)
        @php $num = $i + 1; @endphp
        <button type="button" @click="goToStep({{ $num }})"
            class="flex flex-1 items-center gap-2 rounded-xl px-3 py-2 text-left transition"
            :class="step === {{ $num }} ? 'bg-brand-50 dark:bg-brand-500/10' : 'hover:bg-gray-50 dark:hover:bg-white/[0.02]'">
            <span class="flex h-7 w-7 flex-none items-center justify-center rounded-full text-xs font-semibold"
                :class="step > {{ $num }}
                    ? 'bg-success-500 text-white'
                    : (step === {{ $num }} ? 'bg-brand-500 text-white' : 'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-300')">
                <span x-show="step <= {{ $num }}">{{ $num }}</span>
                <span x-show="step > {{ $num }}">✓</span>
            </span>
            <span class="min-w-0">
                <span class="block text-[10px] uppercase tracking-wide text-gray-400">Step {{ $num }}</span>
                <span class="block truncate text-sm font-medium text-gray-800 dark:text-white/90">{{ $label }}</span>
            </span>
        </button>
        @if (! $loop->last)
            <div class="hidden h-px w-6 flex-none bg-gray-200 dark:bg-gray-700 sm:block"></div>
        @endif
    @endforeach
</div>
