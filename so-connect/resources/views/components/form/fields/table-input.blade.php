@php
    use App\Forms\FieldType;

    /**
     * Table-input special field: a repeating grid whose columns are declared in
     * field_options.columns. Optional per-row computed column (row_total: an
     * operation across chosen columns, see FieldType::tableRowTotal()) and its
     * footer are shown read-only; the server recomputes them (FieldCompute).
     *
     * Expects: $field, $key, $opts, $special.
     */
    $columns = FieldType::tableColumns($opts);
    $rowTotal = FieldType::tableRowTotal($opts);
    $hasRowTotal = $rowTotal !== null;
    $rowTotalKey = $rowTotal['key'] ?? '';
    $events = $special['events'] ?? [];
    $eventOptions = array_map(function ($event) {
        $date = (string) ($event['date'] ?? '');
        if ($date !== '') {
            try {
                $date = \Illuminate\Support\Carbon::parse($date)->format('F j, Y');
            } catch (\Throwable) {
                // Keep the raw string if it isn't a parseable date.
            }
        }

        return [
            'id' => $event['id'],
            'label' => trim(($event['title'] ?? '').($date !== '' ? ' — '.$date : '')),
        ];
    }, $events);

    $oldRows = array_values(array_filter((array) old($key, []), 'is_array'));
    if (! count($oldRows)) {
        $blank = [];
        foreach ($columns as $c) { $blank[$c['key']] = ''; }
        $oldRows = [$blank];
    }
@endphp

<div x-data="{
        rows: {{ Illuminate\Support\Js::from($oldRows) }},
        cols: {{ Illuminate\Support\Js::from($columns) }},
        operands: {{ Illuminate\Support\Js::from($rowTotal['columns'] ?? []) }},
        op: {{ Illuminate\Support\Js::from($rowTotal['op'] ?? 'multiply') }},
        hasRowTotal: {{ $hasRowTotal ? 'true' : 'false' }},
        // Mirrors FieldType::applyRowTotal(): left to right, ÷0 gives 0.
        rowTotal(row) {
            if (!this.hasRowTotal) return 0;
            const values = this.operands.map((k) => parseFloat(row[k]) || 0);
            const result = values.slice(1).reduce((acc, v) => {
                if (this.op === 'add') return acc + v;
                if (this.op === 'subtract') return acc - v;
                if (this.op === 'divide') return v === 0 ? 0 : acc / v;
                return acc * v;
            }, values[0] || 0);
            return Math.round(result * 100) / 100;
        },
        get footer() {
            if (!this.hasRowTotal) return 0;
            return Math.round(this.rows.reduce((s, r) => s + this.rowTotal(r), 0) * 100) / 100;
        },
        addRow() {
            const blank = {};
            this.cols.forEach(c => blank[c.key] = '');
            this.rows.push(blank);
        },
        removeRow(i) { this.rows.splice(i, 1); if (!this.rows.length) this.addRow(); },
        // Publishes this table's per-column sums (plus its row-total column, if
        // any) to the shared formCompute store, so `computed` fields referencing
        // `{{ $key }}.columnKey` live-recompute as rows are typed/added/removed.
        publishSums() {
            const sums = {};
            this.cols.forEach((c) => {
                if (c.type === 'number') {
                    sums[c.key] = this.rows.reduce((s, r) => s + (parseFloat(r[c.key]) || 0), 0);
                }
            });
            if (this.hasRowTotal) {
                sums['{{ $rowTotalKey }}'] = this.footer;
            }
            Alpine.store('formCompute').tableSums['{{ $key }}'] = sums;
        },
        init() {
            this.publishSums();
            this.$watch('rows', () => this.publishSums());
        },
    }" class="overflow-x-auto">
    <table class="w-full border-collapse text-sm">
        <thead>
            <tr class="border-b border-gray-200 dark:border-gray-700">
                @foreach ($columns as $col)
                    <th class="px-2 py-1.5 text-left text-xs font-medium text-gray-500">{{ $col['label'] }}</th>
                @endforeach
                @if ($hasRowTotal)
                    <th class="px-2 py-1.5 text-left text-xs font-medium text-gray-500">{{ $rowTotal['label'] }}</th>
                @endif
                <th class="w-8"></th>
            </tr>
        </thead>
        <tbody>
            <template x-for="(row, i) in rows" :key="i">
                <tr class="border-b border-gray-100 dark:border-gray-800">
                    @foreach ($columns as $col)
                        <td class="px-1 py-1">
                            @if ($col['type'] === 'event-select')
                                <select :name="'{{ $key }}[' + i + '][{{ $col['key'] }}]'" x-model="row['{{ $col['key'] }}']"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:text-white/90">
                                    <option value="">—</option>
                                    @foreach ($eventOptions as $event)
                                        <option value="{{ $event['id'] }}">{{ $event['label'] }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="{{ $col['type'] === 'number' ? 'number' : ($col['type'] === 'date' ? 'date' : 'text') }}"
                                    :name="'{{ $key }}[' + i + '][{{ $col['key'] }}]'" x-model="row['{{ $col['key'] }}']"
                                    @if ($col['type'] === 'number') step="any" @endif
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:text-white/90" />
                            @endif
                        </td>
                    @endforeach
                    @if ($hasRowTotal)
                        <td class="px-2 py-1 text-sm text-gray-600 dark:text-gray-300" x-text="rowTotal(row).toLocaleString()"></td>
                    @endif
                    <td class="px-1 py-1 text-right">
                        <button type="button" @click="removeRow(i)" class="rounded px-2 py-1 text-xs text-error-500">✕</button>
                    </td>
                </tr>
            </template>
        </tbody>
        @if ($hasRowTotal)
            <tfoot>
                <tr class="border-t border-gray-200 dark:border-gray-700">
                    <td colspan="{{ count($columns) }}" class="px-2 py-1.5 text-right text-xs font-medium text-gray-500">Total</td>
                    <td class="px-2 py-1.5 text-sm font-semibold text-gray-800 dark:text-white/90" x-text="footer.toLocaleString()"></td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>
    <button type="button" @click="addRow()" class="mt-2 text-xs font-medium text-brand-500 hover:text-brand-600">+ Add row</button>
</div>
