/**
 * Alpine component driving conditional field visibility on rendered forms.
 *
 * The <form> root tracks every named control's value (seeded from the DOM on
 * init, updated via input/change events — which also catches the ID-scan
 * wizard's programmatic autofill) and answers visible(key) per field wrapper.
 * Hidden wrappers get their controls disabled so their values stay out of the
 * POST; the server re-evaluates the same conditions authoritatively
 * (App\Forms\ConditionEvaluator — keep the semantics in sync).
 */
export function formConditions(config = {}) {
    return {
        conditions: config.conditions || {},
        values: {},

        init() {
            this.$nextTick(() => this.seed());
        },

        seed() {
            this.$root.querySelectorAll('input[name], select[name], textarea[name]').forEach((el) => this.read(el));
        },

        track(event) {
            const el = event.target;
            if (el && el.name) this.read(el);
        },

        read(el) {
            const key = el.name.endsWith('[]') ? el.name.slice(0, -2) : el.name;
            if (!key) return;

            if (el.type === 'checkbox') {
                if (el.name.endsWith('[]')) {
                    const group = this.$root.querySelectorAll(`[name="${CSS.escape(el.name)}"]`);
                    this.values[key] = Array.from(group).filter((c) => c.checked).map((c) => c.value);
                } else {
                    this.values[key] = el.checked ? (el.value || '1') : '';
                }
            } else if (el.type === 'radio') {
                const checked = this.$root.querySelector(`[name="${CSS.escape(el.name)}"]:checked`);
                this.values[key] = checked ? checked.value : '';
            } else if (el.type !== 'file') {
                this.values[key] = el.value;
            }
        },

        visible(key) {
            return this.check(key, []);
        },

        check(key, stack) {
            const cond = this.conditions[key];
            // No condition, or a defensive cycle guard — visible.
            if (!cond || !cond.field || stack.includes(key)) return true;
            // A hidden controller hides everything conditioned on it.
            if (!this.check(cond.field, [...stack, key])) return false;
            return this.passes(cond, this.values[cond.field]);
        },

        passes(cond, value) {
            const expected = String(cond.value ?? '').trim();
            const list = Array.isArray(value)
                ? value.map((v) => String(v).trim()).filter(Boolean)
                : null;
            const scalar = list === null ? String(value ?? '').trim() : '';
            const filled = list === null ? scalar !== '' : list.length > 0;

            switch (cond.op) {
                case 'equals': return list === null ? scalar === expected : list.includes(expected);
                case 'not_equals': return list === null ? scalar !== expected : !list.includes(expected);
                case 'contains': return list === null
                    ? (expected !== '' && scalar.toLowerCase().includes(expected.toLowerCase()))
                    : list.includes(expected);
                case 'filled': return filled;
                case 'empty': return !filled;
                default: return true;
            }
        },

        /** Disable a hidden wrapper's controls so they never post. */
        toggleDisabled(wrapper, isVisible) {
            wrapper.querySelectorAll('input, select, textarea').forEach((el) => {
                el.disabled = !isVisible;
            });
        },
    };
}
