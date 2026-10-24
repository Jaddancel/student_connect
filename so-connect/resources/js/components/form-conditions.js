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
        submitting: false,
        registrationStep: Number(config.registrationStep || 0),
        applicationStep: Number(config.applicationStep || 0),
        applicationMaxStep: Number(config.applicationStep || 0),
        applicationSteps: Number(config.applicationSteps || 0),
        applicationStepError: '',

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

        startSubmit() {
            this.submitting = true;
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
            } else if (el.type === 'file') {
                this.values[key] = Array.from(el.files || []).map((file) => file.name);
            } else {
                this.values[key] = el.value;
            }
        },

        nextApplicationStep() {
            if (!this.validateApplicationStep(this.applicationStep)) return;
            this.applicationStep = Math.min(this.applicationSteps, this.applicationStep + 1);
            this.applicationMaxStep = Math.max(this.applicationMaxStep, this.applicationStep);
            this.applicationStepError = '';
            this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
        },

        startApplication() {
            this.applicationStep = 1;
            this.applicationMaxStep = Math.max(this.applicationMaxStep, 1);
            this.applicationStepError = '';
            this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
        },

        previousApplicationStep() {
            this.applicationStep = Math.max(0, this.applicationStep - 1);
            this.applicationStepError = '';
            this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
        },

        goToApplicationStep(step) {
            if (this.applicationStep === 0 || step > this.applicationMaxStep) return;
            this.applicationStep = Math.max(1, Math.min(this.applicationSteps, Number(step)));
            this.applicationStepError = '';
        },

        validateApplicationStep(step) {
            const panel = this.$root.querySelector(`[data-application-panel="${step}"]`);
            if (!panel) return true;

            const missing = Array.from(panel.querySelectorAll('[data-field-required="true"]'))
                .find((wrapper) => {
                    if (wrapper.offsetParent === null) return false;
                    const controls = Array.from(wrapper.querySelectorAll('input:not([type="hidden"]), select, textarea'))
                        .filter((control) => !control.disabled);
                    if (!controls.length) return false;
                    if (controls[0].type === 'checkbox' || controls[0].type === 'radio') {
                        return !controls.some((control) => control.checked);
                    }
                    if (controls[0].type === 'file') return !controls.some((control) => control.files?.length);
                    return !controls.some((control) => String(control.value || '').trim() !== '');
                });

            if (!missing) return true;
            this.applicationStepError = 'Complete all required fields before continuing.';
            const control = missing.querySelector('input:not([type="hidden"]), select, textarea');
            control?.focus();
            missing.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        },

        reviewValue(key, isDocument = false) {
            const value = this.values[key];
            if (Array.isArray(value)) return value.length ? value.join(', ') : (isDocument ? 'Not attached' : 'Not provided');
            return String(value || '').trim() || (isDocument ? 'Not attached' : 'Not provided');
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
