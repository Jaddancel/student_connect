/*
 * Alpine components for the "builder" views, registered before Alpine.start().
 * They replace inline x-data objects/methods and multi-statement handlers,
 * which Alpine's CSP build (no 'unsafe-eval') cannot evaluate.
 */
export default function registerBuilder(Alpine) {
    // form-builder/editor: nested scopes aliasing the parent's selection.
    Alpine.data("formBuilderSelectedRow", () => ({
        get row() {
            return this.rowById(this.selectedRow);
        },
    }));
    Alpine.data("formBuilderSelectedField", () => ({
        get f() {
            return this.field(this.selectedKey);
        },
    }));

    // report-templates/editor: nested scopes aliasing the parent's selection.
    Alpine.data("reportTemplateSelectedToken", () => ({
        get t() {
            return this.selected;
        },
    }));
    Alpine.data("reportTemplateSelectedParam", () => ({
        get p() {
            return this.selectedParam;
        },
    }));

    // components/form-builder/onlyoffice-template: wizard Step 2 printed-template
    // editor (OnlyOffice host). Nested inside formBuilder/reportTemplateBuilder,
    // whose `step` it watches.
    Alpine.data("onlyofficeTemplateEditor", (opts = {}) => ({
        configUrl: '',
        importUrl: '',
        versionUrl: '',
        addUrl: '',
        slots: [],
        selectedSlotId: null,
        menuOpen: false,
        switching: false,
        csrf: opts.csrf,
        editor: null,
        mounted: false,
        loading: false,
        importing: false,
        error: '',
        importError: '',
        saved: true,
        // Latched once onDocumentStateChange reports real edits this
        // session. `saved` alone cannot gate the save-flush: with
        // autosave it flips back to true as soon as the client has
        // SENT its changes, long before the Document Server has
        // assembled them into the draft (assembly only happens when
        // the editor session closes) — trusting it adopts the stale
        // pre-edit draft and the real edits are discarded.
        touched: false,
        // The draft version the editor booted with, and the version a
        // save-flush is waiting to see exceeded (null = none pending).
        version: 0,
        pendingFlush: null,
        // OnlyOffice is unusable on a phone-sized screen; Step 2 shows
        // a notice instead and never boots the editor there. Tablets
        // and up (≥768px) get the editor.
        isMobile: window.matchMedia('(max-width: 767px)').matches,

        // The draft was (re)synced by the parent; (re)boot against its
        // URLs. A fresh sync means new tokens and possibly a bumped
        // document — tear any live editor down first so it reloads.
        get currentSlot() {
            return this.slots.find((slot) => slot.id === this.selectedSlotId) || null;
        },

        get currentSlotName() {
            return this.currentSlot?.name;
        },

        // Formerly the template's x-init.
        init() {
            const syncNav = (value) => {
                if (!this.$store.sidebar) return;
                // Give the editor room on Step 2; restore the viewport
                // default (mini rail below 1280px) on the way out.
                this.$store.sidebar.isExpanded = value === 2 ? false : window.innerWidth >= 1280;
            };
            this.bindFocusGuard();
            this.$watch('step', (value) => syncNav(value));
            if (this.step === 2) syncNav(2);
            // Crossing the phone/tablet boundary swaps the editor for the
            // notice (and back) without leaving a stale editor behind. The
            // editor only re-boots if a draft has already been synced
            // (configUrl set) and we're on Step 2.
            window.matchMedia('(max-width: 767px)').addEventListener('change', (e) => {
                this.isMobile = e.matches;
                if (this.isMobile) this.teardown();
                else if (this.step === 2 && this.configUrl) this.start();
            });
        },

        onFlushRequest() {
            this.flushForSave().then((ok) => this.$dispatch('printed-template:flush-done', { ok }));
        },

        useSlot(slot) {
            this.selectedSlotId = slot.id;
            this.configUrl = slot.configUrl;
            this.importUrl = slot.importUrl;
            this.versionUrl = slot.versionUrl;
        },

        async onSync(detail) {
            if (this.editor && !(await this.flushForSave())) {
                this.importError = 'The current template is still saving. Please try again.';
                this.start();
                return;
            }
            this.slots = (detail && detail.slots) || [];
            this.addUrl = (detail && detail.addUrl) || '';
            const slot = this.slots.find((item) => item.id === this.selectedSlotId) || this.slots[0];
            if (slot) this.useSlot(slot);
            if (!this.configUrl) return;
            if (this.mounted) this.teardown();
            this.error = '';
            this.saved = true;
            this.touched = false;
            this.start();
        },

        async switchSlot(slot) {
            this.menuOpen = false;
            if (slot.id === this.selectedSlotId || this.switching) return;
            this.switching = true;
            this.importError = '';
            try {
                if (!(await this.flushForSave())) {
                    throw new Error('The current template is still saving. Please try again.');
                }
                this.teardown();
                this.useSlot(slot);
                this.saved = true;
                this.touched = false;
                this.pendingFlush = null;
                this.error = '';
                this.start();
            } catch (e) {
                this.importError = e.message || String(e);
                this.start();
            } finally {
                this.switching = false;
            }
        },

        async uploadAnother(event) {
            const input = event.target;
            const file = input.files && input.files[0];
            input.value = '';
            this.menuOpen = false;
            if (!file || !this.addUrl || this.switching) return;
            this.switching = true;
            this.importError = '';
            try {
                if (!(await this.flushForSave())) {
                    throw new Error('The current template is still saving. Please try again.');
                }
                this.teardown();
                const body = new FormData();
                body.append('docx', file);
                const response = await fetch(this.addUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body,
                });
                const data = await response.json();
                if (!response.ok || !data.ok) {
                    throw new Error(data.message || data.errors?.docx?.[0] || 'Could not add this document.');
                }
                this.slots = data.slots;
                this.useSlot(this.slots[this.slots.length - 1]);
                this.saved = true;
                this.touched = false;
                this.pendingFlush = null;
                this.error = '';
                this.start();
            } catch (e) {
                this.importError = e.message || String(e);
                this.start();
            } finally {
                this.switching = false;
            }
        },

        async removeSlot(slot) {
            this.menuOpen = false;
            if (this.slots.length <= 1 || this.switching || !window.confirm('Remove this printed template?')) return;
            this.switching = true;
            this.importError = '';
            const active = slot.id === this.selectedSlotId;
            try {
                if (active && !(await this.flushForSave())) {
                    throw new Error('The current template is still saving. Please try again.');
                }
                const response = await fetch(slot.removeUrl, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const data = await response.json();
                if (!response.ok || !data.ok) throw new Error(data.message || 'Could not remove the template.');
                this.slots = data.slots;
                if (active) {
                    this.teardown();
                    this.useSlot(this.slots[0]);
                    this.saved = true;
                    this.touched = false;
                    this.pendingFlush = null;
                    this.error = '';
                    this.start();
                }
            } catch (e) {
                this.importError = e.message || String(e);
                if (active) this.start();
            } finally {
                this.switching = false;
            }
        },

        start() {
            if (this.mounted || this.isMobile || !this.configUrl) return;
            this.mounted = true;
            this.loading = true;
            this.boot().catch((e) => { this.error = e.message || String(e); })
                       .finally(() => { this.loading = false; });
        },

        // Once the user clicks into the editor it owns the keyboard
        // until they click somewhere else on the host page. Host
        // shortcuts (Ctrl+S, ⌘K, Escape handlers…) are muted while it
        // does, and if focus slips back to the page (a re-render, a
        // toast) the next keystroke hands it straight back.
        bindFocusGuard() {
            const frame = () => document.querySelector('iframe[name=frameEditor]');
            let owns = false;

            window.addEventListener('blur', () => {
                window.setTimeout(() => {
                    if (frame() && document.activeElement === frame()) owns = true;
                }, 0);
            });
            const release = (e) => {
                const f = frame();
                if (f && e.target !== f && !f.contains(e.target)) owns = false;
            };
            document.addEventListener('mousedown', release, true);
            document.addEventListener('focusin', release, true);

            window.addEventListener('keydown', (e) => {
                const f = frame();
                if (!owns || !f || f.offsetParent === null) return;
                e.stopImmediatePropagation();
                if (document.activeElement !== f) {
                    f.focus();
                    f.contentWindow && f.contentWindow.focus();
                }
            }, true);
        },

        // Tear the editor down (e.g. when the viewport shrinks to
        // phone size) so it can be re-booted cleanly later.
        teardown() {
            if (this.editor && typeof this.editor.destroyEditor === 'function') {
                this.editor.destroyEditor();
            }
            this.editor = null;
            this.mounted = false;
        },

        // Replace the open document with an uploaded .docx. Close
        // the current editor and wait for its terminal callback
        // BEFORE storing the replacement; otherwise that callback
        // can arrive after the import and overwrite the new bytes.
        async importDocx(event) {
            const input = event.target;
            const file = input.files && input.files[0];
            input.value = '';
            if (!file || this.importing || this.switching || !this.importUrl) return;

            this.importing = true;
            this.importError = '';

            try {
                if (!(await this.flushForSave())) {
                    throw new Error('The current template is still closing. Please try the upload again.');
                }
                this.teardown();

                const body = new FormData();
                body.append('docx', file);
                const response = await fetch(this.importUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body,
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.ok) {
                    throw new Error(data.message
                        || (data.errors && data.errors.docx && data.errors.docx[0])
                        || 'Could not import this document.');
                }

                if (data.slots) {
                    this.slots = data.slots;
                    this.useSlot(this.slots.find((slot) => slot.id === this.selectedSlotId));
                }
                this.saved = true;
                this.touched = false;
                this.error = '';
                this.start();
            } catch (e) {
                this.importError = e.message || String(e);
                this.start();
            } finally {
                this.importing = false;
            }
        },

        async boot() {
            const response = await fetch(this.configUrl, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok || !data.enabled) {
                throw new Error(data.message || 'The document editor is unavailable.');
            }

            await this.loadScript(data.apiScript);

            if (typeof window.DocsAPI === 'undefined') {
                throw new Error('The editor script loaded but DocsAPI is missing.');
            }

            this.version = (typeof data.version === 'number') ? data.version : this.version;

            this.editor = new window.DocsAPI.DocEditor('onlyoffice-surface', Object.assign({}, data.config, {
                width: '100%',
                height: '100%',
                events: {
                    onError: (event) => {
                        this.error = (event && event.data && event.data.errorDescription)
                            || 'The editor reported an error.';
                    },
                    // Autosave is on, so this tracks whether the
                    // server still holds unsaved changes.
                    onDocumentStateChange: (event) => {
                        this.saved = !(event && event.data);
                        // Latch dirtiness: once the Document Server
                        // has received edits, the draft no longer
                        // matches the document until the session
                        // closes and the final callback lands.
                        if (event && event.data) this.touched = true;
                    },
                },
            }));
        },

        loadScript(src) {
            return new Promise((resolve, reject) => {
                if (document.querySelector('script[data-onlyoffice]')) return resolve();
                const script = document.createElement('script');
                script.src = src;
                script.dataset.onlyoffice = '1';
                script.onload = resolve;
                script.onerror = () => reject(new Error('Could not reach the document editor at ' + src));
                document.head.appendChild(script);
            });
        },

        // Save-flush, driven by the parent's save(): the Document
        // Server only assembles pending edits when the editor closes,
        // so saving with the editor open would fold the STALE draft
        // into the form's template (and the late final callback then
        // 404s against the already-cleared draft). Tear the editor
        // down first, then wait for the final callback to bump the
        // draft version before letting the form save proceed.
        async flushForSave() {
            // A previous flush already tore the editor down: give a
            // late final callback a short grace, then proceed —
            // nothing further can arrive afterwards.
            if (!this.editor) {
                if (this.pendingFlush !== null) {
                    await this.pollVersion(this.pendingFlush, 3000);
                    this.pendingFlush = null;
                }
                return true;
            }
            // Never edited this session — the draft already matches
            // the open document, so adopting it loses nothing. (`saved`
            // is NOT a substitute: it only tracks client→server sync
            // and is true again within a second of the last keystroke,
            // while the server assembles the document — and reports it
            // to the draft — only when the editor closes.)
            if (!this.touched) return true;
            if (!this.versionUrl) return false;

            const target = this.version;
            this.pendingFlush = target;
            this.teardown();

            const flushed = await this.pollVersion(target, 20000);
            if (flushed) this.pendingFlush = null;
            return flushed;
        },

        // Poll the draft version until it exceeds `target` (i.e. the
        // Document Server's final save callback stored a new
        // revision). Also resolves when the session closed with
        // nothing new to save, or when the draft is already gone —
        // nothing left to wait for in either case.
        async pollVersion(target, timeoutMs) {
            const deadline = Date.now() + timeoutMs;
            while (Date.now() < deadline) {
                try {
                    const res = await fetch(this.versionUrl, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (res.status === 404) return true;
                    const data = await res.json().catch(() => ({}));
                    if (res.ok && typeof data.version === 'number' && data.version > target) {
                        this.version = data.version;
                        return true;
                    }
                    // The Document Server closed the session without
                    // a new revision (e.g. every edit was undone).
                    if (res.ok && data.closed === true) return true;
                } catch (e) { /* keep polling until the deadline */ }
                await new Promise((r) => setTimeout(r, 500));
            }
            return false;
        },
    }));
}
