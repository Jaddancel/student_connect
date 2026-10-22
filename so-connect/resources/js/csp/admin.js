/*
 * Alpine components for the "admin" views, registered before Alpine.start().
 * They replace inline x-data objects/methods and multi-statement handlers,
 * which Alpine's CSP build (no 'unsafe-eval') cannot evaluate.
 */
export default function registerAdmin(Alpine) {
    // Client-side pagination shared by the admin *-requests index pages.
    Alpine.data("adminRequestPagination", ({ perPage = 10, total = 0 } = {}) => ({
        page: 1,
        perPage,
        total,
        get pages() {
            return Math.max(1, Math.ceil(this.total / this.perPage));
        },
        get visiblePages() {
            const s = Math.max(1, this.page - 1);
            const e = Math.min(this.pages, this.page + 1);
            return Array.from({ length: e - s + 1 }, (_, i) => s + i);
        },
        onPage(i) {
            return i >= (this.page - 1) * this.perPage && i < this.page * this.perPage;
        },
        setPage(p) {
            this.page = Math.max(1, Math.min(this.pages, p));
        },
        get from() {
            return this.total === 0 ? 0 : (this.page - 1) * this.perPage + 1;
        },
        get to() {
            return Math.min(this.page * this.perPage, this.total);
        },
    }));

    // pages/admin/accounts/create: dynamic faculty adviser rows.
    Alpine.data("adminAccountAdvisers", ({ advisers = [""] } = {}) => ({
        advisers,
        addAdviser() { this.advisers.push(''); },
        removeAdviser(i) { if (this.advisers.length > 1) this.advisers.splice(i, 1); },
    }));

    // pages/admin/accounts/create + officers/create: local preview of the chosen photo.
    Alpine.data("adminPhotoPreview", () => ({
        preview: null,
        pickPreview(event) {
            this.preview = event.target.files[0] ? URL.createObjectURL(event.target.files[0]) : null;
        },
    }));

    // pages/admin/backups/index: multi-select + bulk archive/delete confirmations.
    Alpine.data("adminBackupSelection", ({ all = [] } = {}) => ({
        all,
        selected: [],
        toggleAll(event) {
            this.selected = event.target.checked ? [...this.all] : [];
        },
        confirmBulkArchive(event) {
            if (! confirm(`Archive ${this.selected.length} backup(s)? They will be moved to the archived section.`)) event.preventDefault();
        },
        confirmBulkDelete(event) {
            if (! confirm(`Delete ${this.selected.length} backup(s)? This cannot be undone.`)) event.preventDefault();
        },
    }));

    // pages/admin/reports/index: generate a report via fetch behind a progress/error modal.
    Alpine.data("adminReportGenerator", () => ({
        busy: false,
        label: '',
        error: '',
        filters: {
            organization: '',
            organizationStatus: '',
            schoolYear: '__all__',
            semester: '__all__',
        },
        init() {
            try {
                const saved = JSON.parse(sessionStorage.getItem('report-global-filters') || '{}');
                this.filters = { ...this.filters, ...saved };
            } catch (_) {}
            ['organization', 'organizationStatus', 'schoolYear', 'semester'].forEach((key) => {
                this.$watch(`filters.${key}`, () => this.persistFilters());
            });
        },
        persistFilters() {
            sessionStorage.setItem('report-global-filters', JSON.stringify(this.filters));
        },
        resetFilters() {
            this.filters = {
                organization: '',
                organizationStatus: '',
                schoolYear: '__all__',
                semester: '__all__',
            };
            this.persistFilters();
        },
        // Generation can take a while (document conversion), so run it
        // through fetch: the modal stays up until the file arrives, then the
        // blob is saved without leaving the page.
        async generate(event) {
            const form = event.target;
            const format = event.submitter?.value || 'pdf';
            const url = new URL(form.action, window.location.origin);
            for (const [key, value] of new FormData(form)) {
                if (value !== '') url.searchParams.append(key, value);
            }
            url.searchParams.set('format', format);

            this.error = '';
            this.label = form.dataset.report + ' (' + (format === 'docx' ? 'Word' : 'PDF') + ')';
            this.busy = true;
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) {
                    const json = await response.json().catch(() => ({}));
                    const first = json.errors ? Object.values(json.errors).flat()[0] : null;
                    throw new Error(first || json.message || 'The report could not be generated.');
                }
                const disposition = response.headers.get('Content-Disposition') || '';
                const name = (disposition.match(/filename="?([^";]+)/) || [])[1] || 'report.' + format;
                const link = document.createElement('a');
                link.href = URL.createObjectURL(await response.blob());
                link.download = name;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(link.href);
                this.busy = false;
            } catch (e) {
                this.error = e.message || 'The report could not be generated.';
            }
        },
        dismissOnEscape() {
            if (this.error) this.busy = false;
        },
        closeError() {
            this.busy = false;
            this.error = '';
        },
    }));

    // pages/admin/scoring/create: live category totals + manual-input modal.
    // `values` carries the per-field initial counts rendered by Blade.
    Alpine.data("adminOrgScoreForm", (values = {}) => ({
        ...values,

        n(v) { return Math.max(0, parseInt(v) || 0); },

        get cat1_total() {
            return Math.min(100,
                this.n(this.cat1_seminar_college) * 10 +
                this.n(this.cat1_seminar_univ) * 15 +
                this.n(this.cat1_activities_related) * 10 +
                this.n(this.cat1_activities_not_related) * 7 +
                this.n(this.cat1_donation_cash) * 2 +
                this.n(this.cat1_donation_kinds) * 10 +
                this.n(this.cat1_cosponsor_pts) +
                this.n(this.cat1_income)
            );
        },
        get cat2_total() {
            return Math.min(100,
                this.n(this.cat2_other_orgs) * 10 +
                this.n(this.cat2_rep_local) * 2 +
                this.n(this.cat2_rep_provincial) * 3 +
                this.n(this.cat2_rep_regional) * 5 +
                this.n(this.cat2_rep_national) * 7 +
                this.n(this.cat2_rep_international) * 10 +
                this.n(this.cat2_ssc_osa_activities) * 10 +
                this.n(this.cat2_ssc_seminars) * 10 +
                this.n(this.cat2_other_seminars) * 7 +
                this.n(this.cat2_osa_seminars) * 5 +
                this.n(this.cat2_ssc_meeting_rep) * 2 +
                this.n(this.cat2_ssc_meeting_proxy) * 1 +
                this.n(this.cat2_help_ssc_osa) * 5 +
                this.n(this.cat2_help_others) * 3
            );
        },
        get cat3_total() {
            return Math.min(50,
                this.n(this.cat3_group_intl) * 20 +
                this.n(this.cat3_group_national) * 15 +
                this.n(this.cat3_group_regional) * 10 +
                this.n(this.cat3_group_provincial) * 7 +
                this.n(this.cat3_group_local) * 5 +
                this.n(this.cat3_individual_intl) * 15 +
                this.n(this.cat3_individual_national) * 10 +
                this.n(this.cat3_individual_regional) * 7 +
                this.n(this.cat3_individual_provincial) * 5 +
                this.n(this.cat3_individual_local) * 3
            );
        },
        get cat4_total() {
            return Math.min(100, this.n(this.cat4_extension_groups) * 10);
        },
        get cat5_total() {
            return Math.min(100, this.n(this.cat5_tangible_projects) * 100);
        },
        get cat6_total() {
            return Math.min(100,
                this.n(this.cat6_documents) * 50 +
                this.n(this.cat6_meetings) * 25 +
                this.n(this.cat6_leadership) * 15 +
                this.n(this.cat6_transparency) * 10
            );
        },
        get grand_total() {
            return this.cat1_total + this.cat2_total + this.cat3_total + this.cat4_total + this.cat5_total + this.cat6_total;
        },

        // Manual-input modal
        modalOpen: false,
        modalField: '',
        modalLabel: '',
        modalType: '',
        modalDivisor: 1,
        modalDesc: '',
        modalBasis: {},
        modalBinaryOn: false,
        modalConfig: {
            cat6_leadership: { label: 'Leadership Training', type: 'binary', desc: 'Check if the organization participated in leadership training.' },
        },
        openModal(field) {
            const cfg = this.modalConfig[field];
            this.modalField   = field;
            this.modalLabel   = cfg.label;
            this.modalType    = cfg.type;
            this.modalDivisor = cfg.divisor || 1;
            this.modalDesc    = cfg.desc;
            if (this.modalBasis[field] !== undefined) {
                if (cfg.type === 'binary') this.modalBinaryOn = !!this.modalBasis[field];
            } else {
                const cur = this.n(this[field]);
                if (cfg.type === 'pesos') {
                    this.modalBasis[field] = String(cur * (cfg.divisor || 1));
                } else if (cfg.type === 'count') {
                    this.modalBasis[field] = String(cur);
                } else {
                    this.modalBinaryOn = cur === 1;
                    this.modalBasis[field] = cur;
                }
            }
            this.modalOpen = true;
            this.$nextTick(() => { const el = this.$el.querySelector('.modal-autofocus'); if (el) el.focus(); });
        },
        get modalPreview() {
            if (this.modalType === 'binary') return this.modalBinaryOn ? 1 : 0;
            const raw = Math.max(0, parseInt(this.modalBasis[this.modalField] ?? 0) || 0);
            return this.modalType === 'pesos' ? Math.floor(raw / this.modalDivisor) : raw;
        },
        applyModal() {
            const instances = this.modalPreview;
            if (this.modalType === 'binary') this.modalBasis[this.modalField] = instances;
            this[this.modalField] = instances;
            this.modalOpen = false;
        },

        // Helpers for template expressions (Math is not reachable from CSP-build expressions).
        mathMin(a, b) { return Math.min(a, b); },
        get grandPercent() {
            return Math.min(100, Math.round(this.grand_total / 550 * 100));
        },
    }));

    // pages/admin/scoring/rules/editor: stateless helpers placed on nested elements inside
    // the tallyEditor scope (scopes merge, so `this` reaches tallyEditor's data/methods).
    Alpine.data("adminTallyEditorHelpers", () => ({
        sameValue(a, b) {
            return String(a) === String(b);
        },
        get linkedFormName() {
            return this.linkedForm?.name;
        },
        normalizeRowNextTick(row) {
            this.$nextTick(() => this.normalizeRow(row));
        },
    }));
}
