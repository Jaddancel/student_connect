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
        sidebar_group: config.data.sidebar_group || [],
        system_function: config.data.system_function || '',
        is_active: config.data.is_active ?? true,
        is_published: config.data.is_published ?? false,
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

        // --- field creation ---
        addField(type) {
            const key = this.uniqueKey(type);
            const f = {
                field_key: key,
                field_label: this.labelFor(type),
                field_type: type,
                is_required: false,
                placeholder_hint: '',
                field_options: this.defaultOptions(type),
                universal_key: '',
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

        uniqueKey(type) {
            const base = type.replace(/[^a-z0-9]+/gi, '_');
            let i = 1;
            let key = `${base}_${i}`;
            const taken = new Set(this.fields.map((f) => f.field_key));
            while (taken.has(key)) {
                i += 1;
                key = `${base}_${i}`;
            }
            return key;
        },

        removeField(key) {
            this.fields = this.fields.filter((f) => f.field_key !== key);
            this.rows.forEach((row) => {
                row.columns.forEach((col) => {
                    col.fields = col.fields.filter((k) => k !== key);
                });
            });
            this.rows = this.rows.filter((row) => row.columns.some((c) => c.fields.length));
            if (this.selectedKey === key) this.selectedKey = null;
            this.$nextTick(() => this.wireSortables());
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

            // Require a printed template before a form may be published.
            if (this.is_published && !this.hasTemplate) {
                this.step = 2;
                this.error = 'Add a printed PDF template (Step 2) before publishing this form.';
                return;
            }

            this.saving = true;
            const payload = {
                name: this.name,
                description_text: this.description_text,
                route_name: this.route_name,
                sidebar_group: this.sidebar_group,
                system_function: this.system_function || null,
                is_active: this.is_active,
                is_published: this.is_published,
                fields: this.fields,
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
