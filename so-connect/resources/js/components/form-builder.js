/**
 * Alpine component backing the WYSIWYG form-builder editor.
 *
 * The `rows`/`fields` model is the single source of truth. Reordering is driven
 * entirely by explicit buttons — ▲▼ move a row or a field within its column,
 * ◀▶ move a field between the columns of its row — so the canvas needs no drag
 * library and behaves predictably on touch devices.
 */
export function formBuilder(config) {
    return {
        // --- config from server ---
        catalog: config.catalog || {},
        // Registered dynamic option sources: [{ key, label, searchable }].
        optionSources: config.optionSources || [],
        storeUrl: config.storeUrl,
        updateUrl: config.updateUrl,
        uploadUrl: config.uploadUrl,
        csrf: config.csrf,
        isEdit: config.isEdit,

        // --- model ---
        name: config.data.name || '',
        description_text: config.data.description_text || '',
        route_name: config.data.route_name || '',
        system_function: config.data.system_function || '',
        // Sidebar icon key (see MenuHelper::iconNames); '' falls back to the
        // default forms glyph.
        icon: config.data.icon || '',
        fields: config.data.fields || [],
        rows: config.data.rows || [],
        // The letterhead (header) and footer belong to the printed document, so
        // they live under pdf_template alongside the rich-text body and page setup.
        pdf_template: Object.assign(
            {
                html: '',
                page: { size: 'a4', orientation: 'portrait' },
                font: { family: "'Times New Roman', Times, serif", size: '12px' },
                header: { align: 'center' },
                footer: {},
            },
            config.data.pdf_template || {},
        ),

        // --- wizard / ui state ---
        step: 1,
        selectedKey: null,
        routeTouched: false,
        saving: false,
        message: '',
        error: '',

        init() {
            // Keys of already-saved fields are frozen: syncFields() upserts by
            // (form_id, field_key), so renaming one would prune the row and
            // orphan its submissions and scoring variables.
            this.fields.forEach((f) => { f._keyLocked = true; });

            // Auto-slug the route name from the title while creating.
            if (!this.isEdit) {
                this.$watch('name', (v) => {
                    if (!this.routeTouched) this.route_name = this.slug(v);
                });
            }
        },

        slug(v) {
            return (v || '').toString().toLowerCase().trim()
                .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        },

        // --- wizard navigation ---
        get hasTemplate() {
            return (this.pdf_template.html || '').replace(/<[^>]*>/g, '').trim().length > 0
                || /data-field=/.test(this.pdf_template.html || '');
        },

        goToStep(n) {
            this.error = '';
            if (n === 2 && this.fields.length === 0) {
                this.error = 'Add at least one field in Step 1 first.';
                return;
            }
            this.step = Math.max(1, Math.min(3, n));
        },

        nextStep() { this.goToStep(this.step + 1); },
        prevStep() { this.goToStep(this.step - 1); },

        field(key) {
            return this.fields.find((f) => f.field_key === key) || null;
        },

        labelFor(type) {
            return (this.catalog[type] && this.catalog[type].label) || type;
        },

        // --- palette sections ---
        get fieldPalette() {
            return Object.fromEntries(
                Object.entries(this.catalog).filter(([, meta]) => meta.group !== 'layout' && meta.group !== 'special'),
            );
        },

        get layoutPalette() {
            return Object.fromEntries(
                Object.entries(this.catalog).filter(([, meta]) => meta.group === 'layout'),
            );
        },

        get specialPalette() {
            return Object.fromEntries(
                Object.entries(this.catalog).filter(([, meta]) => meta.group === 'special'),
            );
        },

        get hasSpecialPalette() {
            return Object.keys(this.specialPalette).length > 0;
        },

        // --- field creation ---
        addField(type) {
            const label = this.labelFor(type);
            const key = this.keyFromLabel(label);
            const f = {
                field_key: key,
                field_label: label,
                field_type: type,
                is_required: false,
                placeholder_hint: '',
                field_options: this.defaultOptions(type),
                universal_key: '',
                _keyLocked: false, // client-only: key follows the label until saved
            };
            this.fields.push(f);
            // Each new field starts in its own full-width row.
            this.rows.push({ columns: [{ span: 12, fields: [key] }] });
            this.selectedKey = key;
        },

        defaultOptions(type) {
            // A checkbox is a single yes/no checkmark — it carries no option list.
            if (['select', 'radio'].includes(type)) {
                return { options: [{ value: 'option_1', label: 'Option 1' }] };
            }
            // The search field is always sourced — default it to the first
            // registered source so it renders something out of the box.
            if (type === 'search') {
                return { source: (this.optionSources[0] || {}).key || '' };
            }
            if (type === 'age') return { min: 0, max: 150, step: 1 };
            if (type === 'static-text') return { content: 'Static text…' };
            if (type === 'password') return { min: 8 };
            if (type === 'multi-image') return { max_files: 5 };
            if (type === 'computed') return { formula: 'sum', args: [] };
            if (type === 'table-input') {
                return {
                    columns: [{ key: 'column_1', label: 'Column 1', type: 'text', required: false }],
                    row_total: { key: '', label: 'Total', multiply: [] },
                };
            }
            return {};
        },

        /**
         * Auto-generate a field key from its label ("Event Title" →
         * `event_title`), unique within this form (`_2`, `_3`… on collision).
         */
        keyFromLabel(label, excludeKey = null) {
            const base = (label || '').toString().toLowerCase().trim()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '') || 'field';
            const taken = new Set(
                this.fields.filter((f) => f.field_key !== excludeKey).map((f) => f.field_key),
            );
            if (!taken.has(base)) return base;
            let i = 2;
            while (taken.has(`${base}_${i}`)) i += 1;
            return `${base}_${i}`;
        },

        /** Live key regeneration while the label of an unsaved field is edited. */
        onLabelInput(f) {
            if (f._keyLocked) return;
            const fresh = this.keyFromLabel(f.field_label, f.field_key);
            if (fresh !== f.field_key) this.renameFieldKey(f.field_key, fresh);
        },

        /** Rename a field key everywhere the model references it. */
        renameFieldKey(oldKey, newKey) {
            const f = this.field(oldKey);
            if (!f) return;
            f.field_key = newKey;
            this.rows.forEach((row) => {
                row.columns.forEach((col) => {
                    col.fields = col.fields.map((k) => (k === oldKey ? newKey : k));
                });
            });
            this.fields.forEach((other) => {
                if (other.field_options && other.field_options.visible_when
                    && other.field_options.visible_when.field === oldKey) {
                    other.field_options.visible_when.field = newKey;
                }
            });
            if (this.selectedKey === oldKey) this.selectedKey = newKey;
        },

        removeField(key) {
            this.fields = this.fields.filter((f) => f.field_key !== key);
            this.rows.forEach((row) => {
                row.columns.forEach((col) => {
                    col.fields = col.fields.filter((k) => k !== key);
                });
            });
            this.rows = this.rows.filter((row) => row.columns.some((c) => c.fields.length));
            // Conditions pointing at the removed field would dangle — drop them.
            this.fields.forEach((f) => {
                if (f.field_options && f.field_options.visible_when && f.field_options.visible_when.field === key) {
                    delete f.field_options.visible_when;
                }
            });
            if (this.selectedKey === key) this.selectedKey = null;
        },

        // --- conditional visibility ---
        setVisibilityMode(f, mode) {
            if (mode === 'conditional') {
                if (!f.field_options.visible_when) {
                    f.field_options.visible_when = { field: '', op: 'equals', value: '' };
                }
            } else if (f.field_options.visible_when) {
                delete f.field_options.visible_when;
            }
        },
        /** Keys of the fields sharing a row with the given field (self excluded). */
        rowSiblingKeys(fieldKey) {
            const row = this.rows.find((r) => r.columns.some((c) => c.fields.includes(fieldKey)));
            if (!row) return [];
            return row.columns.flatMap((c) => c.fields).filter((k) => k !== fieldKey);
        },
        /**
         * Fields that may control a condition: they must sit in the same row as
         * the dependent field (a field can only react to its row-mates) and
         * carry a value (layout/upload/signature controls are excluded).
         */
        conditionSources(exceptKey) {
            const siblings = new Set(this.rowSiblingKeys(exceptKey));
            return this.fields.filter((f) => siblings.has(f.field_key)
                && !['heading', 'static-text', 'image', 'file', 'signature'].includes(f.field_type));
        },
        conditionController(f) {
            const key = f.field_options.visible_when && f.field_options.visible_when.field;
            return key ? this.field(key) : null;
        },
        needsConditionValue(f) {
            const op = f.field_options.visible_when && f.field_options.visible_when.op;
            return !!op && !['filled', 'empty'].includes(op);
        },

        // --- row / column controls ---
        setColumnCount(rowIndex, count) {
            const row = this.rows[rowIndex];
            const allKeys = row.columns.flatMap((c) => c.fields);
            const span = Math.floor(12 / count);
            const cols = Array.from({ length: count }, () => ({ span, fields: [] }));
            allKeys.forEach((k, i) => cols[i % count].fields.push(k));
            row.columns = cols;
        },

        addRow() {
            this.rows.push({ columns: [{ span: 12, fields: [] }] });
        },

        removeRow(rowIndex) {
            const row = this.rows[rowIndex];
            const keys = row.columns.flatMap((c) => c.fields);
            this.fields = this.fields.filter((f) => !keys.includes(f.field_key));
            this.rows.splice(rowIndex, 1);
        },

        // --- explicit reordering (replaces drag) ---
        /** Move a whole row up (dir=-1) or down (dir=+1), clamped to bounds. */
        moveRow(rowIndex, dir) {
            const target = rowIndex + dir;
            if (target < 0 || target >= this.rows.length) return;
            const [moved] = this.rows.splice(rowIndex, 1);
            this.rows.splice(target, 0, moved);
        },

        /** Reorder a field within its column (dir=-1 up, +1 down). */
        moveField(rowIndex, colIndex, fieldIndex, dir) {
            const fields = this.rows[rowIndex]?.columns[colIndex]?.fields;
            if (!fields) return;
            const target = fieldIndex + dir;
            if (target < 0 || target >= fields.length) return;
            const [moved] = fields.splice(fieldIndex, 1);
            fields.splice(target, 0, moved);
        },

        /** Move a field to the previous (dir=-1) or next (dir=+1) column of its row. */
        moveFieldAcross(rowIndex, colIndex, fieldIndex, dir) {
            const row = this.rows[rowIndex];
            if (!row) return;
            const target = colIndex + dir;
            if (target < 0 || target >= row.columns.length) return;
            const [moved] = row.columns[colIndex].fields.splice(fieldIndex, 1);
            if (moved === undefined) return;
            row.columns[target].fields.push(moved);
        },

        // --- option editing (choice fields) ---
        addOption(key) {
            const f = this.field(key);
            if (!f.field_options.options) f.field_options.options = [];
            const n = f.field_options.options.length + 1;
            f.field_options.options.push({ value: `option_${n}`, label: `Option ${n}` });
        },
        removeOption(key, i) {
            const f = this.field(key);
            f.field_options.options.splice(i, 1);
        },
        isOptioned(type) {
            // Checkbox intentionally excluded: it is a single checkmark, not a
            // multi-option group, so the builder offers no option editor for it.
            return ['select', 'radio'].includes(type);
        },

        // --- dynamic option sources (registered DB-backed entries) ---
        /** Field types that can draw their choices from an OptionSource. */
        supportsSource(type) {
            return ['select', 'search'].includes(type);
        },
        /**
         * Switch a select between hand-typed options and a registered source.
         * The search field is always sourced, so it never calls this.
         */
        setOptionMode(f, mode) {
            if (mode === 'source') {
                if (!f.field_options.source) {
                    f.field_options.source = (this.optionSources[0] || {}).key || '';
                }
            } else {
                delete f.field_options.source;
            }
        },
        supportsAutofillNow(type) {
            return ['date', 'time', 'datetime'].includes(type);
        },
        /**
         * "Current value" options the Autofill dropdown offers for a field
         * type — mirrors App\Support\UniversalField's `current_*` system
         * keys, filtered to the ones that make sense for `type`. Only one of
         * these is ever shown per field: the type is fixed once the field is
         * added, so there's no need to react to it changing later.
         */
        systemAutofillOptions(type) {
            const byType = {
                text: [
                    { value: 'current_date', label: 'Current Date' },
                    { value: 'current_school_year', label: 'Current School Year' },
                ],
                date: [{ value: 'current_date', label: 'Current Date' }],
                time: [{ value: 'current_time', label: 'Time' }],
                datetime: [{ value: 'current_datetime', label: 'Date/Time' }],
                number: [{ value: 'current_year', label: 'Year' }],
                age: [{ value: 'current_year', label: 'Year' }],
            };
            return byType[type] || [];
        },

        // --- table-input column editing ---
        slugColumn(label) {
            return (label || '').toString().toLowerCase().trim()
                .replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '') || 'column';
        },
        addColumn(key) {
            const f = this.field(key);
            if (!f.field_options.columns) f.field_options.columns = [];
            const n = f.field_options.columns.length + 1;
            f.field_options.columns.push({ key: `column_${n}`, label: `Column ${n}`, type: 'text', required: false });
        },
        removeColumn(key, i) {
            const f = this.field(key);
            f.field_options.columns.splice(i, 1);
        },
        onColumnLabel(col) {
            col.key = this.slugColumn(col.label);
        },

        // --- computed args ---
        addArg(key) {
            const f = this.field(key);
            if (!f.field_options.args) f.field_options.args = [];
            f.field_options.args.push('');
        },
        removeArg(key, i) {
            const f = this.field(key);
            f.field_options.args.splice(i, 1);
        },
        // Sibling fields a computed field can reference (numbers + table columns).
        numericSiblings(exceptKey) {
            const out = [];
            this.fields.forEach((f) => {
                if (f.field_key === exceptKey) return;
                if (['number', 'age', 'computed'].includes(f.field_type)) {
                    out.push({ value: f.field_key, label: f.field_label });
                }
                if (f.field_type === 'table-input') {
                    const rt = (f.field_options || {}).row_total || {};
                    if (rt.key) out.push({ value: `${f.field_key}.${rt.key}`, label: `${f.field_label} → ${rt.label || rt.key}` });
                    ((f.field_options || {}).columns || []).forEach((c) => {
                        if (c.type === 'number') out.push({ value: `${f.field_key}.${c.key}`, label: `${f.field_label} → ${c.label}` });
                    });
                }
            });
            return out;
        },
        isNumeric(type) {
            return ['number', 'age'].includes(type);
        },
        isFileLike(type) {
            return ['image', 'file'].includes(type);
        },

        // --- upload accept (checkboxes over the server's hard allowlist) ---
        acceptChoices(type) {
            return type === 'file' ? ['jpeg', 'png', 'heic', 'pdf'] : ['jpeg', 'png', 'heic'];
        },
        acceptList(f) {
            return String(f.field_options.accept || '')
                .split(',')
                .map((e) => e.trim().replace(/^\./, '').toLowerCase())
                .map((e) => (e === 'jpg' ? 'jpeg' : e))
                .filter(Boolean);
        },
        // An empty accept means "everything the allowlist permits".
        acceptHas(f, ext) {
            const list = this.acceptList(f);
            return !list.length || list.includes(ext);
        },
        toggleAccept(f, ext) {
            const choices = this.acceptChoices(f.field_type);
            let list = this.acceptList(f);
            if (!list.length) list = [...choices];
            list = list.includes(ext) ? list.filter((e) => e !== ext) : [...list, ext];
            list = choices.filter((e) => list.includes(e));
            f.field_options.accept = list.length === choices.length ? '' : list.join(',');
        },

        // --- persistence ---
        async save() {
            this.message = '';
            this.error = '';

            this.saving = true;
            // Saving publishes — active/published/sidebar are server-decided.
            const payload = {
                name: this.name,
                description_text: this.description_text,
                route_name: this.route_name,
                system_function: this.system_function || null,
                icon: this.icon || null,
                fields: this.fields.map(({ _keyLocked, ...field }) => field),
                rows: this.rows,
                pdf_template: this.pdf_template,
            };
            try {
                const res = await fetch(this.isEdit ? this.updateUrl : this.storeUrl, {
                    method: this.isEdit ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify(payload),
                });
                const json = await res.json();
                if (res.ok) {
                    this.message = json.message || 'Saved.';
                    // Every field is now persisted — its key is frozen for good.
                    this.fields.forEach((f) => { f._keyLocked = true; });
                    // On both create and edit, return to the Forms list where the
                    // flashed "saved" toast is shown.
                    if (json.redirect) {
                        window.location = json.redirect;
                    }
                } else {
                    this.error = json.message || this.firstError(json.errors) || 'Could not save.';
                }
            } catch (e) {
                this.error = 'Could not save.';
            } finally {
                this.saving = false;
            }
        },

        firstError(errors) {
            if (!errors) return null;
            const k = Object.keys(errors)[0];
            return k ? errors[k][0] : null;
        },
    };
}
