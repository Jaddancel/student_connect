{{-- WHERE conditions of the Data Box token bound to Alpine variable {{ $owner }}. --}}
@php
    $pill = 'rounded-lg border border-brand-300 bg-brand-50 px-2 py-1 font-mono text-xs text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-brand-500/40 dark:bg-brand-500/10 dark:text-brand-300';
    $kw = 'font-mono text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
@endphp
<div class="flex flex-wrap items-center gap-2">
    <span class="{{ $kw }}">Where</span>
    <template x-for="(c, ci) in {{ $owner }}.where" :key="ci">
        <span class="flex flex-wrap items-center gap-1">
            <span x-show="ci > 0" class="{{ $kw }}">and</span>
            <select x-model="c.column" class="{{ $pill }}">
                <option value="">column…</option>
                <template x-for="col in columns(conditionTable({{ $owner }}))" :key="col.name">
                    <option :value="col.name" x-text="col.name" :selected="col.name === c.column"></option>
                </template>
            </select>
            <select x-model="c.op" class="{{ $pill }}">
                <template x-for="op in schema.ops" :key="op">
                    <option :value="op" x-text="op.replace('_', ' ')" :selected="op === c.op"></option>
                </template>
            </select>
            <template x-if="needsValue(c.op)">
                <span class="flex items-center gap-1">
                    <select x-model="c.param" class="{{ $pill }}">
                        <option value="">value</option>
                        <template x-for="param in definition.parameters" :key="param.name">
                            <option :value="param.name" x-text="'ask: ' + param.name" :selected="param.name === c.param"></option>
                        </template>
                    </select>
                    <input type="text" x-show="!c.param" x-model="c.value"
                        :placeholder="['in', 'not_in'].includes(c.op) ? 'a, b, c' : 'value'" class="{{ $pill }} w-32" />
                </span>
            </template>
            <button type="button" @click="{{ $owner }}.where.splice(ci, 1)" class="text-xs text-gray-400 hover:text-error-500">✕</button>
        </span>
    </template>
    <button type="button" @click="addCondition({{ $owner }})" class="text-xs font-medium text-brand-500"
        :disabled="!conditionTable({{ $owner }})">+ condition</button>
</div>
