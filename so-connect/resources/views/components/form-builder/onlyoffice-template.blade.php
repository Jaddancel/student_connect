@props(['printEnabled' => false])

{{--
    Wizard Step 2 — the printed template, edited in OnlyOffice.

    Replaces the old contenteditable rich-text editor. The document itself is a
    real .docx (see FormPrintTemplateService), so what the admin lays out here
    is what DocxTemplateService::populate() later fills in — no HTML round-trip
    in between.

    The field-token palette lives inside the editor as a Document Server plugin
    rather than in this page: Community Edition has no Automation API, so the
    host page cannot insert content into the open document.

    The editor boots off a *draft* synced when the wizard enters this step (see
    form-builder.js goToStep/syncDraft): the parent dispatches a
    'printed-template:sync' event carrying this draft's config/import URLs, which
    onSync() below picks up to (re)boot the editor. Because the draft is
    non-persistent (file cache, no DB), this works for brand-new unsaved forms
    too — there is no longer a "save the form first" gate.
--}}
<div class="grid grid-cols-12 gap-5">
    <div class="col-span-12">
        @if (! $printEnabled)
            <div class="rounded-2xl border border-dashed border-gray-300 bg-palette-surface p-10 text-center dark:border-gray-700 dark:bg-white/[0.03]">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Printed-template editor unavailable</h3>
                <p class="mx-auto mt-2 max-w-md text-xs text-gray-500 dark:text-gray-400">
                    The document editor is not configured. Set <code>ONLYOFFICE_PUBLIC_URL</code> and an
                    <code>ONLYOFFICE_JWT_SECRET</code> of at least 32 characters, then reopen this form to
                    design its printed output. Your form's fields still save normally without it.
                </p>
            </div>
        @else
            <div x-data="{
                    configUrl: '',
                    importUrl: '',
                    versionUrl: '',
                    csrf: '{{ csrf_token() }}',
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
                    onSync(detail) {
                        this.configUrl = (detail && detail.configUrl) || '';
                        this.importUrl = (detail && detail.importUrl) || '';
                        this.versionUrl = (detail && detail.versionUrl) || '';
                        if (!this.configUrl) return;
                        if (this.mounted) this.teardown();
                        this.error = '';
                        this.saved = true;
                        this.touched = false;
                        this.start();
                    },

                    start() {
                        if (this.mounted || this.isMobile || !this.configUrl) return;
                        this.mounted = true;
                        this.loading = true;
                        this.boot().catch((e) => { this.error = e.message || String(e); })
                                   .finally(() => { this.loading = false; });
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

                    // Replace the open document with an uploaded .docx. Only
                    // enabled once the editor reports 'all changes saved', so it
                    // has no pending edits to lose. On success the revision is
                    // bumped server-side (new document key), so tearing the
                    // editor down and re-booting loads the uploaded content. On
                    // failure the live editor is left untouched.
                    async importDocx(event) {
                        const input = event.target;
                        const file = input.files && input.files[0];
                        input.value = '';
                        if (!file || this.importing || !this.importUrl) return;

                        this.importing = true;
                        this.importError = '';

                        try {
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

                            if (this.editor && typeof this.editor.destroyEditor === 'function') {
                                this.editor.destroyEditor();
                                this.editor = null;
                            }
                            this.mounted = false;
                            this.saved = true;
                            this.touched = false;
                            this.error = '';
                            this.start();
                        } catch (e) {
                            this.importError = e.message || String(e);
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
                }"
                x-init="
                    const syncNav = (value) => {
                        if (!$store.sidebar) return;
                        // Give the editor room on Step 2; restore the viewport
                        // default (mini rail below 1280px) on the way out.
                        $store.sidebar.isExpanded = value === 2 ? false : window.innerWidth >= 1280;
                    };
                    $watch('step', (value) => syncNav(value));
                    if (step === 2) syncNav(2);
                    // Crossing the phone/tablet boundary swaps the editor for the
                    // notice (and back) without leaving a stale editor behind. The
                    // editor only re-boots if a draft has already been synced
                    // (configUrl set) and we're on Step 2.
                    window.matchMedia('(max-width: 767px)').addEventListener('change', (e) => {
                        isMobile = e.matches;
                        if (isMobile) teardown();
                        else if (step === 2 && configUrl) start();
                    });
                "
                @printed-template:sync.window="onSync($event.detail)"
                @printed-template:flush-request.window="flushForSave().then((ok) => $dispatch('printed-template:flush-done', { ok }))"
                class="rounded-2xl border border-gray-200 bg-palette-surface p-4 dark:border-gray-800 dark:bg-white/[0.03]">

                <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Printed template</h3>
                        <p class="text-xs text-gray-400">
                            Insert form fields from the <span class="font-medium">Field tokens</span> panel
                            inside the editor. Changes save automatically; to download or print, use the
                            editor's <span class="font-medium">File</span> menu.
                        </p>
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400" x-show="importError" x-cloak x-text="importError"></p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3" x-show="!isMobile" x-cloak>
                        <template x-if="importUrl">
                            <span class="flex items-center gap-3">
                                <input type="file" x-ref="docxInput" accept=".docx"
                                       class="hidden" @change="importDocx($event)">
                                <button type="button"
                                        @click="$refs.docxInput.click()"
                                        :disabled="!saved || loading || importing || !mounted"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-brand-400 hover:bg-brand-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                                        title="Replace this template with an uploaded Word document">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="17 8 12 3 7 8"></polyline>
                                        <line x1="12" y1="3" x2="12" y2="15"></line>
                                    </svg>
                                    <span x-text="importing ? 'Uploading…' : 'Upload .docx'"></span>
                                </button>
                            </span>
                        </template>
                        <span class="text-xs" x-show="!loading && !error"
                              :class="saved ? 'text-gray-400' : 'text-amber-600'"
                              x-text="saved ? 'All changes saved' : 'Saving…'"></span>
                    </div>
                </div>

                <template x-if="isMobile && !error">
                    <div x-cloak class="rounded-xl border border-amber-200 bg-amber-50 p-8 text-center dark:border-amber-900/50 dark:bg-amber-950/30">
                        <svg class="mx-auto h-10 w-10 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="3" width="20" height="14" rx="2"></rect>
                            <line x1="8" y1="21" x2="16" y2="21"></line>
                            <line x1="12" y1="17" x2="12" y2="21"></line>
                        </svg>
                        <h4 class="mt-3 text-sm font-semibold text-amber-800 dark:text-amber-200">Larger screen required</h4>
                        <p class="mx-auto mt-1 max-w-sm text-xs text-amber-700 dark:text-amber-300">
                            The printed-template editor is only available on desktop and tablet devices.
                            Open this form on a wider screen to design its printed output.
                        </p>
                    </div>
                </template>

                <template x-if="loading">
                    <p class="py-10 text-center text-xs text-gray-400">Loading the document editor…</p>
                </template>

                <template x-if="error">
                    <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
                        <p class="font-medium">The document editor could not be loaded.</p>
                        <p class="mt-1" x-text="error"></p>
                        <p class="mt-2 text-red-500">
                            Check that the <code>onlyoffice</code> container is running and that
                            <code>ONLYOFFICE_PUBLIC_URL</code> is reachable from this browser.
                        </p>
                    </div>
                </template>

                <div x-show="!error && !isMobile" class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800"
                     style="height: calc(100vh - 13rem); min-height: 640px;">
                    <div id="onlyoffice-surface" class="h-full w-full"></div>
                </div>
            </div>
        @endif
    </div>
</div>
