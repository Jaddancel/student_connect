/*
 * Alpine components for the "forms" views, registered before Alpine.start().
 * They replace inline x-data objects/methods and multi-statement handlers,
 * which Alpine's CSP build (no 'unsafe-eval') cannot evaluate.
 */
export default function registerForms(Alpine) {
    // Replaces onsubmit="return confirm('…')": <form data-confirm="…">.
    // Guarded so only one such delegated listener binds per page app-wide.
    if (!window.__csDataConfirmBound) {
        window.__csDataConfirmBound = true;
        document.addEventListener(
            "submit",
            (e) => {
                const f = e.target;
                if (
                    f instanceof HTMLFormElement &&
                    f.dataset.confirm !== undefined &&
                    !e.defaultPrevented &&
                    !confirm(f.dataset.confirm)
                ) {
                    e.preventDefault();
                }
            },
            true,
        );
    }

    // Replaces onchange="this.form.submit()": <select data-autosubmit>.
    // Same guard as the other groups so only one listener binds per page.
    if (!window.__csAutoSubmitBound) {
        window.__csAutoSubmitBound = true;
        document.addEventListener("change", (e) => {
            const t = e.target;
            if (t instanceof HTMLElement && t.dataset.autosubmit !== undefined && t.form) {
                t.form.submit();
            }
        });
    }

    // components/form/date-picker.blade.php
    Alpine.data("formDatePicker", (opts = {}) => ({
        flatpickrInstance: null,
        init() {
            this.$nextTick(() => {
                this.flatpickrInstance = window.flatpickr(this.$refs.dateInput, {
                    mode: opts.mode,
                    static: true,
                    monthSelectorType: 'static',
                    dateFormat: opts.dateFormat,
                    defaultDate: opts.defaultDate,
                    onChange: (selectedDates, dateStr, instance) => {
                        this.$dispatch('date-change', {
                            selectedDates,
                            dateStr,
                            instance
                        });
                    }
                });
            });
        },
        destroy() {
            if (this.flatpickrInstance) {
                this.flatpickrInstance.destroy();
                this.flatpickrInstance = null;
            }
        }
    }));

    // components/form/fields/field.blade.php — multi-select dropdown.
    Alpine.data("formMultiSelect", (opts = {}) => ({
        open: false,
        selected: opts.selected,
        labels: opts.labels,
        order: opts.order,
        summary() { return this.order.filter((v) => this.selected.includes(v)).map((v) => this.labels[v]).join(', '); },
    }));

    // components/form/fields/field.blade.php — date / time / datetime inputs.
    // `options` are the static flatpickr options; `defaultNow` adds
    // `defaultDate: new Date()` evaluated at init (time/datetime autofill).
    Alpine.data("formFlatpickrInput", (opts = {}) => ({
        init() {
            const options = { ...(opts.options || {}) };
            if (opts.defaultNow) options.defaultDate = new Date();
            window.flatpickr && window.flatpickr(this.$el, options);
        },
    }));

    // components/form/fields/field.blade.php — new officer/president email check.
    Alpine.data("formEmailAvailability", (opts = {}) => ({
        status: null,
        check(event) {
            const input = event ? event.target : this.$el;
            if (! input.value) { this.status = null; return; }
            fetch(opts.url + '?email=' + encodeURIComponent(input.value)).then(r => r.json()).then(d => this.status = d.registered ? 'registered' : 'available').catch(() => this.status = null);
        },
    }));

    // components/form/fields/field.blade.php — event select that autofills
    // empty sibling inputs from the chosen option's data-* attributes.
    Alpine.data("formEventSelect", (opts = {}) => ({
        autofillFromEvent(selectEl) {
            const opt = selectEl.selectedOptions[0];
            if (!opt) return;
            const map = opts.map;
            for (const [attr, target] of Object.entries(map)) {
                const val = opt.dataset[attr];
                if (val === undefined) continue;
                const el = document.querySelector('[name="' + target + '"]');
                if (el && !el.value) { el.value = val; el.dispatchEvent(new Event('input', { bubbles: true })); }
            }
        },
    }));

    // components/form/fields/field.blade.php — workplan picker that shows the
    // picked workplan's approved activities below it.
    Alpine.data("formWorkplanSelect", (opts = {}) => ({
        picked: opts.picked ?? "",
        isPicked(id) {
            return String(this.picked) === String(id);
        },
    }));

    // components/form/fields/field.blade.php — repeatable text list.
    Alpine.data("formTextList", (opts = {}) => ({
        rows: opts.rows,
        removeRow(i) {
            this.rows.splice(i, 1);
            if (!this.rows.length) this.rows.push('');
        },
    }));

    // components/form/fields/table-input.blade.php
    Alpine.data("formTableInput", (opts = {}) => {
        const rowTotalKey = opts.rowTotalKey;
        const fieldKey = opts.fieldKey;
        return {
            rows: opts.rows,
            cols: opts.cols,
            operands: opts.operands,
            op: opts.op,
            hasRowTotal: opts.hasRowTotal,
            // Mirrors FieldType::applyRowTotal(): left to right, ÷0 gives 0.
            rowTotal(row) {
                if (!this.hasRowTotal) return 0;
                const values = this.operands.map((k) => parseFloat(row[k]) || 0);
                const result = values.slice(1).reduce((acc, v) => {
                    if (this.op === 'add') return acc + v;
                    if (this.op === 'subtract') return acc - v;
                    if (this.op === 'divide') return v === 0 ? 0 : acc / v;
                    return acc * v;
                }, values[0] || 0);
                return Math.round(result * 100) / 100;
            },
            get footer() {
                if (!this.hasRowTotal) return 0;
                return Math.round(this.rows.reduce((s, r) => s + this.rowTotal(r), 0) * 100) / 100;
            },
            addRow() {
                const blank = {};
                this.cols.forEach(c => blank[c.key] = '');
                this.rows.push(blank);
            },
            removeRow(i) { this.rows.splice(i, 1); if (!this.rows.length) this.addRow(); },
            // Publishes this table's per-column sums (plus its row-total column, if
            // any) to the shared formCompute store, so `computed` fields referencing
            // `<fieldKey>.columnKey` live-recompute as rows are typed/added/removed.
            publishSums() {
                const sums = {};
                this.cols.forEach((c) => {
                    if (c.type === 'number') {
                        sums[c.key] = this.rows.reduce((s, r) => s + (parseFloat(r[c.key]) || 0), 0);
                    }
                });
                if (this.hasRowTotal) {
                    sums[rowTotalKey] = this.footer;
                }
                Alpine.store('formCompute').tableSums[fieldKey] = sums;
            },
            init() {
                this.publishSums();
                this.$watch('rows', () => this.publishSums());
            },
        };
    });

    // components/form/form-elements/dropzone.blade.php (demo dropzone).
    Alpine.data("formDemoDropzone", () => ({
        isDragging: false,
        files: [],
        handleDrop(e) {
            this.isDragging = false;
            const droppedFiles = Array.from(e.dataTransfer.files);
            this.handleFiles(droppedFiles);
        },
        handleFiles(selectedFiles) {
            const validTypes = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];
            const validFiles = selectedFiles.filter(file => validTypes.includes(file.type));

            if (validFiles.length > 0) {
                this.files = [...this.files, ...validFiles];
                console.log('Files uploaded:', validFiles);

                // Here you can add logic to upload files to server
                this.uploadFiles(validFiles);
            }
        },
        uploadFiles(files) {
            // Implement your file upload logic here
            // Example: Use FormData and fetch/axios to upload
            console.log('Uploading files:', files);
        },
        removeFile(index) {
            this.files.splice(index, 1);
        },
        // File-input @change: add the picked files, then reset the input.
        onFileInputChange(event) {
            this.handleFiles(Array.from(event.target.files));
            event.target.value = '';
        },
    }));

    // components/form/select/multiple-select.blade.php (demo multi-select).
    Alpine.data("formDemoMultipleSelect", () => ({
        open: false,
        selected: [1, 3],
        options: [
            { id: 1, name: 'Option 1' },
            { id: 2, name: 'Option 2' },
            { id: 3, name: 'Option 3' },
            { id: 4, name: 'Option 4' },
            { id: 5, name: 'Option 5' }
        ],
        toggleOption(id) {
            if (this.selected.includes(id)) {
                this.selected = this.selected.filter(i => i !== id);
            } else {
                this.selected.push(id);
            }
        },
        isSelected(id) {
            return this.selected.includes(id);
        },
        optionName(id) {
            return this.options.find(o => o.id === id).name;
        },
    }));

    // pages/form/directory.blade.php — searchable forms directory.
    // components/form/fields/field: reusable attachment library picker on
    // multi-image fields; attachments matching the activity are ranked first.
    Alpine.data("attachmentLibraryPicker", ({ selected = [], items = [], max = 0 } = {}) => ({
        open: false,
        search: "",
        selected,
        items,
        max,
        activityTitle() {
            return (document.getElementById("title")?.value || "").toLowerCase();
        },
        eventId() {
            return String(document.getElementById("event_id")?.value || "");
        },
        score(item) {
            let score = this.eventId() && String(item.event_id || "") === this.eventId() ? 100 : 0;
            const words = this.activityTitle().split(/\s+/).filter((w) => w.length >= 3);
            const haystack = [item.title, ...(item.tags || [])].join(" ").toLowerCase();
            score += words.filter((word) => haystack.includes(word)).length * 10;
            return score;
        },
        get filtered() {
            const term = this.search.trim().toLowerCase();
            return this.items
                .filter((item) => !term || [item.title, item.name, ...(item.tags || [])].join(" ").toLowerCase().includes(term))
                .sort((a, b) => this.score(b) - this.score(a));
        },
        toggle(path) {
            const index = this.selected.indexOf(path);
            if (index >= 0) this.selected.splice(index, 1);
            else if (this.selected.length < this.max) this.selected.push(path);
            this.$nextTick(() => window.dispatchEvent(new Event("change")));
        },
    }));

    Alpine.data("formDirectorySearch", (opts = {}) => ({
        q: '',
        forms: opts.forms,
        get filtered() {
            const q = this.q.trim().toLowerCase();
            if (!q) return this.forms;
            return this.forms.filter((f) =>
                (f.name || '').toLowerCase().includes(q) ||
                (f.purpose || '').toLowerCase().includes(q));
        },
    }));

    // components/event-plan-card.blade.php — card root.
    Alpine.data("eventPlanCard", (opts = {}) => {
        const isEventRequest = !!opts.isEventRequest;
        const organizationId = opts.organizationId;
        return {
            open: false,
            showCreateForm: false,
            showReviseForm: false,
            reviseOfficers: [],
            reviseSelected: opts.reviseSelected ?? [],
            reviseLoading: false,
            async openReviseForm() {
                this.showReviseForm = !this.showReviseForm;
                if (!isEventRequest && this.showReviseForm && this.reviseOfficers.length === 0) {
                    this.reviseLoading = true;
                    try {
                        const r = await fetch('/api/organizations/' + organizationId + '/officers');
                        this.reviseOfficers = await r.json();
                    } finally {
                        this.reviseLoading = false;
                    }
                }
            },
        };
    });

    // components/event-plan-card.blade.php — inline revise & resubmit form.
    Alpine.data("eventPlanReviseForm", (opts = {}) => ({
        facilities: opts.facilities,
        addFacility() {
            if (this.facilities.length < 10) this.facilities.push('');
        },
        removeFacility(index) {
            if (this.facilities.length > 1) this.facilities.splice(index, 1);
        },
        advisers: opts.advisers,
        addAdviser() {
            this.advisers.push('');
        },
        removeAdviser(index) {
            if (this.advisers.length > 1) this.advisers.splice(index, 1);
        },
        activityTypes: opts.activityTypes,
        activityType: opts.activityType,
        activityTypeOther: opts.activityTypeOther,
        seminarLevel: opts.seminarLevel,
        areaScope: opts.areaScope,
        areaScopeOther: opts.areaScopeOther,
        sponsor: opts.sponsor,
        sponsorOther: opts.sponsorOther,
        cosponsorCount: opts.cosponsorCount,
        relatedToOrg: opts.relatedToOrg,
        extensionServices: opts.extensionServices,
    }));
}
