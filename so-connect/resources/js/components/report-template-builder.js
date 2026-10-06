import {
    dispatchPrintedTemplateSync,
    flushPrintedTemplate,
    syncPrintedTemplateDraft,
} from "./shared/printed-template-draft";

/**
 * Alpine component behind the Report Template wizard
 * (pages/admin/report-templates/editor.blade.php).
 *
 * Step 1 edits `definition` — { parameters: [...], tokens: [...] } — through a
 * token tree (group headers with indented children) and a "Data Box" that
 * renders the selected token as a sentence with dropdown pills, sourced from
 * the introspected schema. See App\Reports\ReportDefinitionValidator for the
 * shape. Step 2 reuses the form builder's OnlyOffice component through a
 * report draft; Step 3 holds the details.
 */
export function reportTemplateBuilder(config) {
    return {
        // --- config ---
        schemaUrl: config.schemaUrl,
        previewUrl: config.previewUrl,
        parameterOptionsUrl: config.parameterOptionsUrl,
        draftSyncUrl: config.draftSyncUrl,
        storeUrl: config.storeUrl,
        updateUrl: config.updateUrl,
        reportId: config.reportId || null,
        isEdit: !!config.reportId,
        printEnabled: !!config.printEnabled,
        csrf: config.csrf,

        // --- model ---
        name: config.data.name || "",
        description: config.data.description || "",
        icon: config.data.icon || "",
        audience: config.data.audience || "admins",
        is_active: config.data.is_active !== false,
        definition: normalizeDefinition(config.data.definition),

        // --- ui ---
        step: 1,
        selectedId: null,
        selectedParam: null,
        schema: { tables: [], ops: [], aggregates: [], formats: [], paramTypes: [] },
        schemaLoaded: false,
        draftId: null,
        saving: false,
        message: "",
        error: "",
        preview: { loading: false, html: "", errors: [], truncated: false, params: {} },
        paramOptions: {},
        previewTimer: null,

        async init() {
            try {
                const res = await fetch(this.schemaUrl, { headers: { Accept: "application/json" } });
                this.schema = await res.json();
            } catch (e) {
                this.error = "Could not load the database catalog.";
            }
            this.schemaLoaded = true;
            if (this.definition.tokens.length) {
                this.selectedId = this.definition.tokens[0].id;
            }
            this.definition.parameters.forEach((p) => this.loadParamOptions(p));
            this.$watch("definition", () => this.schedulePreview(), { deep: true });
            this.runPreview();
        },

        // ── schema helpers ────────────────────────────────────────────────
        table(name) {
            return this.schema.tables.find((t) => t.name === name) || null;
        },
        columns(tableName) {
            return (this.table(tableName) || {}).columns || [];
        },
        relations(tableName, type) {
            return ((this.table(tableName) || {}).relations || []).filter((r) => !type || r.type === type);
        },
        relationTable(tableName, relationName) {
            const relation = this.relations(tableName).find((r) => r.name === relationName);
            return relation ? relation.table : null;
        },

        // ── tree helpers ──────────────────────────────────────────────────
        /** Flattened tree for the Token List: [{ token, depth, parent }]. */
        get flatTokens() {
            const out = [];
            const walk = (tokens, depth, parent) => {
                tokens.forEach((token) => {
                    out.push({ token, depth, parent });
                    if (token.kind === "group") walk(token.children, depth + 1, token);
                });
            };
            walk(this.definition.tokens, 0, null);
            return out;
        },
        get selected() {
            const entry = this.flatTokens.find((e) => e.token.id === this.selectedId);
            return entry ? entry.token : null;
        },
        parentOf(token) {
            const entry = this.flatTokens.find((e) => e.token === token);
            return entry ? entry.parent : null;
        },
        siblingsOf(token) {
            const parent = this.parentOf(token);
            return parent ? parent.children : this.definition.tokens;
        },
        /** The table a token's rows/values read from ("context"). */
        contextTable(token) {
            const parent = this.parentOf(token);
            return parent ? this.groupTable(parent) : null;
        },
        groupTable(group) {
            if (!group) return null;
            const parent = this.parentOf(group);
            if (!parent) return group.entity || null;
            return this.relationTable(this.groupTable(parent), group.relation);
        },
        tokenPath(token) {
            const names = [];
            for (let t = token; t; t = this.parentOf(t)) names.unshift(t.name || "?");
            return names.join(".");
        },
        groups() {
            return this.flatTokens.filter((e) => e.token.kind === "group").map((e) => e.token);
        },
        isDescendant(candidate, ancestor) {
            for (let t = candidate; t; t = this.parentOf(t)) if (t === ancestor) return true;
            return false;
        },

        // ── token editing ────────────────────────────────────────────────
        uniqueName(base, siblings) {
            let name = base;
            let i = 2;
            while (siblings.some((t) => t.name === name)) name = `${base}_${i++}`;
            return name;
        },
        /** "+" — a value token, inside the selected group (or beside the selection). */
        addValue() {
            const target = this.insertionTarget();
            const token = {
                id: uid(), kind: "value", name: this.uniqueName("value", target),
                mode: "field", from: "", path: [], fn: "count", relation: "", column: "", expression: "",
                where: [], format: { type: "text", pattern: "", fallback: "" },
            };
            target.push(token);
            this.select(token);
        },
        /** "New Group" — a repeating group. */
        addGroup() {
            const target = this.insertionTarget();
            const token = {
                id: uid(), kind: "group", name: this.uniqueName("group", target),
                entity: "", relation: "", where: [], order: [], limit: null, children: [],
            };
            target.push(token);
            this.select(token);
        },
        insertionTarget() {
            const s = this.selected;
            if (s && s.kind === "group") return s.children;
            if (s) return this.siblingsOf(s);
            return this.definition.tokens;
        },
        select(token) {
            this.selectedId = token.id;
            this.selectedParam = null;
        },
        removeSelected() {
            const s = this.selected;
            if (!s) return;
            const siblings = this.siblingsOf(s);
            siblings.splice(siblings.indexOf(s), 1);
            this.selectedId = null;
        },
        move(token, delta) {
            const siblings = this.siblingsOf(token);
            const i = siblings.indexOf(token);
            const j = i + delta;
            if (j < 0 || j >= siblings.length) return;
            siblings.splice(i, 1);
            siblings.splice(j, 0, token);
        },
        /** Re-parent the selected token ('' = top level). */
        moveTo(token, groupId) {
            const target = groupId ? this.flatTokens.find((e) => e.token.id === groupId)?.token : null;
            if (target && (target === token || this.isDescendant(target, token))) return;
            const siblings = this.siblingsOf(token);
            siblings.splice(siblings.indexOf(token), 1);
            (target ? target.children : this.definition.tokens).push(token);
            // The context table changed: clear bindings that no longer apply.
            if (token.kind === "group") {
                if (target) { token.entity = ""; } else { token.relation = ""; }
            } else {
                token.path = [];
                token.relation = "";
                if (target) token.from = "";
            }
        },
        normalizeName(token) {
            token.name = (token.name || "")
                .toLowerCase()
                .replace(/[^a-z0-9_]+/g, "_")
                .replace(/_+/g, "_")
                .replace(/^[^a-z]+/, "");
        },

        // ── compute tokens ───────────────────────────────────────────────
        /** Sibling value tokens a formula can read (never itself). */
        computeNames(token) {
            return this.siblingsOf(token)
                .filter((t) => t.kind === "value" && t !== token && t.name)
                .map((t) => t.name);
        },
        addToExpression(token, text) {
            const current = token.expression || "";
            token.expression = current && !/[\s(]$/.test(current) ? `${current} ${text}` : `${current}${text}`;
        },

        // ── value paths (pills) ──────────────────────────────────────────
        /**
         * One pill per path segment: each picks a belongs_to relation (which
         * opens another pill on the related table) or a column (which ends it).
         */
        pills(baseTable, path) {
            const pills = [];
            let table = baseTable;
            for (let i = 0; table && i < 8; i++) {
                const value = path[i] || "";
                pills.push({ index: i, table, value });
                if (!value || !this.isRelationSegment(table, value)) break;
                table = this.relationTable(table, value);
            }
            return pills;
        },
        pathPills(token) {
            return this.pills(this.contextTable(token) || token.from, token.path);
        },
        isRelationSegment(tableName, segment) {
            return this.relations(tableName, "belongs_to").some((r) => r.name === segment);
        },
        setPathSegment(token, index, value) {
            token.path = token.path.slice(0, index);
            if (value) token.path.push(value);
        },

        // ── conditions / order ───────────────────────────────────────────
        conditionTable(token) {
            if (token.kind === "group") return this.groupTable(token);
            if (token.mode === "aggregate") {
                const context = this.contextTable(token);
                return context ? this.relationTable(context, token.relation) : token.from;
            }
            return token.from;
        },
        addCondition(token) {
            token.where.push({ column: "", op: "=", value: "", param: "" });
        },
        addOrder(token) {
            token.order.push({ column: "", dir: "asc" });
        },
        /** The organizations key, or a column referencing it: it can take the session's organization. */
        isOrganizationColumn(tableName, column) {
            const table = this.table(tableName);
            if (!table || !column) return false;
            if (tableName === "organizations") return table.primary === column;
            return (table.relations || []).some((r) => r.type === "belongs_to" && r.table === "organizations" && r.local === column);
        },
        needsValue(op) {
            return !["is_null", "not_null"].includes(op);
        },

        // ── parameters ───────────────────────────────────────────────────
        addParameter() {
            const param = {
                name: this.uniqueName("param", this.definition.parameters),
                label: "", type: "entity", entity: "", display: [], required: false, context: "", default: "",
            };
            this.definition.parameters.push(param);
            this.selectParam(param);
        },
        selectParam(param) {
            this.selectedParam = param;
            this.selectedId = null;
        },
        removeParam(param) {
            this.definition.parameters.splice(this.definition.parameters.indexOf(param), 1);
            this.selectedParam = null;
        },
        paramDisplayPills(param) {
            return this.pills(param.entity, param.display);
        },
        setDisplaySegment(param, index, value) {
            param.display = param.display.slice(0, index);
            if (value) param.display.push(value);
            this.loadParamOptions(param);
        },
        async loadParamOptions(param) {
            if (param.type !== "entity" || !param.entity) return;
            try {
                const res = await this.post(this.parameterOptionsUrl, { parameter: param });
                const json = await res.json();
                this.paramOptions[param.name] = json.options || [];
            } catch (e) {
                this.paramOptions[param.name] = [];
            }
        },

        // ── preview ──────────────────────────────────────────────────────
        schedulePreview() {
            clearTimeout(this.previewTimer);
            this.previewTimer = setTimeout(() => this.runPreview(), 700);
        },
        async runPreview() {
            if (!this.definition.tokens.length) {
                this.preview.html = "";
                this.preview.errors = [];
                return;
            }
            this.preview.loading = true;
            try {
                const res = await this.post(this.previewUrl, {
                    definition: this.serialize(),
                    params: this.preview.params,
                });
                const json = await res.json().catch(() => ({}));
                if (res.ok) {
                    this.preview.errors = [];
                    this.preview.truncated = !!json.truncated;
                    this.preview.html = renderPreview(json.data || {});
                } else {
                    this.preview.errors = json.errors || [json.message || "Preview failed."];
                }
            } catch (e) {
                this.preview.errors = ["Preview failed."];
            } finally {
                this.preview.loading = false;
            }
        },

        // ── wizard ───────────────────────────────────────────────────────
        async goToStep(n) {
            this.error = "";
            if (n >= 2 && !this.definition.tokens.length) {
                this.error = "Add at least one token in Step 1 first.";
                return;
            }
            if (n === 2 && this.printEnabled) {
                const result = await syncPrintedTemplateDraft(this.draftSyncUrl, this.csrf, {
                    draft_id: this.draftId,
                    report_id: this.reportId,
                    name: this.name,
                    definition: this.serialize(),
                });
                if (result.error) {
                    this.error = result.error;
                    return;
                }
                this.draftId = result.draftId;
                this.step = 2;
                dispatchPrintedTemplateSync(result.detail);
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

        // ── persistence ──────────────────────────────────────────────────
        serialize() {
            return JSON.parse(JSON.stringify(this.definition));
        },
        post(url, body, method = "POST") {
            return fetch(url, {
                method,
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": this.csrf,
                    Accept: "application/json",
                },
                body: JSON.stringify(body),
            });
        },
        async save() {
            this.message = "";
            this.error = "";
            this.saving = true;
            if (this.printEnabled && this.draftId && !(await flushPrintedTemplate())) {
                this.error = "The printed template is still saving. Please wait a moment and click Save again.";
                this.saving = false;
                return;
            }
            try {
                const res = await this.post(
                    this.isEdit ? this.updateUrl : this.storeUrl,
                    {
                        name: this.name,
                        description: this.description,
                        icon: this.icon || null,
                        audience: this.audience,
                        is_active: this.is_active,
                        definition: this.serialize(),
                        draft_id: this.draftId,
                    },
                    this.isEdit ? "PUT" : "POST",
                );
                const json = await res.json().catch(() => ({}));
                if (res.ok) {
                    this.message = json.message || "Saved.";
                    if (json.redirect) window.location = json.redirect;
                } else {
                    const first = json.errors ? Object.values(json.errors).flat()[0] : null;
                    this.error = first || json.message || "Could not save.";
                }
            } catch (e) {
                this.error = "Could not save.";
            } finally {
                this.saving = false;
            }
        },
    };
}

function uid() {
    return `t_${Math.random().toString(36).slice(2, 10)}`;
}

/** Fill defaults so every token has the keys the UI binds to. */
function normalizeDefinition(raw) {
    const definition = { parameters: [], tokens: [], ...(raw || {}) };
    const fix = (tokens) =>
        (tokens || []).map((t) => {
            if (t.kind === "group") {
                return {
                    id: t.id || uid(), kind: "group", name: t.name || "", entity: t.entity || "",
                    relation: t.relation || "", where: (t.where || []).map(fixCond), order: t.order || [],
                    limit: t.limit ?? null, children: fix(t.children),
                };
            }
            return {
                id: t.id || uid(), kind: "value", name: t.name || "", mode: t.mode || "field",
                from: t.from || "", path: t.path || [], fn: t.fn || "count", relation: t.relation || "",
                column: t.column || "", expression: t.expression || "", where: (t.where || []).map(fixCond),
                format: { type: "text", pattern: "", fallback: "", ...(t.format || {}) },
            };
        });
    const fixCond = (c) => ({
        column: c.column || "", op: c.op || "=",
        value: Array.isArray(c.value) ? c.value.join(", ") : c.value ?? "", param: c.param || "",
    });
    definition.tokens = fix(definition.tokens);
    definition.parameters = (definition.parameters || []).map((p) => ({
        name: p.name || "", label: p.label || "", type: p.type || "text", entity: p.entity || "",
        display: p.display || [], required: !!p.required, context: p.context || "", default: p.default || "",
    }));
    return definition;
}

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (c) => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
    })[c]);
}

/** The resolved data tree as nested tables (values escaped). */
function renderPreview(data) {
    const scalars = Object.entries(data).filter(([, v]) => !Array.isArray(v));
    const groups = Object.entries(data).filter(([, v]) => Array.isArray(v));
    let html = "";
    if (scalars.length) {
        html += '<table class="rt-preview"><tbody>' + scalars.map(([k, v]) =>
            `<tr><th>${escapeHtml(k)}</th><td>${escapeHtml(v) || '<span class="rt-empty">—</span>'}</td></tr>`).join("") + "</tbody></table>";
    }
    groups.forEach(([name, rows]) => {
        html += `<p class="rt-group-title">${escapeHtml(name)} <span>(${rows.length} row${rows.length === 1 ? "" : "s"})</span></p>`;
        html += renderRows(rows);
    });
    return html || '<p class="rt-empty">No data.</p>';
}

function renderRows(rows) {
    if (!rows.length) return '<p class="rt-empty">No rows.</p>';
    const keys = Object.keys(rows[0]);
    return '<table class="rt-preview"><thead><tr>' + keys.map((k) => `<th>${escapeHtml(k)}</th>`).join("") +
        "</tr></thead><tbody>" + rows.map((row) => "<tr>" + keys.map((k) => {
            const v = row[k];
            return `<td>${Array.isArray(v) ? renderRows(v) : escapeHtml(v)}</td>`;
        }).join("") + "</tr>").join("") + "</tbody></table>";
}
