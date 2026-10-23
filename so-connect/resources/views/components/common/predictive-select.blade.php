@props([
    'options' => [],
    'name' => null,
    'selected' => '',
    'placeholder' => 'Select an option',
    'searchPlaceholder' => 'Type to search…',
    'allowClear' => true,
    'clearValue' => '',
    'autoSubmit' => false,
    'required' => false,
    'id' => 'predictive-select-'.Str::random(8),
])

<div
    x-data="predictiveSelect({ options: {{ \App\Support\JsLiteral::from($options) }}, selected: @js((string) $selected), autoSubmit: @js((bool) $autoSubmit), allowClear: @js((bool) $allowClear), clearValue: @js((string) $clearValue), placeholder: @js($placeholder), searchPlaceholder: @js($searchPlaceholder) })"
    x-modelable="value"
    @click.outside="close()"
    {{ $attributes->class(['relative min-w-0']) }}>
    @if($name)
        <input type="hidden" name="{{ $name }}" x-model="value" @required($required) />
    @endif

    <div class="relative">
        <span class="pointer-events-none absolute left-3.5 top-1/2 z-10 -translate-y-1/2 text-gray-400 transition-colors"
            :class="open ? 'text-brand-600 dark:text-brand-400' : ''" aria-hidden="true">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path>
            </svg>
        </span>
        <input
            id="{{ $id }}"
            x-ref="input"
            type="text"
            x-model="query"
            @focus="show()"
            @click="show()"
            @input="onInput()"
            @keydown.arrow-down.prevent="move(1)"
            @keydown.arrow-up.prevent="move(-1)"
            @keydown.enter.prevent="chooseActive()"
            @keydown.escape.prevent="close()"
            @keydown.tab="close()"
            role="combobox"
            aria-autocomplete="list"
            :aria-expanded="open"
            aria-controls="{{ $id }}-options"
            autocomplete="off"
            :placeholder="open ? searchPlaceholder : placeholder"
            class="h-12 w-full rounded-xl border border-gray-300 bg-white py-2 pl-10 pr-20 text-sm font-medium text-gray-800 shadow-sm transition placeholder:font-normal placeholder:text-gray-400 hover:border-brand-300 focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:hover:border-brand-600" />
        <button type="button" x-show="value && value !== clearValue && @js((bool) $allowClear)" x-transition.opacity @click.stop="clearAndFocus()" aria-label="Clear selection"
            class="absolute right-10 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-brand-500/30 dark:hover:bg-white/10 dark:hover:text-white">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 6 12 12M18 6 6 18" /></svg>
        </button>
        <button type="button" @click="toggle()" tabindex="-1" aria-label="Open options"
            class="absolute right-2 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg text-gray-400 transition hover:bg-brand-50 hover:text-brand-700 dark:hover:bg-brand-500/10 dark:hover:text-brand-300">
            <svg class="h-4 w-4 transition-transform duration-200" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" /></svg>
        </button>
    </div>

    <div x-show="open" x-cloak x-transition.origin.top.duration.150ms id="{{ $id }}-options" x-ref="list" role="listbox"
        class="absolute z-[100] mt-2 max-h-72 w-full overflow-auto rounded-xl border border-brand-200 bg-white p-1.5 shadow-xl shadow-gray-900/10 dark:border-brand-500/30 dark:bg-gray-900">
        @if($allowClear)
            <button type="button" @click="clear()" role="option" :aria-selected="value === clearValue"
                class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-sm transition"
                :class="value === clearValue ? 'bg-brand-50 font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300' : 'text-gray-600 hover:bg-brand-50 hover:text-brand-700 dark:text-gray-300 dark:hover:bg-brand-500/10 dark:hover:text-brand-300'">
                <span>{{ $placeholder }}</span>
                <svg x-show="value === clearValue" class="h-4 w-4 text-brand-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 5.292a1 1 0 0 1 .004 1.414l-8 8a1 1 0 0 1-1.416 0l-4-4a1 1 0 1 1 1.416-1.412L8 12.586l7.296-7.294a1 1 0 0 1 1.408 0Z" clip-rule="evenodd" /></svg>
            </button>
        @endif
        <template x-for="(option, index) in filtered" :key="option.value">
            <button type="button" @mouseenter="activeIndex = index" @click="choose(option)" role="option"
                :aria-selected="value === option.value" :data-active="activeIndex === index"
                class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition"
                :class="activeIndex === index || value === option.value ? 'bg-brand-50 font-medium text-brand-800 dark:bg-brand-500/15 dark:text-brand-300' : 'text-gray-700 hover:bg-brand-50/70 dark:text-gray-200 dark:hover:bg-brand-500/10'">
                <span class="min-w-0 truncate" x-effect="renderLabel($el, option)"></span>
                <svg x-show="value === option.value" aria-hidden="true" class="h-4 w-4 shrink-0 text-brand-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 5.292a1 1 0 0 1 .004 1.414l-8 8a1 1 0 0 1-1.416 0l-4-4a1 1 0 1 1 1.416-1.412L8 12.586l7.296-7.294a1 1 0 0 1 1.408 0Z" clip-rule="evenodd" /></svg>
            </button>
        </template>
        <div x-show="filtered.length === 0" class="px-4 py-6 text-center">
            <svg class="mx-auto h-6 w-6 text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            <p class="mt-2 text-sm font-medium text-gray-500 dark:text-gray-400" x-text="query ? 'No matching option' : 'No options available'"></p>
        </div>
    </div>
</div>
