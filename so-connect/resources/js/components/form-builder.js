import Sortable from "sortablejs";

/**
 * Alpine component backing the WYSIWYG form-builder editor.
 *
 * The `rows`/`fields` model is the single source of truth. Rows are reordered
 * by dragging a row's handle (top-level reorder only — a row can never be
 * dropped into a column; rows and fields drag in separate SortableJS groups
 * so the two hierarchies can't cross). Fields are reordered by dragging the
 * field card (within its column, across columns, or into a different row),
 * driven by SortableJS over each column's field list.
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
        kit: config.kit || "",
        storeUrl: config.storeUrl,
        updateUrl: config.updateUrl,
        uploadUrl: config.uploadUrl,
        signatorySearchUrl: config.signatorySearchUrl,
        csrf: config.csrf,
        isEdit: config.isEdit,
        // Printed-template draft (Step 2). syncDraft() snapshots the current
        // fields into non-persistent storage so the editor's token palette
        // reflects unsaved fields; printEnabled gates it on OnlyOffice being
        // configured. draftId is server-minted on first sync and echoed back on
        // save so the draft can be folded into the real template.
        draftSyncUrl: config.draftSyncUrl || "",
        formId: config.formId || null,
        printEnabled: !!config.printEnabled,
        draftId: null,
        draftSyncing: false,
        draftError: "",

        // --- model ---
        name: config.data.name || "",
        description_text: config.data.description_text || "",
        route_name: config.data.route_name || "",
        system_function: config.data.system_function || "",
        // Sidebar icon key (see MenuHelper::iconNames); '' falls back to the
        // default forms glyph.
        icon: config.data.icon || "",
        fields: config.data.fields || [],
        rows: config.data.rows || [],
        // The letterhead (header) and footer belong to the printed document, so
        // they live under pdf_template alongside the rich-text body and page setup.
        pdf_template: Object.assign(
            {
                html: "",
                page: { size: "a4", orientation: "portrait" },
                font: {
                    family: "'Times New Roman', Times, serif",
                    size: "12px",
                },
                header: { align: "center" },
                footer: {},
            },
            config.data.pdf_template || {},
        ),

        // --- wizard / ui state ---
        step: 1,
        selectedKey: null,
        // Currently selected row, tracked by its client-only stable `_id` (not
        // an index, so reordering/removing rows can't misdirect the selection).
        selectedRow: null,
        routeTouched: false,
        saving: false,
        message: "",
        error: "",

        // --- signature expected-signer picker ---
        // Mirrors App\Forms\FieldType::POSITION_OPTIONS.
        expectedPositionChoices: [
            "President",
            "Treasurer",
            "Auditor",
            "Secretary",
            "Others",
        ],
        signatoryQuery: "",
        signatoryResults: [],

        init() {
            // Keys of already-saved fields are frozen: syncFields() upserts by
            // (form_id, field_key), so renaming one would prune the row and
            // orphan its submissions and scoring variables.
            this.fields.forEach((f) => {
                f._keyLocked = true;
            });

            // Legacy forms stored "Section"/"Static text" as fields inside a
            // column; they're now row properties. Hoist them onto their row and
            // drop the fields so the builder works with a single, clean model.
            this.migrateLegacyLayoutFields();
            // Every row needs a stable id for selection (see selectedRow).
            this.rows.forEach((row) => {
                if (!row._id) row._id = this.newId();
            });

            if (!this.isEdit) {
                // The real name is entered in Step 3, but name (and its derived
                // route) are required on save. Seed a temporary name up front —
                // the bound system function's name, else the current timestamp —
                // so saving/advancing before Step 3 isn't blocked. The user can
                // still overwrite it in Step 3 (the route follows along).
                if (!this.name) {
                    this.name = this.system_function
                        ? this.titleCase(this.system_function)
                        : `Untitled form ${this.timestampTag()}`;
                    this.route_name = this.slug(this.name);
                }

                // Auto-slug the route name from the title while creating.
                this.$watch("name", (v) => {
                    if (!this.routeTouched) this.route_name = this.slug(v);
                });
            }
        },

        slug(v) {
            return (v || "")
                .toString()
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, "-")
                .replace(/^-+|-+$/g, "");
        },

        /** "new_event" → "New Event" for a friendly temporary form name. */
        titleCase(key) {
            return (key || "")
                .toString()
                .replace(/[_-]+/g, " ")
                .trim()
                .replace(/\b\w/g, (c) => c.toUpperCase());
        },

        /** Local "YYYY-MM-DD HH:MM:SS" tag, unique enough for a temp name/route. */
        timestampTag() {
            const d = new Date();
            const p = (n) => String(n).padStart(2, "0");
            return (
                `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}` +
                ` ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`
            );
        },

        // --- wizard navigation ---
        get hasTemplate() {
            return (
                (this.pdf_template.html || "").replace(/<[^>]*>/g, "").trim()
                    .length > 0 ||
                /data-field=/.test(this.pdf_template.html || "")
            );
        },

        async goToStep(n) {
            this.error = "";
            if (n === 2 && this.fields.length === 0) {
                this.error = "Add at least one field in Step 1 first.";
                return;
            }
            // Entering Step 2: snapshot the current fields into a draft so the
            // printed-template editor's token palette includes unsaved fields.
            // Re-entry re-syncs (fresh tokens). Bail on sync failure so we don't
            // show the step with a dead editor. Skipped entirely when OnlyOffice
            // isn't configured — the component then renders its "editor
            // unavailable" notice instead.
            if (n === 2 && this.printEnabled) {
                const detail = await this.syncDraft();
                if (!detail) return;
                this.step = 2;
                // Boot (or reboot) the editor only after the step is shown, so
                // OnlyOffice never initialises into a hidden, zero-size surface.
                window.dispatchEvent(
                    new CustomEvent("printed-template:sync", { detail }),
                );
                return;
            }
            this.step = Math.max(1, Math.min(3, n));
        },

        nextStep() {
            this.goToStep(this.step + 1);
        },
        prevStep() {
            this.goToStep(this.step - 1);
        },

        /**
         * Snapshot the builder's fields into a non-persistent draft. Returns the
         * editor's { configUrl, importUrl } on success, or false on failure. The
         * draft never touches the database — it lives in the file cache until
         * the form is saved (then folded into the real template).
         */
        async syncDraft() {
            if (!this.draftSyncUrl) return false;
            this.draftSyncing = true;
            this.draftError = "";
            try {
                const res = await fetch(this.draftSyncUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": this.csrf,
                        Accept: "application/json",
                    },
                    body: JSON.stringify({
                        draft_id: this.draftId,
                        form_id: this.formId,
                        name: this.name,
                        fields: this.fields.map(
                            ({ _keyLocked, ...field }) => field,
                        ),
                    }),
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.draftError =
                        json.message ||
                        "Could not prepare the printed template.";
                    this.error = this.draftError;
                    return false;
                }
                this.draftId = json.draftId;
                return { configUrl: json.configUrl, importUrl: json.importUrl };
            } catch (e) {
                this.draftError = "Could not prepare the printed template.";
                this.error = this.draftError;
                return false;
            } finally {
                this.draftSyncing = false;
            }
        },

        field(key) {
            return this.fields.find((f) => f.field_key === key) || null;
        },

        labelFor(type) {
            return (this.catalog[type] && this.catalog[type].label) || type;
        },

        // --- row identity & selection ---
        /** Short, collision-unlikely client-only id for a row. */
        newId() {
            return `r_${Math.random().toString(36).slice(2, 9)}`;
        },
        rowById(id) {
            return this.rows.find((r) => r._id === id) || null;
        },
        /** Select a row (and clear any field selection) to edit its settings. */
        selectRow(row) {
            this.selectedRow = row._id;
            this.selectedKey = null;
        },
        /** Select a field (and clear any row selection) to edit its settings. */
        selectKey(key) {
            this.selectedKey = key;
            this.selectedRow = null;
        },

        /**
         * One-time migration for forms saved before header/static-text became
         * row properties: hoist the first heading field's label into row.header
         * and the first static-text field's content into row.static_text, then
         * drop those fields from the row and the model. Extra presentational
         * fields in the same row are dropped too (rare — each was historically
         * added in its own full-width row).
         */
        migrateLegacyLayoutFields() {
            const dropped = new Set();
            this.rows.forEach((row) => {
                row.columns.forEach((col) => {
                    col.fields = col.fields.filter((key) => {
                        const f = this.field(key);
                        if (!f) return true;
                        if (f.field_type === "heading") {
                            if (!row.header) row.header = f.field_label || "";
                            dropped.add(key);
                            return false;
                        }
                        if (f.field_type === "static-text") {
                            if (!row.static_text) {
                                row.static_text =
                                    (f.field_options &&
                                        f.field_options.content) ||
                                    f.field_label ||
                                    "";
                            }
                            dropped.add(key);
                            return false;
                        }
                        return true;
                    });
                });
            });
            if (dropped.size) {
                this.fields = this.fields.filter(
                    (f) => !dropped.has(f.field_key),
                );
            }
        },

        // --- palette sections ---
        get fieldPalette() {
            return Object.fromEntries(
                Object.entries(this.catalog).filter(
                    ([, meta]) =>
                        meta.group !== "layout" && meta.group !== "special",
                ),
            );
        },

        get specialPalette() {
            return Object.fromEntries(
                Object.entries(this.catalog).filter(
                    ([type, meta]) =>
                        meta.group === "special" &&
                        this.isPaletteTypeAvailable(type),
                ),
            );
        },

        get hasSpecialPalette() {
            return Object.keys(this.specialPalette).length > 0;
        },

        /** Only the proposed president is unique on the organization form. */
        isPaletteTypeAvailable(type) {
            if (type === "new-president-email") {
                return !this.fields.some((field) => field.field_type === type);
            }

            return (
                type !== "new-officer-email" ||
                this.kit !== "sign_up" ||
                !this.fields.some((field) => field.field_type === type)
            );
        },

        // --- field creation ---
        /** Build a field object (not yet placed in a row) for `type`. */
        makeField(type) {
            const label = this.labelFor(type);
            const fixedKey = this.fixedFieldKey(type);
            return {
                field_key: fixedKey || this.keyFromLabel(label),
                field_label: label,
                field_type: type,
                is_required: false,
                placeholder_hint: "",
                field_options: this.defaultOptions(type),
                universal_key: "",
                // Kit-required email controls retain their contract key even if
                // an admin adjusts the field label for display.
                _keyLocked: Boolean(fixedKey),
            };
        },

        /** The field-kit keys required for role-defining email controls. */
        fixedFieldKey(type) {
            if (type === "new-officer-email") {
                return this.kit === "sign_up"
                    ? "email"
                    : this.kit === "new_organization_registration"
                      ? this.keyFromLabel("officer_email")
                      : null;
            }

            return type === "new-president-email" &&
                this.kit === "new_organization_registration"
                ? "president_email"
                : null;
        },
        /** Insert a row (stamping its id) at `index`, or append when null. */
        insertRow(row, index = null) {
            if (!row._id) row._id = this.newId();
            if (index === null || index >= this.rows.length)
                this.rows.push(row);
            else this.rows.splice(Math.max(0, index), 0, row);
            return row;
        },
        /** Add a new field in its own full-width row at `index` (append if null). */
        addFieldAtRow(type, index = null) {
            const f = this.makeField(type);
            this.fields.push(f);
            this.insertRow(
                { columns: [{ span: 12, fields: [f.field_key] }] },
                index,
            );
            this.selectKey(f.field_key);
        },
        /** Palette click-to-add: append a new field row at the end. */
        addField(type) {
            this.addFieldAtRow(type, null);
        },

        defaultOptions(type) {
            // A checkbox is a single yes/no checkmark — it carries no option list.
            if (["select", "radio"].includes(type)) {
                return { options: [{ value: "option_1", label: "Option 1" }] };
            }
            // The search field is always sourced — default it to the first
            // registered source so it renders something out of the box.
            if (type === "search") {
                return { source: (this.optionSources[0] || {}).key || "" };
            }
            if (type === "age") return { min: 0, max: 150, step: 1 };
            if (type === "static-text") return { content: "Static text…" };
            if (type === "password") return { min: 8 };
            if (type === "multi-image") return { max_files: 5 };
            if (type === "computed") return { formula: "sum", args: [] };
            if (type === "activity-table") return { columns: [] };
            if (type === "table-input") {
                return {
                    columns: [
                        {
                            key: "column_1",
                            label: "Column 1",
                            type: "text",
                            required: false,
                        },
                    ],
                    row_total: { key: "", label: "Total", multiply: [] },
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
            if (["heading", "static-text"].includes(newType)) {
                f.universal_key = "";
                f.is_required = false;
            }
        },

        /**
         * Auto-generate a field key from its label ("Event Title" →
         * `event_title`), unique within this form (`_2`, `_3`… on collision).
         */
        keyFromLabel(label, excludeKey = null) {
            const base =
                (label || "")
                    .toString()
                    .toLowerCase()
                    .trim()
                    .replace(/[^a-z0-9]+/g, "_")
                    .replace(/^_+|_+$/g, "") || "field";
            const taken = new Set(
                this.fields
                    .filter((f) => f.field_key !== excludeKey)
                    .map((f) => f.field_key),
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
                    col.fields = col.fields.map((k) =>
                        k === oldKey ? newKey : k,
                    );
                });
            });
            this.fields.forEach((other) => {
                if (
                    other.field_options &&
                    other.field_options.visible_when &&
                    other.field_options.visible_when.field === oldKey
                ) {
                    other.field_options.visible_when.field = newKey;
                }
            });
            if (this.selectedKey === oldKey) this.selectedKey = newKey;
        },

        removeField(key) {
            // Note which row held the field so we can prune it if it empties.
            const host = this.rows.find((r) =>
                r.columns.some((c) => c.fields.includes(key)),
            );
            this.fields = this.fields.filter((f) => f.field_key !== key);
            this.rows.forEach((row) => {
                row.columns.forEach((col) => {
                    col.fields = col.fields.filter((k) => k !== key);
                });
            });
            // Removing the last field of a row removes the row itself.
            if (host) this.pruneRowIfFieldless(host);
            // Conditions pointing at the removed field would dangle — drop them.
            this.fields.forEach((f) => {
                if (
                    f.field_options &&
                    f.field_options.visible_when &&
                    f.field_options.visible_when.field === key
                ) {
                    delete f.field_options.visible_when;
                }
            });
            if (this.selectedKey === key) this.selectedKey = null;
        },

        /**
         * Remove `row` when it no longer holds any field. Triggered only when a
         * field leaves a row (drag-out or delete): the row (and any header/static
         * text it carried) goes away with its last field. A row that never held a
         * field — e.g. a header-only section divider — is never passed here, so it
         * survives until explicitly removed.
         */
        pruneRowIfFieldless(row) {
            if (!row || row.columns.some((c) => c.fields.length > 0)) return;
            const i = this.rows.indexOf(row);
            if (i === -1) return;
            if (this.selectedRow === row._id) this.selectedRow = null;
            this.rows.splice(i, 1);
        },

        // --- conditional visibility ---
        setVisibilityMode(f, mode) {
            if (mode === "conditional") {
                if (!f.field_options.visible_when) {
                    f.field_options.visible_when = {
                        field: "",
                        op: "equals",
                        value: "",
                    };
                }
            } else if (f.field_options.visible_when) {
                delete f.field_options.visible_when;
            }
        },
        /** Keys of the fields sharing a row with the given field (self excluded). */
        rowSiblingKeys(fieldKey) {
            const row = this.rows.find((r) =>
                r.columns.some((c) => c.fields.includes(fieldKey)),
            );
            if (!row) return [];
            return row.columns
                .flatMap((c) => c.fields)
                .filter((k) => k !== fieldKey);
        },
        /**
         * Fields that may control a condition: they must sit in the same row as
         * the dependent field (a field can only react to its row-mates) and
         * carry a value (layout/upload/signature controls are excluded).
         */
        conditionSources(exceptKey) {
            const siblings = new Set(this.rowSiblingKeys(exceptKey));
            return this.fields.filter(
                (f) =>
                    siblings.has(f.field_key) &&
                    ![
                        "heading",
                        "static-text",
                        "image",
                        "file",
                        "signature",
                    ].includes(f.field_type),
            );
        },
        conditionController(f) {
            const key =
                f.field_options.visible_when &&
                f.field_options.visible_when.field;
            return key ? this.field(key) : null;
        },
        needsConditionValue(f) {
            const op =
                f.field_options.visible_when && f.field_options.visible_when.op;
            return !!op && !["filled", "empty"].includes(op);
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
                while (row.columns.length < count)
                    row.columns.push({ span, fields: [] });
            } else {
                const dropped = row.columns
                    .slice(count)
                    .flatMap((c) => c.fields);
                row.columns = row.columns.slice(0, count);
                row.columns[count - 1].fields.push(...dropped);
            }

            row.columns.forEach((c) => {
                c.span = span;
            });
        },

        removeRow(rowIndex) {
            const row = this.rows[rowIndex];
            if (!row) return;
            const keys = row.columns.flatMap((c) => c.fields);
            this.fields = this.fields.filter(
                (f) => !keys.includes(f.field_key),
            );
            if (this.selectedRow === row._id) this.selectedRow = null;
            this.rows.splice(rowIndex, 1);
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
                // Accepts field cards (own group, for reordering/moving) and new
                // fields dragged from the palette ('builder-new') dropped straight
                // into a populated column; onAdd creates the field there.
                group: {
                    name: "builder-fields",
                    put: ["builder-fields", "builder-new"],
                },
                animation: 150,
                draggable: "[data-field-card]",
                // The ✕ button must stay clickable, not start a drag.
                filter: "[data-no-drag]",
                preventOnFilter: false,
                ghostClass: "opacity-40",
                // Native HTML5 DnD brings the browser's own page-level
                // autoscroll along for the ride, fighting the canvas's own
                // scroll. forceFallback swaps to Sortable's mouse-simulated
                // drag (no native DnD), so only the scroll config below runs.
                forceFallback: true,
                scroll: true,
                scrollSensitivity: 120,
                scrollSpeed: 25,
                bubbleScroll: false,
                scrollFn: (dx, dy, evt, touchEvt, target) =>
                    this.redirectAutoScroll(el, dx, dy, target),
                onStart: () => this.ignoreGhost(),
                onEnd: (evt) => this.onFieldDrop(evt),
                onAdd: (evt) => this.onColumnAdd(evt),
            });
        },

        /**
         * forceFallback drags by cloning the dragged element into a floating
         * "ghost" that follows the pointer (Sortable.ghost) — a raw DOM clone,
         * carrying over Alpine directive attributes (x-for, x-text, :class…)
         * as inert text, not live bindings. Alpine's mutation observer still
         * notices it landing in the document and tries to evaluate those
         * attributes against the clone, which has none of the loop scope
         * (row, key, col…) they depend on — hence "row is not defined" etc.
         * Tagging the ghost x-ignore, synchronously in onStart (before
         * Alpine's observer gets its microtask turn), keeps Alpine off it.
         */
        ignoreGhost() {
            if (Sortable.ghost) Sortable.ghost.setAttribute("x-ignore", "");
        },

        /**
         * SortableJS resolves its autoscroll target from whatever DOM element
         * is literally under the pointer, not from the dragged item's own
         * container — so once the pointer strays outside the canvas panel
         * (near the true top/bottom of the browser window), nothing
         * scrollable sits between that point and <body>, and it falls back
         * to scrolling the whole page (bubbleScroll:false only stops it
         * chaining *past* a found target — it doesn't stop this fallback).
         *
         * The canvas panel (data-canvas-scroll) is the only surface meant to
         * scroll during a drag on desktop, where it's independently
         * `overflow-y-auto` (see editor.blade.php). So: whenever Sortable
         * would have scrolled the page, redirect that scroll into the canvas
         * instead. On mobile the canvas isn't bounded (no `lg:overflow-y-
         * auto`), so its scrollHeight never exceeds its clientHeight and this
         * defers to the default (page scroll), which is the only option there.
         */
        redirectAutoScroll(el, dx, dy, target) {
            if (
                target !== document.scrollingElement &&
                target !== document.documentElement
            )
                return "continue";
            const canvas = el.closest("[data-canvas-scroll]");
            if (!canvas || canvas.scrollHeight <= canvas.clientHeight)
                return "continue";
            canvas.scrollTop += dy;
            canvas.scrollLeft += dx;
            return "stop"; // any non-'continue' value suppresses the default scrollBy(target, …)
        },

        /**
         * Apply a completed drag to the model. SortableJS has already moved the
         * DOM node, but Alpine's x-for owns that DOM — so undo the physical
         * move first and let Alpine re-render from the mutated model, keeping
         * the model the single source of truth.
         */
        onFieldDrop(evt) {
            const { item, from, to, oldIndex, newIndex } = evt;

            // Dropped onto the canvas (outside any column) → onCanvasAdd turns it
            // into a new row. Leave the DOM/model to that handler and bail here.
            if (to.hasAttribute && to.hasAttribute("data-rows-root")) return;

            // Revert Sortable's DOM mutation (see above). Index against the
            // field cards only — a column's element children also include
            // Alpine's <template> anchors, so raw `children` would misplace it.
            to.removeChild(item);
            const cards = from.querySelectorAll(":scope > [data-field-card]");
            from.insertBefore(item, cards[oldIndex] || null);

            const fromRow = parseInt(from.dataset.row, 10);
            const fromCol = parseInt(from.dataset.col, 10);
            const toRow = parseInt(to.dataset.row, 10);
            const toCol = parseInt(to.dataset.col, 10);
            if (
                [fromRow, fromCol, toRow, toCol, oldIndex, newIndex].some(
                    Number.isNaN,
                )
            )
                return;

            const sourceRow = this.rows[fromRow];
            const source = sourceRow?.columns[fromCol]?.fields;
            const target = this.rows[toRow]?.columns[toCol]?.fields;
            if (!source || !target) return;
            if (source === target && oldIndex === newIndex) return;

            const [moved] = source.splice(oldIndex, 1);
            if (moved === undefined) return;
            target.splice(newIndex, 0, moved);
            // Moving the last field out of a row removes that row (and any
            // header/static text it carried) — see pruneRowIfFieldless().
            if (fromRow !== toRow) this.pruneRowIfFieldless(sourceRow);
        },

        // --- row reordering (SortableJS drag) ---
        /**
         * Attach SortableJS to the row list once (the container itself only
         * ever renders once, so there's no per-row re-wiring the way
         * wireColumn() has to handle new columns).
         *
         * A distinct `group` from wireColumn()'s 'builder-fields' means a row
         * can never be shown as droppable into a column, and a field card can
         * never be shown as droppable into the row list — SortableJS only
         * allows drags across instances that share a group name. `handle`
         * further restricts drag-start to the row's own ⠿ icon, so the
         * column-count buttons, "remove row", and nested field cards never
         * accidentally start a row drag.
         */
        wireRows(el) {
            if (el._sortable) return;
            el._sortable = Sortable.create(el, {
                // Accepts row reorders (own group), new fields from the palette
                // ('builder-new'), and field cards dragged out of a column
                // ('builder-fields'); onAdd turns either drop into a new row at
                // the drop position (SortableJS shows the placeholder gap so the
                // admin sees where it will land).
                group: {
                    name: "builder-rows",
                    put: ["builder-new", "builder-fields"],
                },
                animation: 150,
                handle: "[data-row-handle]",
                draggable: "[data-row-item]",
                ghostClass: "opacity-40",
                // See wireColumn()'s forceFallback/ignoreGhost notes — same
                // reasoning applies here, and rows have even more nested
                // Alpine scope (columns, fields) for the clone to trip over.
                forceFallback: true,
                scroll: true,
                scrollSensitivity: 120,
                scrollSpeed: 25,
                bubbleScroll: false,
                // See wireColumn()'s redirectAutoScroll note — same fix applies here.
                scrollFn: (dx, dy, evt, touchEvt, target) =>
                    this.redirectAutoScroll(el, dx, dy, target),
                onStart: () => this.ignoreGhost(),
                onEnd: (evt) => this.onRowDrop(evt),
                onAdd: (evt) => this.onCanvasAdd(evt),
            });
        },

        /**
         * Attach SortableJS to a palette section so its buttons can be dragged
         * onto the canvas. Items are clones (pull:'clone', put:false) and the
         * palette itself never reorders (sort:false) — the drag only produces a
         * new field/row via the rows list's onAdd (onPaletteDrop).
         */
        wirePalette(el) {
            if (el._sortable) return;
            el._sortable = Sortable.create(el, {
                group: { name: "builder-new", pull: "clone", put: false },
                sort: false,
                draggable: "[data-palette-item]",
                ghostClass: "opacity-40",
                forceFallback: true,
                onStart: () => this.ignoreGhost(),
                // pull:'clone' drags the *original* button into the canvas (it's
                // evt.item in the drop handlers, which removeChild it) and leaves
                // a raw clone behind. This container is owned by Alpine's x-for,
                // which — since the catalog never changes — never re-renders to
                // heal that, so a used field type would vanish from the palette.
                // Mirror the row/field drag pattern: revert Sortable's mutation
                // so Alpine's DOM stays canonical — drop the clone, put the
                // original button back in its slot.
                onEnd: (evt) => this.restorePalette(evt),
            });
        },

        /**
         * Undo SortableJS's DOM changes to the palette after a drag: remove the
         * clone Sortable inserted and re-seat the original button at its slot,
         * so every field type stays draggable no matter how often it's used.
         */
        restorePalette(evt) {
            const { item, from, oldIndex, clone } = evt;
            if (clone && clone.parentNode) clone.parentNode.removeChild(clone);
            if (!this.isPaletteTypeAvailable(item.dataset.fieldType)) {
                if (item.parentNode) item.parentNode.removeChild(item);
                return;
            }
            const items = () =>
                from.querySelectorAll(":scope > [data-palette-item]");
            const inPlace =
                item.parentNode === from && items()[oldIndex] === item;
            if (inPlace) return;
            if (item.parentNode) item.parentNode.removeChild(item);
            from.insertBefore(item, items()[oldIndex] || null);
        },

        /**
         * Something was dropped onto the canvas (the rows list) from outside a
         * column — either a palette item (new field) or a field card dragged out
         * of a column. Undo Sortable's DOM insertion (Alpine's x-for owns this
         * DOM), work out where it landed among the real row wrappers, then place
         * it on its own new row there. Row reorders never reach here (same list).
         */
        onCanvasAdd(evt) {
            const { item, to } = evt;
            // Count real row wrappers preceding the dropped node → insert index.
            let index = 0;
            let node = item.previousElementSibling;
            while (node) {
                if (node.matches && node.matches("[data-row-item]")) index += 1;
                node = node.previousElementSibling;
            }

            // A field card dragged out of a column → move it onto a new row.
            if (item.matches("[data-field-card]")) {
                const key = item.dataset.fieldKey;
                to.removeChild(item);
                if (key) this.moveFieldToNewRow(key, index);
                return;
            }

            // A palette item → create a new field of that type on a new row.
            const type = item.dataset.fieldType;
            to.removeChild(item);
            if (type) this.addFieldAtRow(type, index);
        },

        /**
         * A palette item was dropped straight into a populated column. Field-card
         * moves between columns are owned by onFieldDrop, so ignore those here.
         */
        onColumnAdd(evt) {
            const { item, to } = evt;
            if (!item.matches("[data-palette-item]")) return;
            const type = item.dataset.fieldType;
            // Index among the column's existing field cards, before the clone.
            let index = 0;
            let node = item.previousElementSibling;
            while (node) {
                if (node.matches && node.matches("[data-field-card]"))
                    index += 1;
                node = node.previousElementSibling;
            }
            to.removeChild(item);
            const rowIndex = parseInt(to.dataset.row, 10);
            const colIndex = parseInt(to.dataset.col, 10);
            if (Number.isNaN(rowIndex) || Number.isNaN(colIndex) || !type)
                return;
            this.addFieldToColumn(type, rowIndex, colIndex, index);
        },

        /** Add a new field of `type` into an existing column at `index`. */
        addFieldToColumn(type, rowIndex, colIndex, index = null) {
            const col = this.rows[rowIndex]?.columns[colIndex];
            if (!col) return;
            const f = this.makeField(type);
            this.fields.push(f);
            const at =
                index === null
                    ? col.fields.length
                    : Math.max(0, Math.min(index, col.fields.length));
            col.fields.splice(at, 0, f.field_key);
            this.selectKey(f.field_key);
        },

        /** Move an existing field out of its column onto its own new row at `index`. */
        moveFieldToNewRow(key, index) {
            const sourceRow = this.rows.find((r) =>
                r.columns.some((c) => c.fields.includes(key)),
            );
            if (!sourceRow) return;
            sourceRow.columns.forEach((c) => {
                c.fields = c.fields.filter((k) => k !== key);
            });
            this.insertRow({ columns: [{ span: 12, fields: [key] }] }, index);
            // Moving the last field out of the source row removes it.
            this.pruneRowIfFieldless(sourceRow);
            this.selectKey(key);
        },

        /**
         * Apply a completed row drag to the model — mirrors onFieldDrop:
         * revert Sortable's DOM mutation first (Alpine's x-for owns this
         * DOM), then reorder `this.rows` and let Alpine re-render from it.
         */
        onRowDrop(evt) {
            const { item, from, oldIndex, newIndex } = evt;
            if (oldIndex === newIndex) return;

            // Index against row wrappers only — the container also holds
            // Alpine's <template> anchors, so raw `children` would misplace it.
            from.removeChild(item);
            const rowNodes = from.querySelectorAll(":scope > [data-row-item]");
            from.insertBefore(item, rowNodes[oldIndex] || null);

            const [moved] = this.rows.splice(oldIndex, 1);
            if (moved === undefined) return;
            this.rows.splice(newIndex, 0, moved);
        },

        // --- option editing (choice fields) ---
        addOption(key) {
            const f = this.field(key);
            if (!f.field_options.options) f.field_options.options = [];
            const n = f.field_options.options.length + 1;
            f.field_options.options.push({
                value: `option_${n}`,
                label: `Option ${n}`,
            });
        },
        removeOption(key, i) {
            const f = this.field(key);
            f.field_options.options.splice(i, 1);
        },
        isOptioned(type) {
            // Checkbox intentionally excluded: it is a single checkmark, not a
            // multi-option group, so the builder offers no option editor for it.
            return ["select", "radio"].includes(type);
        },

        /**
         * Predefined choices to offer for a visibility condition's comparison
         * value, driven by the controlling field `f` — mirrors the score-tally
         * editor's select-vs-text pattern. Returns:
         *   • select/radio with options, or a legacy option-group checkbox → its
         *     own {value,label} options;
         *   • a single checkbox (no options; submits "1" checked / "" unchecked)
         *     → Checked / Unchecked;
         *   • otherwise (non-optioned, or a select drawing from a dynamic source)
         *     → null, so the picker falls back to a free-text input.
         * @returns {Array<{value:string,label:string}>|null}
         */
        conditionValueChoices(f) {
            if (!f) return null;
            const opts = (f.field_options || {}).options || [];
            if (["select", "radio"].includes(f.field_type)) {
                // A select drawing from a registered source has no static choices.
                if (f.field_type === "select" && (f.field_options || {}).source)
                    return null;
                return opts.length ? opts : null;
            }
            if (f.field_type === "checkbox") {
                if (opts.length) return opts; // legacy option-group checkbox
                return [
                    { value: "1", label: "Checked" },
                    { value: "", label: "Unchecked" },
                ];
            }
            return null;
        },

        // --- dynamic option sources (registered DB-backed entries) ---
        /** Field types that can draw their choices from an OptionSource. */
        supportsSource(type) {
            return ["select", "search"].includes(type);
        },
        /**
         * Switch a select between hand-typed options and a registered source.
         * The search field is always sourced, so it never calls this.
         */
        setOptionMode(f, mode) {
            if (mode === "source") {
                if (!f.field_options.source) {
                    f.field_options.source =
                        (this.optionSources[0] || {}).key || "";
                }
            } else {
                delete f.field_options.source;
            }
        },
        supportsAutofillNow(type) {
            return ["date", "time", "datetime"].includes(type);
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
                    { value: "current_date", label: "Current Date" },
                    {
                        value: "current_school_year",
                        label: "Current School Year",
                    },
                    { value: "current_semester", label: "Current Semester" },
                ],
                date: [{ value: "current_date", label: "Current Date" }],
                time: [{ value: "current_time", label: "Time" }],
                datetime: [{ value: "current_datetime", label: "Date/Time" }],
                number: [{ value: "current_year", label: "Year" }],
                age: [{ value: "current_year", label: "Year" }],
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
            if (this.kit !== "sign_up") return [];
            if (!["age", "number", "text"].includes(type)) return [];
            return [
                {
                    value: "age_from_birthday",
                    label: "Age - Computed from Birthday",
                },
            ];
        },

        // --- table-input column editing ---
        slugColumn(label) {
            return (
                (label || "")
                    .toString()
                    .toLowerCase()
                    .trim()
                    .replace(/[^a-z0-9]+/g, "_")
                    .replace(/^_+|_+$/g, "") || "column"
            );
        },
        addColumn(key) {
            const f = this.field(key);
            if (!f.field_options.columns) f.field_options.columns = [];
            const n = f.field_options.columns.length + 1;
            f.field_options.columns.push({
                key: `column_${n}`,
                label: `Column ${n}`,
                type: "text",
                required: false,
            });
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
            return (f.field_options.columns || []).some(
                (c) => c.key === choice.key,
            );
        },
        /**
         * Toggle a New Events field in/out of the activity table's columns. Newly
         * checked columns append in selection order, snapshotting label + type so
         * a later change to the source field still prints its old heading.
         */
        toggleActivityColumn(f, choice) {
            if (!f.field_options.columns) f.field_options.columns = [];
            const i = f.field_options.columns.findIndex(
                (c) => c.key === choice.key,
            );
            if (i === -1) {
                f.field_options.columns.push({
                    key: choice.key,
                    label: choice.label,
                    type: choice.type,
                });
            } else {
                f.field_options.columns.splice(i, 1);
            }
        },
        /** Stored columns whose source field is no longer on the New Events form. */
        staleActivityColumns(f) {
            const live = new Set(this.eventFieldChoices.map((c) => c.key));
            return (f.field_options.columns || []).filter(
                (c) => !live.has(c.key),
            );
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
            f.field_options.args.push("");
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
                if (["number", "age", "computed"].includes(f.field_type)) {
                    out.push({ value: f.field_key, label: f.field_label });
                }
                if (f.field_type === "table-input") {
                    const rt = (f.field_options || {}).row_total || {};
                    if (rt.key)
                        out.push({
                            value: `${f.field_key}.${rt.key}`,
                            label: `${f.field_label} → ${rt.label || rt.key}`,
                        });
                    ((f.field_options || {}).columns || []).forEach((c) => {
                        if (c.type === "number")
                            out.push({
                                value: `${f.field_key}.${c.key}`,
                                label: `${f.field_label} → ${c.label}`,
                            });
                    });
                }
            });
            return out;
        },
        isNumeric(type) {
            return ["number", "age"].includes(type);
        },
        isFileLike(type) {
            return ["image", "file"].includes(type);
        },

        // --- upload accept (checkboxes over the server's hard allowlist) ---
        acceptChoices(type) {
            return type === "file"
                ? ["jpeg", "png", "heic", "pdf"]
                : ["jpeg", "png", "heic"];
        },
        acceptList(f) {
            return String(f.field_options.accept || "")
                .split(",")
                .map((e) => e.trim().replace(/^\./, "").toLowerCase())
                .map((e) => (e === "jpg" ? "jpeg" : e))
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
            list = list.includes(ext)
                ? list.filter((e) => e !== ext)
                : [...list, ext];
            list = choices.filter((e) => list.includes(e));
            f.field_options.accept =
                list.length === choices.length ? "" : list.join(",");
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
            if (!f.field_options.expected_positions)
                f.field_options.expected_positions = [];
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
                const url = new URL(
                    this.signatorySearchUrl,
                    window.location.origin,
                );
                url.searchParams.set("q", q);
                const res = await fetch(url, {
                    headers: { Accept: "application/json" },
                });
                if (!res.ok) {
                    this.signatoryResults = [];
                    return;
                }
                const json = await res.json();
                this.signatoryResults = json.data || [];
            } catch (e) {
                this.signatoryResults = [];
            }
        },
        addExpectedPerson(f, person) {
            if (!f.field_options.expected_profiles)
                f.field_options.expected_profiles = [];
            if (!f.field_options.expected_people)
                f.field_options.expected_people = [];
            const id = Number(person.profile_id);
            if (!f.field_options.expected_profiles.includes(id)) {
                f.field_options.expected_profiles.push(id);
                // Companion display list (name + role) so chips render without a lookup.
                f.field_options.expected_people.push({
                    id,
                    name: person.name,
                    org_role: person.org_role || null,
                });
            }
            this.signatoryQuery = "";
            this.signatoryResults = [];
            this.syncCompareMode(f);
        },
        removeExpectedPerson(f, id) {
            id = Number(id);
            f.field_options.expected_profiles = (
                f.field_options.expected_profiles || []
            ).filter((x) => Number(x) !== id);
            f.field_options.expected_people = (
                f.field_options.expected_people || []
            ).filter((p) => Number(p.id) !== id);
            this.syncCompareMode(f);
        },
        // An expected signer implies Compare mode by default; the admin can still
        // uncheck it. Once they've explicitly chosen a mode, we don't override it.
        syncCompareMode(f) {
            if (this.hasExpectedSigner(f)) {
                if (f.field_options.match_mode !== "normal")
                    f.field_options.match_mode = "compare";
            } else if (f.field_options.match_mode === "compare") {
                f.field_options.match_mode = "normal";
            }
        },

        // --- persistence ---
        async save() {
            this.message = "";
            this.error = "";

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
                // Fold any Step-2 draft template into the saved form (server-side).
                draft_id: this.draftId,
            };
            try {
                const res = await fetch(
                    this.isEdit ? this.updateUrl : this.storeUrl,
                    {
                        method: this.isEdit ? "PUT" : "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": this.csrf,
                            Accept: "application/json",
                        },
                        body: JSON.stringify(payload),
                    },
                );
                const json = await res.json();
                if (res.ok) {
                    this.message = json.message || "Saved.";
                    // Every field is now persisted — its key is frozen for good.
                    this.fields.forEach((f) => {
                        f._keyLocked = true;
                    });
                    // On both create and edit, return to the Forms list where the
                    // flashed "saved" toast is shown.
                    if (json.redirect) {
                        window.location = json.redirect;
                    }
                } else {
                    this.error =
                        json.message ||
                        this.firstError(json.errors) ||
                        "Could not save.";
                }
            } catch (e) {
                this.error = "Could not save.";
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
