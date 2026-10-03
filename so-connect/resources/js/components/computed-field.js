import Alpine from "alpinejs";

/**
 * Live client-side mirror of App\Forms\FieldCompute's sum/difference/table_sum
 * formulas, so a `computed` field shows its autofilled value while the form is
 * being filled instead of staying blank until submit. The server remains
 * authoritative — FieldCompute::apply() recomputes and overwrites every
 * `computed` value at submit time regardless of what this posts.
 *
 * Reads table-column sums from the shared `formCompute` store (kept current by
 * each table-input field, see table-input.blade.php) and other `computed`
 * fields' resolved values from the same store, so a field can reference either
 * without caring which. `x-effect="value = compute()"` on the host element
 * re-runs this whenever any store value it touched last run changes.
 */
export function computedField(config = {}) {
    return {
        formula: config.formula || "sum",
        args: config.args || [],
        table: config.table || "",
        column: config.column || "",
        fieldKey: config.fieldKey || "",
        value: "",

        resolveNumber(key) {
            if (!key) return 0;

            if (key.includes(".")) {
                const [table, column] = key.split(".");
                const sums = Alpine.store("formCompute").tableSums[table] || {};
                return parseFloat(sums[column]) || 0;
            }

            const computed = Alpine.store("formCompute").computedValues[key];
            if (computed !== undefined) return parseFloat(computed) || 0;

            // Any keystroke anywhere bumps this tick, so a plain sibling number
            // field (not a table column, not another computed field) still
            // recomputes live even though its value isn't store-tracked.
            void Alpine.store("formCompute").tick;
            const el = document.querySelector(`[name="${CSS.escape(key)}"]`);
            return parseFloat(el?.value) || 0;
        },

        compute() {
            let result = 0;

            if (this.formula === "difference") {
                result = this.resolveNumber(this.args[0]);
                this.args.slice(1).forEach((key) => {
                    result -= this.resolveNumber(key);
                });
            } else if (this.formula === "table_sum") {
                const sums =
                    Alpine.store("formCompute").tableSums[this.table] || {};
                result = parseFloat(sums[this.column]) || 0;
            } else {
                result = this.args.reduce(
                    (sum, key) => sum + this.resolveNumber(key),
                    0,
                );
            }

            result = Math.round(result * 100) / 100;

            if (this.fieldKey) {
                Alpine.store("formCompute").computedValues[this.fieldKey] =
                    result;
            }

            return result;
        },
    };
}
