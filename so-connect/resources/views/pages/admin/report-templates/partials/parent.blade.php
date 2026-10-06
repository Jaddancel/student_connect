{{-- Re-parent the Data Box token bound to Alpine variable {{ $owner }}. --}}
<div class="flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3 text-xs text-gray-500 dark:border-gray-800">
    <span>Belongs to</span>
    <select class="h-8 rounded-lg border border-gray-300 bg-transparent px-2 text-xs dark:border-gray-700"
        @change="moveTo({{ $owner }}, $event.target.value)">
        <option value="" :selected="!parentOf({{ $owner }})">(top level)</option>
        <template x-for="g in groups().filter((g) => g !== {{ $owner }} && !isDescendant(g, {{ $owner }}))" :key="g.id">
            <option :value="g.id" x-text="'GROUP ' + tokenPath(g)" :selected="parentOf({{ $owner }}) === g"></option>
        </template>
    </select>
    <span class="text-gray-400">Moving a token clears data bindings that depend on its group.</span>
</div>
