/**
 * Text/sentence-based scoring trigger editor (replaces the Blockly workspace).
 *
 * The admin composes a rule as a sentence:
 *   "When [a submission of «Form»] is approved, if [conditions] then add […]."
 * Conditions are grouped rows with All/Any (and/or) switches and per-row NOT.
 *
 * The component round-trips the server's trigger AST — `fromAst()` on init loads
 * an existing rule (Blockly-era or not, since both stored the same AST) and
 * `toAst()` on save emits it. The PUT payload stays {workspace:null, trigger,
 * enabled}; the server re-validates the AST (TriggerValidator), so the contract
 * with ScoringRuleEngine is unchanged.
 */
const LIST_FIELD_TYPES = ['text-list', 'table-input', 'multi-image', 'workplan-events'];
const NUMBER_TYPES = ['number', 'age', 'computed'];

export function tallyEditor(config) {
    return {
        saveUrl: config.saveUrl,
        csrf: config.csrf,
        variables: config.variables || { forms: [], universal: [], plan: [] },
        enabled: config.enabled ?? true,
        saving: false,
        error: '',

        // --- sentence model ---
        source: 'form_submission', // form_submission | event_plan
        formId: '',
        root: { op: 'and', negated: false, children: [] }, // condition group
        add: { kind: 'const', value: 1, var: '', divisor: 1 },

        init() {
            this.fromAst(config.trigger || null);
        },

        // ---- variable catalog for the current source/form ----
        get selectedForm() {
            return this.variables.forms.find((f) => String(f.id) === String(this.formId)) || null;
        },

        /** The New Event form linked to the selected (After Event) form, if any. */
        get linkedForm() {
            const form = this.source === 'form_submission' ? this.selectedForm : null;
            if (!form || !form.linked_form_id) return null;
            return this.variables.forms.find((f) => String(f.id) === String(form.linked_form_id)) || null;
        },

        get variableGroups() {
            const groups = [];
            if (this.source === 'form_submission') {
                const form = this.selectedForm;
                if (form) {
                    groups.push({
                        label: form.name,
                        items: form.fields.map((fld) => ({ value: `field:${fld.key}`, label: this.fieldLabel(fld) })),
                    });
                }
                const linked = this.linkedForm;
                if (linked) {
                    groups.push({
                        label: `${linked.name} (linked event)`,
                        items: linked.fields.map((fld) => ({ value: `new_event:${fld.key}`, label: this.fieldLabel(fld) })),
                    });
                }
            } else {
                groups.push({
                    label: 'Event plan',
                    items: this.variables.plan.map((p) => ({ value: `plan:${p.key}`, label: p.label })),
                });
            }
            groups.push({
                label: 'Universal fields',
                items: this.variables.universal.map((u) => ({ value: `universal:${u.key}`, label: u.label })),
            });
            return groups;
        },

        /**
         * Variables for the "Then add" picker. "One instance per count of"
         * divides a number, so it offers only number variables (keeping a
         * previously saved non-number pick visible so it isn't silently lost).
         */
        get addVariableGroups() {
            if (this.add.kind !== 'floor_div') return this.variableGroups;
            const numeric = new Set(this.numberVariableKeys());
            const groups = this.variableGroups
                .map((g) => ({ ...g, items: g.items.filter((it) => numeric.has(it.value) || it.value === this.add.var) }))
                .filter((g) => g.items.length);
            return groups;
        },

        numberVariableKeys() {
            const keys = [];
            const fields = (form, prefix) => (form ? form.fields : [])
                .filter((fld) => NUMBER_TYPES.includes(fld.type))
                .forEach((fld) => keys.push(`${prefix}:${fld.key}`));
            if (this.source === 'form_submission') {
                fields(this.selectedForm, 'field');
                fields(this.linkedForm, 'new_event');
            } else {
                this.variables.plan.filter((p) => p.type === 'number').forEach((p) => keys.push(`plan:${p.key}`));
            }
            this.variables.universal.filter((u) => NUMBER_TYPES.includes(u.type)).forEach((u) => keys.push(`universal:${u.key}`));
            return keys;
        },

        /**
         * Picker label for a form field. List fields (text list, table, images)
         * are flagged: comparing them (≥, <, …) or dividing them uses their row
         * count, and "one instance per row of" counts their rows.
         */
        fieldLabel(fld) {
            return LIST_FIELD_TYPES.includes(fld.type) ? `${fld.label} (rows)` : fld.label;
        },

        /** The field definition behind a `field:<key>` / `new_event:<key>` variable (for option pickers). */
        varDef(varKey) {
            if (!varKey) return null;
            let form = null;
            let key = '';
            if (varKey.startsWith('field:')) {
                form = this.selectedForm;
                key = varKey.slice('field:'.length);
            } else if (varKey.startsWith('new_event:')) {
                form = this.linkedForm;
                key = varKey.slice('new_event:'.length);
            }
            return form ? form.fields.find((fld) => fld.key === key) || null : null;
        },

        /** Option pairs to offer as the comparison value for a row's variable. */
        optionsFor(row) {
            const def = this.varDef(row.var);
            return def && def.options && def.options.length ? def.options : null;
        },

        needsValue(row) {
            return row.op !== 'not_empty';
        },

        // ---- row / group operations ----
        newRow() {
            return { kind: 'row', not: false, var: '', op: '=', value: '' };
        },
        addRow(group) {
            group.children.push(this.newRow());
        },
        addGroup(group) {
            group.children.push({ kind: 'group', op: 'and', negated: false, children: [this.newRow()] });
        },
        removeChild(group, index) {
            group.children.splice(index, 1);
        },

        // ---- AST → model ----
        fromAst(trigger) {
            if (!trigger || typeof trigger !== 'object') {
                this.root = { op: 'and', negated: false, children: [] };
                this.formId = this.variables.forms[0] ? String(this.variables.forms[0].id) : '';
                return;
            }
            const when = trigger.when || {};
            this.source = when.source === 'event_plan' ? 'event_plan' : 'form_submission';
            this.formId = when.form_id ? String(when.form_id) : (this.variables.forms[0] ? String(this.variables.forms[0].id) : '');

            this.root = this.groupFromAst(trigger.if);

            const add = (trigger.then && trigger.then.add) || {};
            this.add = {
                kind: ['const', 'floor_div', 'count_list'].includes(add.kind) ? add.kind : 'const',
                value: add.value ?? 1,
                var: add.var ?? '',
                divisor: add.divisor ?? 1,
            };
        },

        /** Normalise any condition AST node into a top-level group. */
        groupFromAst(node) {
            if (!node) return { op: 'and', negated: false, children: [] };
            // A bare comparison → single-row group.
            if (this.isCompare(node)) {
                return { op: 'and', negated: false, children: [this.rowFromAst(node)] };
            }
            if (node.op === 'not') {
                const inner = node.children && node.children[0];
                const g = this.groupFromAst(inner);
                g.negated = !g.negated;
                return g;
            }
            // and/or logic.
            return {
                op: node.op === 'or' ? 'or' : 'and',
                negated: false,
                children: (node.children || []).map((c) => this.childFromAst(c)),
            };
        },

        childFromAst(node) {
            if (this.isCompare(node)) return this.rowFromAst(node);
            if (node.op === 'not' && this.isCompare(node.children && node.children[0])) {
                const row = this.rowFromAst(node.children[0]);
                row.not = true;
                return row;
            }
            // Nested group.
            return { kind: 'group', ...this.groupFromAst(node) };
        },

        rowFromAst(node) {
            return {
                kind: 'row',
                not: false,
                var: (node.left && node.left.var) || '',
                op: node.op || '=',
                value: node.right && 'value' in node.right ? String(node.right.value) : '',
            };
        },

        isCompare(node) {
            return node && typeof node === 'object'
                && ['=', '!=', '>', '>=', '<', '<=', 'contains', 'not_empty'].includes(node.op);
        },

        // ---- model → AST ----
        toAst() {
            const when = { source: this.source };
            if (this.source === 'form_submission') when.form_id = parseInt(this.formId, 10) || 0;
            when.status = 'approved';

            const trigger = { when, if: this.groupToAst(this.root), then: { add: this.addToAst() } };
            return trigger;
        },

        groupToAst(group) {
            const children = group.children.map((c) => this.childToAst(c)).filter(Boolean);
            if (children.length === 0) return null;
            let node = children.length === 1 && !group.negated
                ? children[0]
                : { op: group.op, children };
            if (group.negated) node = { op: 'not', children: [children.length === 1 ? children[0] : { op: group.op, children }] };
            return node;
        },

        childToAst(child) {
            if (child.kind === 'group') return this.groupToAst(child);
            return this.rowToAst(child);
        },

        rowToAst(row) {
            if (!row.var) return null;
            const cmp = { op: row.op, left: { var: row.var } };
            if (row.op !== 'not_empty') cmp.right = { value: this.coerce(row.value) };
            return row.not ? { op: 'not', children: [cmp] } : cmp;
        },

        addToAst() {
            if (this.add.kind === 'const') return { kind: 'const', value: parseInt(this.add.value, 10) || 1 };
            if (this.add.kind === 'floor_div') return { kind: 'floor_div', var: this.add.var, divisor: parseFloat(this.add.divisor) || 1 };
            return { kind: 'count_list', var: this.add.var };
        },

        coerce(value) {
            if (value === '' || value === null) return '';
            const n = Number(value);
            return Number.isNaN(n) || String(n) !== String(value).trim() ? value : n;
        },

        usesVarPrefix(prefix) {
            const inGroup = (group) => group.children.some((c) => (c.kind === 'group' ? inGroup(c) : (c.var || '').startsWith(prefix)));
            return inGroup(this.root) || (this.add.kind !== 'const' && (this.add.var || '').startsWith(prefix));
        },

        // ---- persistence ----
        async save() {
            this.error = '';
            // Guardrails mirroring the server validator, for friendlier errors.
            if (this.source === 'form_submission' && !(parseInt(this.formId, 10) > 0)) {
                this.error = 'Choose which form this trigger watches.';
                return;
            }
            if ((this.add.kind === 'floor_div' || this.add.kind === 'count_list') && !this.add.var) {
                this.error = 'Pick the variable for the "add" amount.';
                return;
            }
            if (this.add.kind === 'floor_div' && !(parseFloat(this.add.divisor) > 0)) {
                this.error = 'The number to divide by must be greater than 0.';
                return;
            }
            if (!this.linkedForm && this.usesVarPrefix('new_event:')) {
                this.error = 'Linked New Event fields can only be used when the trigger watches the After Event form.';
                return;
            }

            this.saving = true;
            try {
                const res = await fetch(this.saveUrl, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ workspace: null, trigger: this.toAst(), enabled: this.enabled }),
                });
                const json = await res.json();
                if (res.ok && json.redirect) {
                    window.location = json.redirect;
                } else {
                    this.error = (json.errors && (json.errors.trigger?.[0] || Object.values(json.errors)[0]?.[0]))
                        || json.message || 'Could not save the trigger.';
                }
            } catch (e) {
                this.error = 'Could not save the trigger.';
            } finally {
                this.saving = false;
            }
        },
    };
}
