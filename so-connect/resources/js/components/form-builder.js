import Sortable from 'sortablejs';

/**
 * Alpine component backing the WYSIWYG form-builder editor.
 *
 * The `rows`/`fields` model is the single source of truth. Rows are reordered
 * by explicit ▲▼ buttons only — rows are never draggable. Fields are reordered
 * by dragging the field card (within its column, across columns, or into a
 * different row), driven by SortableJS over each column's field list.
 */
export function formBuilder(config) {
    return {
        // --- config from server ---
        catalog: config.catalog || {},
        // Registered dynamic option sources: [{ key, label, searchable }].
        optionSources: config.optionSources || [],
        // New Events form fields offered as Activity-Table columns:
        // [{ key, label, type, type_label }]. Empty unless this is the workplan form.
        eventFieldChoices: config.eventFieldChoices || [],
        // The form's field kit (e.g. 'sign_up'), used to gate kit-only autofills.
        kit: config.kit || '',
        storeUrl: config.storeUrl,
        updateUrl: config.updateUrl,
        uploadUrl: config.uploadUrl,
        signatorySearchUrl: config.signatorySearchUrl,
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

        // --- signature expected-signer picker ---
        // Mirrors App\Forms\FieldType::POSITION_OPTIONS.
        expectedPositionChoices: ['President', 'Treasurer', 'Auditor', 'Secretary', 'Others'],
        signatoryQuery: '',
        signatoryResults: [],

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
            if (type === 'activity-table') return { columns: [] };
            if (type === 'table-input') {
                return {
                    columns: [{ key: 'column_1', label: 'Column 1', type: 'text', required: false }],
                    row_total: { key: '', label: 'Total', multiply: [] },
                };
            }
            return {};
        },

        /**
         * Switch a field to a different type from the settings panel. The
         * type-specific `field_options` no longer apply, so they're reset to the
         * new type's defaults — but the field's own visibility condition rides
         * along, since it describes when the field shows, not what it is.
         */
        changeFieldType(f, newType) {
            if (!f || !newType || newType === f.field_type) return;
            const keepVisible = f.field_options && f.field_options.visible_when;
            f.field_type = newType;
            f.field_options = this.defaultOptions(newType);
            if (keepVisible) f.field_options.visible_when = keepVisible;
            // Value-less layout types (heading/static-text) can't autofill or be
            // required, so drop mappings that would now be meaningless.
            if (['heading', 'static-text'].includes(newType)) {
                f.universal_key = '';
                f.is_required = false;
            }
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
            // A row left empty is KEPT: empty rows/columns are layout the admin
            // created ("+ Empty row", the column buttons) and drag targets they
            // still need. "remove row" is how a row goes away.
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
        /**
         * Change how many columns a row has WITHOUT moving the fields already
         * in it — the buttons arrange columns, dragging arranges fields.
         * Growing adds empty columns to drag into; shrinking folds the dropped
         * columns' fields into the last surviving one so nothing is lost.
         */
        setColumnCount(rowIndex, count) {
            const row = this.rows[rowIndex];
            if (!row || count === row.columns.length) return;
            const span = Math.floor(12 / count);

            if (count > row.columns.length) {
                while (row.columns.length < count) row.columns.push({ span, fields: [] });
            } else {
                const dropped = row.columns.slice(count).flatMap((c) => c.fields);
                row.columns = row.columns.slice(0, count);
                row.columns[count - 1].fields.push(...dropped);
            }

            row.columns.forEach((c) => { c.span = span; });
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

        // --- row reordering (buttons only — rows are never draggable) ---
        /** Move a whole row up (dir=-1) or down (dir=+1), clamped to bounds. */
        moveRow(rowIndex, dir) {
            const target = rowIndex + dir;
            if (target < 0 || target >= this.rows.length) return;
            const [moved] = this.rows.splice(rowIndex, 1);
            this.rows.splice(target, 0, moved);
        },

        // --- field reordering (SortableJS drag) ---
        /**
         * Attach SortableJS to one column's field list. Called from the
         * column's x-init, so every column Alpine renders — including ones
         * added later — wires itself exactly once.
         *
         * The column's own `data-row`/`data-col` are Alpine-bound, so they stay
         * correct as rows are reordered or removed; onEnd reads them fresh
         * rather than closing over the indices at wire time.
         */
        wireColumn(el) {
            if (el._sortable) return;
            el._sortable = Sortable.create(el, {
                group: 'builder-fields',
                animation: 150,
                draggable: '[data-field-card]',
                // The ✕ button must stay clickable, not start a drag.
                filter: '[data-no-drag]',
                preventOnFilter: false,
                ghostClass: 'opacity-40',
                onEnd: (evt) => this.onFieldDrop(evt),
            });
        },

        /**
         * Apply a completed drag to the model. SortableJS has already moved the
         * DOM node, but Alpine's x-for owns that DOM — so undo the physical
         * move first and let Alpine re-render from the mutated model, keeping
         * the model the single source of truth.
         */
        onFieldDrop(evt) {
            const { item, from, to, oldIndex, newIndex } = evt;

            // Revert Sortable's DOM mutation (see above). Index against the
            // field cards only — a column's element children also include
            // Alpine's <template> anchors, so raw `children` would misplace it.
            to.removeChild(item);
            const cards = from.querySelectorAll(':scope > [data-field-card]');
            from.insertBefore(item, cards[oldIndex] || null);

            const fromRow = parseInt(from.dataset.row, 10);
            const fromCol = parseInt(from.dataset.col, 10);
            const toRow = parseInt(to.dataset.row, 10);
            const toCol = parseInt(to.dataset.col, 10);
            if ([fromRow, fromCol, toRow, toCol, oldIndex, newIndex].some(Number.isNaN)) return;

            const source = this.rows[fromRow]?.columns[fromCol]?.fields;
            const target = this.rows[toRow]?.columns[toCol]?.fields;
            if (!source || !target) return;
            if (source === target && oldIndex === newIndex) return;

            const [moved] = source.splice(oldIndex, 1);
            if (moved === undefined) return;
            target.splice(newIndex, 0, moved);
            // A row emptied by the drag is KEPT — see removeField().
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
                    { value: 'current_semester', label: 'Current Semester' },
                ],
                date: [{ value: 'current_date', label: 'Current Date' }],
                time: [{ value: 'current_time', label: 'Time' }],
                datetime: [{ value: 'current_datetime', label: 'Date/Time' }],
                number: [{ value: 'current_year', label: 'Year' }],
                age: [{ value: 'current_year', label: 'Year' }],
            };
            return byType[type] || [];
        },
        /**
         * "Computed" autofill options derived from a sibling field on this form.
         * Unique to the Sign Up builder: `age_from_birthday` computes the age
         * from the form's own birthday date field. Offered only on number-ish
         * fields (age/number/text).
         */
        derivedAutofillOptions(type) {
            if (this.kit !== 'sign_up') return [];
            if (!['age', 'number', 'text'].includes(type)) return [];
            return [{ value: 'age_from_birthday', label: 'Age - Computed from Birthday' }];
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

        // --- activity-table columns (chosen from the New Events form's fields) ---
        /** Whether the field already includes a column for this New Events field. */
        activityColumnChecked(f, choice) {
            return (f.field_options.columns || []).some((c) => c.key === choice.key);
        },
        /**
         * Toggle a New Events field in/out of the activity table's columns. Newly
         * checked columns append in selection order, snapshotting label + type so
         * a later change to the source field still prints its old heading.
         */
        toggleActivityColumn(f, choice) {
            if (!f.field_options.columns) f.field_options.columns = [];
            const i = f.field_options.columns.findIndex((c) => c.key === choice.key);
            if (i === -1) {
                f.field_options.columns.push({ key: choice.key, label: choice.label, type: choice.type });
            } else {
                f.field_options.columns.splice(i, 1);
            }
        },
        /** Stored columns whose source field is no longer on the New Events form. */
        staleActivityColumns(f) {
            const live = new Set(this.eventFieldChoices.map((c) => c.key));
            return (f.field_options.columns || []).filter((c) => !live.has(c.key));
        },
        /**
         * A column's type label: the live New Events field's label when the field
         * still exists, else the FieldType catalog label for the stored snapshot.
         */
        columnTypeLabel(col) {
            const live = this.eventFieldChoices.find((c) => c.key === col.key);
            if (live) return live.type_label;
            return this.labelFor(col.type);
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

        // --- signature expected-signer picker ---
        // Whether the field has any expected signer configured (position or person).
        hasExpectedSigner(f) {
            const positions = f.field_options.expected_positions || [];
            const profiles = f.field_options.expected_profiles || [];
            return positions.length > 0 || profiles.length > 0;
        },
        hasExpectedPosition(f, pos) {
            return (f.field_options.expected_positions || []).includes(pos);
        },
        toggleExpectedPosition(f, pos) {
            if (!f.field_options.expected_positions) f.field_options.expected_positions = [];
            const list = f.field_options.expected_positions;
            const i = list.indexOf(pos);
            if (i === -1) list.push(pos);
            else list.splice(i, 1);
            this.syncCompareMode(f);
        },
        async searchExpectedPeople() {
            const q = this.signatoryQuery.trim();
            if (!this.signatorySearchUrl) return;
            try {
                const url = new URL(this.signatorySearchUrl, window.location.origin);
                url.searchParams.set('q', q);
                const res = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!res.ok) { this.signatoryResults = []; return; }
                const json = await res.json();
                this.signatoryResults = json.data || [];
            } catch (e) {
                this.signatoryResults = [];
            }
        },
        addExpectedPerson(f, person) {
            if (!f.field_options.expected_profiles) f.field_options.expected_profiles = [];
            if (!f.field_options.expected_people) f.field_options.expected_people = [];
            const id = Number(person.profile_id);
            if (!f.field_options.expected_profiles.includes(id)) {
                f.field_options.expected_profiles.push(id);
                // Companion display list (name + role) so chips render without a lookup.
                f.field_options.expected_people.push({ id, name: person.name, org_role: person.org_role || null });
            }
            this.signatoryQuery = '';
            this.signatoryResults = [];
            this.syncCompareMode(f);
        },
        removeExpectedPerson(f, id) {
            id = Number(id);
            f.field_options.expected_profiles = (f.field_options.expected_profiles || []).filter((x) => Number(x) !== id);
            f.field_options.expected_people = (f.field_options.expected_people || []).filter((p) => Number(p.id) !== id);
            this.syncCompareMode(f);
        },
        // An expected signer implies Compare mode by default; the admin can still
        // uncheck it. Once they've explicitly chosen a mode, we don't override it.
        syncCompareMode(f) {
            if (this.hasExpectedSigner(f)) {
                if (f.field_options.match_mode !== 'normal') f.field_options.match_mode = 'compare';
            } else if (f.field_options.match_mode === 'compare') {
                f.field_options.match_mode = 'normal';
            }
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
