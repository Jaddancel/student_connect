@props(['exportUrl', 'importUrl'])
@php
    $assetBase = rtrim(\Illuminate\Support\Facades\Storage::disk(config('documents.disk', 'public'))->url('/'), '/');
    // Flat list of universal fields for the "Universal fields" token palette.
    // `system` keys (current date/time/year, school year) are excluded: they're
    // resolved by FormRenderContext for live field autofill only — the printed-
    // PDF token resolver (PdfTemplateRenderer::universalNodes) doesn't handle
    // that source, so a token wouldn't render here.
    $universalTokens = [];
    foreach (\App\Support\UniversalField::groupedBySource('profile') + \App\Support\UniversalField::groupedBySource('org') as $group => $entries) {
        foreach ($entries as $ukey => $meta) {
            $universalTokens[] = ['key' => $ukey, 'label' => $meta['label'], 'group' => $group];
        }
    }
@endphp

{{--
    Wizard Step 2 — the separate printed-PDF template editor. Nested inside the
    formBuilder Alpine scope; `fields` and `pdf_template` are that scope's
    reactive objects passed by reference, so the palette mirrors Step 1's fields
    and edits flow into the wizard's single POST payload.
--}}
<div x-data="pdfTemplateEditor({
        getFields: () => fields,
        model: pdf_template,
        csrf: csrf,
        universalFields: {{ Js::from($universalTokens) }},
        uploadUrl: uploadUrl,
        assetBase: '{{ $assetBase }}',
        exportUrl: '{{ $exportUrl }}',
        importUrl: '{{ $importUrl }}',
    })"
    class="grid grid-cols-12 gap-5 lg:items-start">

    {{-- LEFT: field palette + page setup + DOCX (floats as the page scrolls) --}}
    <div class="col-span-12 space-y-5 lg:col-span-3 lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto">
        <div class="rounded-2xl border border-gray-200 bg-palette-surface p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="mb-1 text-sm font-semibold text-gray-800 dark:text-white/90">Field tokens</h3>
            <p class="mb-3 text-xs text-gray-400">Click or drag a field to drop it into the document.</p>
            <div class="space-y-2">
                <template x-for="field in printableFields" :key="field.field_key">
                    <button type="button"
                        draggable="true"
                        @click="insertField(field)"
                        @dragstart="onDragStart($event, field)"
                        class="flex w-full items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-left text-xs font-medium text-gray-700 transition hover:border-brand-400 hover:bg-brand-50 dark:border-gray-700 dark:text-gray-300">
                        <span class="text-gray-300">⠿</span>
                        <span class="min-w-0">
                            <span class="block truncate" x-text="field.field_label"></span>
                            <span class="block font-mono text-[10px] text-gray-400" x-text="field.field_key"></span>
                        </span>
                    </button>
                </template>
                <template x-if="printableFields.length === 0">
                    <p class="text-xs text-gray-400">No fillable fields yet — add some in Step 1.</p>
                </template>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-palette-surface p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="mb-1 text-sm font-semibold text-gray-800 dark:text-white/90">Universal fields</h3>
            <p class="mb-3 text-xs text-gray-400">Prints straight from the submitter's profile — no matching form field needed.</p>
            <div class="space-y-2">
                <template x-for="uf in universalFields" :key="uf.key">
                    <button type="button"
                        @click="insertUniversal(uf)"
                        class="flex w-full items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-left text-xs font-medium text-gray-700 transition hover:border-brand-400 hover:bg-brand-50 dark:border-gray-700 dark:text-gray-300">
                        <span class="text-gray-300">◈</span>
                        <span class="min-w-0">
                            <span class="block truncate" x-text="uf.label"></span>
                            <span class="block font-mono text-[10px] text-gray-400" x-text="uf.key"></span>
                        </span>
                    </button>
                </template>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-palette-surface p-4 dark:border-gray-800 dark:bg-white/[0.03] space-y-3">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Page setup</h3>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Paper size</label>
                <select x-model="model.page.size" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
                    <option value="a4">A4</option>
                    <option value="letter">Letter</option>
                    <option value="legal">Legal</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Orientation</label>
                <select x-model="model.page.orientation" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
                    <option value="portrait">Portrait</option>
                    <option value="landscape">Landscape</option>
                </select>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-palette-surface p-4 dark:border-gray-800 dark:bg-white/[0.03] space-y-3">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Header / letterhead</h3>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Header image</label>
                <input type="file" accept="image/*" @change="uploadImage($event, 'header', 'image')" class="w-full text-xs text-gray-500" />
                <template x-if="model.header && model.header.image">
                    <div class="mt-1 flex items-center gap-2">
                        <span class="truncate text-xs text-success-600" x-text="model.header.image"></span>
                        <button type="button" @click="removeImage('header', 'image')" class="text-xs text-error-500">remove</button>
                    </div>
                </template>
                <p class="mt-1 text-[10px] text-gray-400">Prints at the full page width (aspect ratio kept).</p>
            </div>
            <textarea x-model="model.header.title" rows="2" placeholder="Title"
                class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"></textarea>
            <textarea x-model="model.header.subtitle" rows="2" placeholder="Subtitle"
                class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"></textarea>
            <select x-model="model.header.align" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
                <option value="left">Align left</option>
                <option value="center">Align center</option>
                <option value="right">Align right</option>
            </select>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-palette-surface p-4 dark:border-gray-800 dark:bg-white/[0.03] space-y-3">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Footer</h3>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Footer image</label>
                <input type="file" accept="image/*" @change="uploadImage($event, 'footer', 'image')" class="w-full text-xs text-gray-500" />
                <template x-if="model.footer && model.footer.image">
                    <div class="mt-1 flex items-center gap-2">
                        <span class="truncate text-xs text-success-600" x-text="model.footer.image"></span>
                        <button type="button" @click="removeImage('footer', 'image')" class="text-xs text-error-500">remove</button>
                    </div>
                </template>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-palette-surface p-4 dark:border-gray-800 dark:bg-white/[0.03] space-y-2">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Word (.docx)</h3>
            <label class="block w-full cursor-pointer rounded-lg border border-dashed border-gray-300 px-3 py-2 text-center text-xs font-medium text-gray-600 transition hover:border-brand-400 hover:text-brand-600 dark:border-gray-700">
                <span x-show="!importing">Import .docx</span>
                <span x-show="importing">Importing…</span>
                <input type="file" accept=".docx" class="hidden" @change="importDocx($event)" :disabled="importing" />
            </label>
            <button type="button" @click="exportDocx()" :disabled="exporting"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-600 transition hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:text-gray-300">
                <span x-show="!exporting">Export .docx</span>
                <span x-show="exporting">Exporting…</span>
            </button>
            <p x-show="note" x-text="note" class="text-xs text-gray-400"></p>
        </div>
    </div>

    {{-- CENTER: toolbar + editable document --}}
    <div class="col-span-12 lg:col-span-9">
        <div class="rounded-2xl border border-gray-200 bg-palette-surface p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            {{-- toolbar (floats under the top bar as the page scrolls) --}}
            <div class="mb-3 flex flex-wrap items-center gap-1 border-b border-gray-200 pb-3 dark:border-gray-800 lg:sticky lg:top-20 lg:z-30 lg:bg-palette-surface lg:pt-1 dark:lg:bg-[#171f2f]">
                @php
                    $btn = 'rounded-md border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300';
                @endphp
                <select x-model="model.font.family" @change="sync()"
                    class="h-7 rounded-md border border-gray-300 bg-transparent px-1.5 text-xs text-gray-700 dark:border-gray-700 dark:text-gray-300"
                    title="Font">
                    <option value="Arial, Helvetica, sans-serif">Arial</option>
                    <option value="'Times New Roman', Times, serif">Times New Roman</option>
                    <option value="Georgia, serif">Georgia</option>
                    <option value="'Courier New', Courier, monospace">Courier</option>
                    <option value="'DejaVu Sans', sans-serif">DejaVu Sans</option>
                </select>
                <select x-model="model.font.size" @change="sync()"
                    class="h-7 rounded-md border border-gray-300 bg-transparent px-1.5 text-xs text-gray-700 dark:border-gray-700 dark:text-gray-300"
                    title="Font size">
                    @foreach ([10, 11, 12, 14, 16, 18, 20, 24, 28, 32] as $px)
                        <option value="{{ $px }}px">{{ $px }}pt</option>
                    @endforeach
                </select>
                <span class="mx-1 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>
                <button type="button" class="{{ $btn }} font-bold" @click="exec('bold')">B</button>
                <button type="button" class="{{ $btn }} italic" @click="exec('italic')">I</button>
                <button type="button" class="{{ $btn }} underline" @click="exec('underline')">U</button>
                <span class="mx-1 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>
                <button type="button" class="{{ $btn }}" @click="block('H1')">H1</button>
                <button type="button" class="{{ $btn }}" @click="block('H2')">H2</button>
                <button type="button" class="{{ $btn }}" @click="block('H3')">H3</button>
                <button type="button" class="{{ $btn }}" @click="block('P')">¶</button>
                <span class="mx-1 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>
                <button type="button" class="{{ $btn }}" @click="exec('justifyLeft')">⯇</button>
                <button type="button" class="{{ $btn }}" @click="exec('justifyCenter')">≡</button>
                <button type="button" class="{{ $btn }}" @click="exec('justifyRight')">⯈</button>
                <span class="mx-1 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>
                <button type="button" class="{{ $btn }}" @click="exec('insertUnorderedList')">• List</button>
                <button type="button" class="{{ $btn }}" @click="exec('insertOrderedList')">1. List</button>
                <span class="mx-1 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>
                {{-- Table insert (grid picker) --}}
                <div class="relative" x-data="{ open: false, r: 0, c: 0 }" @click.outside="open = false">
                    <button type="button" class="{{ $btn }}" @click="open = !open">▦ Table</button>
                    <div x-show="open" x-cloak class="absolute left-0 top-8 z-40 rounded-lg border border-gray-200 bg-white p-2 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                        <p class="mb-1 text-center text-[10px] text-gray-500" x-text="(r||1) + ' × ' + (c||1)"></p>
                        <div class="grid grid-cols-8 gap-0.5">
                            <template x-for="i in 64" :key="i">
                                @php /* i: 1..64 → row = ceil(i/8), col = ((i-1)%8)+1 */ @endphp
                                <div class="h-4 w-4 cursor-pointer rounded-sm border"
                                    :class="(Math.ceil(i/8) <= r && ((i-1)%8)+1 <= c) ? 'border-brand-400 bg-brand-200' : 'border-gray-200 dark:border-gray-700'"
                                    @mouseenter="r = Math.ceil(i/8); c = ((i-1)%8)+1"
                                    @click="insertTable(Math.ceil(i/8), ((i-1)%8)+1); open = false"></div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Contextual table toolbar (appears when the caret is inside a table) --}}
            <div x-show="tableActive" x-cloak class="mb-3 flex flex-wrap items-center gap-1 rounded-lg border border-brand-200 bg-brand-50/40 p-2 dark:border-brand-500/30 dark:bg-brand-500/5">
                <span class="mr-1 text-[10px] font-semibold uppercase text-brand-600 dark:text-brand-400">Table</span>
                <button type="button" class="{{ $btn }}" @click="tableAction('rowAbove')">+Row ↑</button>
                <button type="button" class="{{ $btn }}" @click="tableAction('rowBelow')">+Row ↓</button>
                <button type="button" class="{{ $btn }}" @click="tableAction('colLeft')">+Col ←</button>
                <button type="button" class="{{ $btn }}" @click="tableAction('colRight')">+Col →</button>
                <span class="mx-1 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>
                <button type="button" class="{{ $btn }}" @click="tableAction('delRow')">−Row</button>
                <button type="button" class="{{ $btn }}" @click="tableAction('delCol')">−Col</button>
                <button type="button" class="{{ $btn }}" @click="tableAction('delTable')">Delete table</button>
                <span class="mx-1 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>
                <button type="button" class="{{ $btn }}" @click="tableAction('merge')" title="Merge selected cells">Merge</button>
                <button type="button" class="{{ $btn }}" @click="tableAction('split')" title="Split cell">Split</button>
                <span class="mx-1 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>
                <label class="flex items-center gap-1 text-[10px] text-gray-500">Border
                    <input type="number" min="0" max="6" value="1" class="h-6 w-12 rounded border border-gray-300 bg-transparent px-1 text-xs dark:border-gray-700"
                        @change="tableAction('border', $event.target.value)" />px</label>
                <label class="flex items-center gap-1 text-[10px] text-gray-500">Cell bg
                    <input type="color" class="h-6 w-8 rounded border border-gray-300" @change="tableAction('bg', $event.target.value)" /></label>
                <label class="flex items-center gap-1 text-[10px] text-gray-500">Col width
                    <input type="number" min="5" max="100" class="h-6 w-14 rounded border border-gray-300 bg-transparent px-1 text-xs dark:border-gray-700"
                        @change="tableAction('colWidth', $event.target.value)" />%</label>
                <label class="flex items-center gap-1 text-[10px] text-gray-500">Row ht
                    <input type="number" min="10" max="400" class="h-6 w-14 rounded border border-gray-300 bg-transparent px-1 text-xs dark:border-gray-700"
                        @change="tableAction('rowHeight', $event.target.value)" />px</label>
            </div>

            {{-- the document surface (looks like a page): letterhead, body, footer --}}
            <div class="flex justify-center overflow-auto rounded-xl bg-gray-100 p-6 dark:bg-white/[0.02]">
                <div class="w-full max-w-[794px] rounded-sm bg-white shadow"
                    :style="{ fontFamily: model.font.family, fontSize: model.font.size }">

                    {{-- live letterhead preview. The separator is a BROKEN line in
                         the editor only — it never prints in the exported PDF. --}}
                    <template x-if="model.header && (model.header.image || model.header.logo || model.header.title || model.header.subtitle)">
                        <div class="border-b border-dashed border-gray-300 px-12 pt-10 pb-4"
                            :class="{
                                'text-left': model.header.align === 'left',
                                'text-center': (model.header.align || 'center') === 'center',
                                'text-right': model.header.align === 'right',
                            }">
                            {{-- Header image prints at full page width, aspect kept. --}}
                            <template x-if="model.header.image || model.header.logo">
                                <img :src="imageUrl(model.header.image || model.header.logo)" alt="header" class="mb-2 w-full object-contain" />
                            </template>
                            <div class="whitespace-pre-line text-base font-bold uppercase text-gray-900" x-text="model.header.title"></div>
                            <div class="whitespace-pre-line text-sm text-gray-700" x-text="model.header.subtitle"></div>
                        </div>
                    </template>

                    {{-- editable body --}}
                    <div x-ref="surface"
                        contenteditable="true"
                        @input="sync()"
                        @blur="sync()"
                        @drop="onDrop($event)"
                        @dragover.prevent
                        @keydown.tab.prevent="onTab($event)"
                        @mouseup="refreshTableState()"
                        @keyup="refreshTableState()"
                        class="pdf-template-surface min-h-[500px] px-12 py-10 leading-relaxed focus:outline-none"></div>

                    {{-- live footer preview --}}
                    <template x-if="model.footer && model.footer.image">
                        <div class="border-t border-gray-200 px-12 pb-8 pt-4 text-center">
                            <img :src="imageUrl(model.footer.image)" alt="footer" class="mx-auto max-h-24 w-full object-contain" />
                        </div>
                    </template>
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-400">The letterhead and footer above print on every page. Field tokens print the submitted answer; renaming a field label later won't break the template — tokens resolve by key.</p>
        </div>
    </div>
</div>
