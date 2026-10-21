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
            <div x-data="onlyofficeTemplateEditor({ csrf: @js(csrf_token()) })"
                @printed-template:sync.window="onSync($event.detail)"
                @printed-template:flush-request.window="onFlushRequest()"
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
                        <div class="relative" x-show="currentSlot" @click.outside="menuOpen = false" @keydown.escape="menuOpen = false">
                            <button type="button" @click="menuOpen = !menuOpen"
                                    :disabled="switching || importing || loading"
                                    :aria-expanded="menuOpen.toString()"
                                    class="inline-flex w-52 max-w-full items-center justify-between gap-2 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition-colors duration-150 hover:border-brand-400 hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                                <span class="min-w-0 flex-1 truncate text-left" x-text="currentSlotName"></span>
                                <svg class="h-4 w-4 shrink-0 text-gray-500 transition-transform duration-200 dark:text-gray-400"
                                     :class="menuOpen ? 'rotate-180' : ''" aria-hidden="true" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m6 9 6 6 6-6"></path>
                                </svg>
                            </button>
                            <div x-show="menuOpen" x-cloak
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                 x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                                 class="absolute right-0 z-30 mt-1 w-64 max-w-[calc(100vw-2rem)] origin-top-right rounded-lg border border-gray-200 bg-white p-1 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                                <template x-for="slot in slots" :key="slot.id">
                                    <div class="flex items-center gap-1 rounded-md transition-colors duration-150 hover:bg-gray-100 dark:hover:bg-gray-800">
                                        <button type="button" @click="switchSlot(slot)" :title="slot.name"
                                                :aria-current="slot.id === selectedSlotId ? 'true' : null"
                                                class="min-w-0 flex-1 truncate rounded-md px-3 py-2 text-left text-xs text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 dark:text-gray-200"
                                                :class="slot.id === selectedSlotId ? 'font-semibold text-brand-600' : ''"
                                                x-text="slot.name"></button>
                                        <button type="button" x-show="slots.length > 1" @click="removeSlot(slot)"
                                                :aria-label="'Remove ' + slot.name"
                                                class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-transparent text-gray-400 transition-colors duration-150 hover:bg-red-50 hover:text-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/30 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400">
                                            <svg class="h-4 w-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                                <path d="M18 6 6 18M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </template>
                                <div class="my-1 border-t border-gray-200 dark:border-gray-700"></div>
                                <input type="file" x-ref="addDocxInput" accept=".docx" class="hidden" @change="uploadAnother($event)">
                                <button type="button" @click="$refs.addDocxInput.click()" :disabled="slots.length >= 10"
                                        class="w-full rounded-md px-3 py-2 text-left text-xs font-medium text-gray-700 transition-colors duration-150 hover:bg-brand-50 hover:text-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 disabled:cursor-not-allowed disabled:opacity-50 dark:text-gray-200 dark:hover:bg-white/[0.05]">
                                    + Upload another .docx
                                </button>
                            </div>
                        </div>
                        <span class="flex items-center gap-3" x-show="importUrl">
                            <input type="file" x-ref="docxInput" accept=".docx"
                                   class="hidden" @change="importDocx($event)">
                            <button type="button" @click="$refs.docxInput.click()"
                                    :disabled="!saved || loading || importing || switching || !mounted"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-brand-400 hover:bg-brand-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                                    title="Replace the selected template with an uploaded Word document">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17 8 12 3 7 8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg>
                                <span x-text="importing ? 'Uploading…' : 'Replace .docx'"></span>
                            </button>
                        </span>
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
