@props(['configUrl' => null])

{{--
    Wizard Step 2 — the printed template, edited in OnlyOffice.

    Replaces the old contenteditable rich-text editor. The document itself is a
    real .docx (see FormPrintTemplateService), so what the admin lays out here
    is what DocxTemplateService::populate() later fills in — no HTML round-trip
    in between.

    The field-token palette lives inside the editor as a Document Server plugin
    rather than in this page: Community Edition has no Automation API, so the
    host page cannot insert content into the open document.

    A form that has not been saved yet has no id, and therefore no document to
    open — the wizard shows the notice below instead.
--}}
<div class="grid grid-cols-12 gap-5">
    <div class="col-span-12">
        @if ($configUrl === null)
            <div class="rounded-2xl border border-dashed border-gray-300 bg-palette-surface p-10 text-center dark:border-gray-700 dark:bg-white/[0.03]">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Save the form first</h3>
                <p class="mx-auto mt-2 max-w-md text-xs text-gray-500 dark:text-gray-400">
                    The printed template is a Word document attached to this form, so the form
                    needs to exist before it can be edited. Finish Step 3 and save — then reopen
                    the form to design its printed output.
                </p>
            </div>
        @else
            <div x-data="{
                    configUrl: '{{ $configUrl }}',
                    mounted: false,
                    loading: false,
                    error: '',
                    saved: true,

                    start() {
                        if (this.mounted) return;
                        this.mounted = true;
                        this.loading = true;
                        this.boot().catch((e) => { this.error = e.message || String(e); })
                                   .finally(() => { this.loading = false; });
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

                        new window.DocsAPI.DocEditor('onlyoffice-surface', Object.assign({}, data.config, {
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
                }"
                x-init="$watch('step', (value) => { if (value === 2) start(); }); if (step === 2) start();"
                class="rounded-2xl border border-gray-200 bg-palette-surface p-4 dark:border-gray-800 dark:bg-white/[0.03]">

                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Printed template</h3>
                        <p class="text-xs text-gray-400">
                            Insert form fields from the <span class="font-medium">Field tokens</span> panel
                            inside the editor. Changes save automatically.
                        </p>
                    </div>
                    <span class="shrink-0 text-xs" x-show="!loading && !error"
                          :class="saved ? 'text-gray-400' : 'text-amber-600'"
                          x-text="saved ? 'All changes saved' : 'Saving…'"></span>
                </div>

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

                <div x-show="!error" class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800"
                     style="height: calc(100vh - 20rem); min-height: 520px;">
                    <div id="onlyoffice-surface" class="h-full w-full"></div>
                </div>
            </div>
        @endif
    </div>
</div>
