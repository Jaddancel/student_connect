import Sortable from 'sortablejs';

/**
 * Alpine component backing the WYSIWYG form-builder editor.
 *
 * The `rows`/`fields` model is the single source of truth; SortableJS
 * provides drag-to-reorder UX and writes back into the model on drop. Explicit
 * buttons (add/remove/move/columns) cover everything drag does, so the builder
 * stays usable regardless.
 */
export function formBuilder(config) {
    return {
        // --- config from server ---
        catalog: config.catalog || {},
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
            this.$nextTick(() => this.wireSortables());
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
                Object.entries(this.catalog).filter(([, meta]) => meta.group !== 'layout'),
            );
        },

        get layoutPalette() {
            return Object.fromEntries(
                Object.entries(this.catalog).filter(([, meta]) => meta.group === 'layout'),
            );
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
            this.$nextTick(() => this.wireSortables());
        },

        defaultOptions(type) {
            if (['select', 'radio', 'checkbox'].includes(type)) {
                return { options: [{ value: 'option_1', label: 'Option 1' }] };
            }
            if (type === 'age') return { min: 0, max: 150, step: 1 };
            if (type === 'static-text') return { content: 'Static text…' };
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
            this.$nextTick(() => this.wireSortables());
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
            this.$nextTick(() => this.wireSortables());
        },

        addRow() {
            this.rows.push({ columns: [{ span: 12, fields: [] }] });
            this.$nextTick(() => this.wireSortables());
        },

        removeRow(rowIndex) {
            const row = this.rows[rowIndex];
            const keys = row.columns.flatMap((c) => c.fields);
            this.fields = this.fields.filter((f) => !keys.includes(f.field_key));
            this.rows.splice(rowIndex, 1);
            this.$nextTick(() => this.wireSortables());
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
            return ['select', 'radio', 'checkbox'].includes(type);
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

        // --- drag wiring ---
        wireSortables() {
            this.$root.querySelectorAll('[data-col-list]').forEach((el) => {
                if (el._sortable) el._sortable.destroy();
                el._sortable = Sortable.create(el, {
                    group: 'builder-fields',
                    animation: 150,
                    handle: '[data-drag]',
                    onEnd: (evt) => this.onDrop(evt),
                });
            });
        },

        onDrop(evt) {
            const fromRow = parseInt(evt.from.dataset.row, 10);
            const fromCol = parseInt(evt.from.dataset.col, 10);
            const toRow = parseInt(evt.to.dataset.row, 10);
            const toCol = parseInt(evt.to.dataset.col, 10);
            if ([fromRow, fromCol, toRow, toCol].some(Number.isNaN)) return;

            const source = this.rows[fromRow].columns[fromCol].fields;
            const target = this.rows[toRow].columns[toCol].fields;
            const [moved] = source.splice(evt.oldIndex, 1);
            if (moved === undefined) return;
            target.splice(evt.newIndex, 0, moved);

            // Drop empty rows, then re-render and re-wire from the model.
            this.rows = this.rows.filter((row) => row.columns.some((c) => c.fields.length));
            this.$nextTick(() => this.wireSortables());
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
                    if (!this.isEdit && json.redirect) {
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
