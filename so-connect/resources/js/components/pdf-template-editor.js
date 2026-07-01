/**
 * Alpine component backing the wizard's Step 2 — the separate printed-PDF
 * template editor.
 *
 * It wraps a `contenteditable` document surface with a formatting toolbar and a
 * field palette derived from Step 1's fields. Clicking (or dragging) a field
 * inserts an inline **token chip**:
 *
 *     <span class="field-token" data-field="{key}" contenteditable="false">{Label}</span>
 *
 * Tokens carry no value; {@see \App\Forms\PdfTemplateRenderer} replaces them with
 * the submission's answers at generation time. The surface's sanitized innerHTML
 * is written back into the shared `model.html` (the parent wizard's
 * `pdf_template.html`), so the parent's single POST persists it.
 *
 * `config.fields` and `config.model` are the parent wizard's reactive objects,
 * passed by reference, so the palette stays in sync with Step 1 and edits flow
 * straight into the payload.
 */
const ALLOWED_TAGS = new Set([
    'H1', 'H2', 'H3', 'P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U',
    'UL', 'OL', 'LI', 'SPAN', 'DIV',
]);

export function pdfTemplateEditor(config) {
    return {
        fields: config.fields,
        model: config.model,
        csrf: config.csrf,
        exportUrl: config.exportUrl,
        importUrl: config.importUrl,
        uploadUrl: config.uploadUrl,
        assetBase: config.assetBase || '/storage',
        importing: false,
        exporting: false,
        note: '',

        init() {
            // The letterhead/footer/font live on the printed document, so default
            // their containers here in case an older template lacks them.
            if (!this.model.header) this.model.header = { align: 'center' };
            if (!this.model.footer) this.model.footer = {};
            if (!this.model.font) this.model.font = { family: "'Times New Roman', Times, serif", size: '12px' };

            const surface = this.$refs.surface;
            if (surface) {
                surface.innerHTML = this.model.html || '';
            }
        },

        /** Absolute URL for a disk-relative uploaded asset (public disk). */
        imageUrl(path) {
            if (!path) return '';
            if (/^(https?:|data:|\/)/.test(path)) return path;
            return `${this.assetBase}/${path}`.replace(/([^:])\/\//g, '$1/');
        },

        /** Fields eligible to print (everything except pure layout blocks). */
        get printableFields() {
            return (this.fields || []).filter(
                (f) => !['heading', 'static-text'].includes(f.field_type),
            );
        },

        // --- toolbar ---
        exec(command, value = null) {
            this.$refs.surface.focus();
            document.execCommand(command, false, value);
            this.sync();
        },

        block(tag) {
            this.exec('formatBlock', tag);
        },

        // --- token insertion ---
        insertField(field) {
            const surface = this.$refs.surface;
            surface.focus();

            const chip = document.createElement('span');
            chip.className = 'field-token';
            chip.setAttribute('data-field', field.field_key);
            chip.setAttribute('contenteditable', 'false');
            chip.textContent = field.field_label || field.field_key;

            this.insertNodeAtCaret(surface, chip);
            // A trailing space keeps the caret editable after the chip.
            this.insertNodeAtCaret(surface, document.createTextNode(' '));
            this.sync();
        },

        insertNodeAtCaret(surface, node) {
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0 || !surface.contains(sel.anchorNode)) {
                surface.appendChild(node);
                return;
            }
            const range = sel.getRangeAt(0);
            range.deleteContents();
            range.insertNode(node);
            range.setStartAfter(node);
            range.setEndAfter(node);
            sel.removeAllRanges();
            sel.addRange(range);
        },

        // --- drag support (native HTML5 drag from palette) ---
        onDragStart(event, field) {
            event.dataTransfer.setData('application/x-field-key', field.field_key);
            event.dataTransfer.effectAllowed = 'copy';
        },

        onDrop(event) {
            const key = event.dataTransfer.getData('application/x-field-key');
            if (!key) return;
            event.preventDefault();
            const field = (this.fields || []).find((f) => f.field_key === key);
            if (!field) return;
            // Place caret at the drop point when the browser supports it.
            if (document.caretRangeFromPoint) {
                const range = document.caretRangeFromPoint(event.clientX, event.clientY);
                if (range) {
                    const sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
            }
            this.insertField(field);
        },

        // --- letterhead / footer image upload ---
        async uploadImage(event, section, slot) {
            const file = event.target.files[0];
            event.target.value = '';
            if (!file) return;
            const body = new FormData();
            body.append('asset', file);
            body.append('_token', this.csrf);
            try {
                const res = await fetch(this.uploadUrl, { method: 'POST', body });
                const json = await res.json();
                if (res.ok && json.path) {
                    if (!this.model[section]) this.model[section] = {};
                    this.model[section][slot] = json.path;
                } else {
                    this.note = json.message || 'Upload failed.';
                }
            } catch (e) {
                this.note = 'Upload failed.';
            }
        },

        removeImage(section, slot) {
            if (this.model[section]) this.model[section][slot] = '';
        },

        // --- serialization ---
        sync() {
            this.model.html = this.sanitize(this.$refs.surface.innerHTML);
        },

        /** Best-effort client sanitize; the server re-sanitizes authoritatively. */
        sanitize(html) {
            const doc = new DOMParser().parseFromString(
                `<div id="root">${html}</div>`, 'text/html',
            );
            const root = doc.getElementById('root');
            this.scrub(root);
            return root.innerHTML;
        },

        scrub(node) {
            [...node.children].forEach((el) => {
                if (!ALLOWED_TAGS.has(el.tagName)) {
                    // Unwrap unknown elements, keeping their text content.
                    el.replaceWith(...el.childNodes);
                    return;
                }
                // Whitelist attributes.
                [...el.attributes].forEach((attr) => {
                    const name = attr.name.toLowerCase();
                    if (name === 'style') {
                        const clean = this.cleanStyle(attr.value);
                        if (clean) el.setAttribute('style', clean);
                        else el.removeAttribute('style');
                        return;
                    }
                    const ok = name === 'data-field'
                        || name === 'contenteditable'
                        || (name === 'class' && el.classList.contains('field-token'));
                    if (!ok) el.removeAttribute(attr.name);
                });
                this.scrub(el);
            });
        },

        /** Keep only alignment + font styling from an inline style attribute. */
        cleanStyle(style) {
            const allowed = ['text-align', 'font-size', 'font-family'];
            return (style || '').split(';')
                .map((d) => d.trim())
                .filter((d) => d.includes(':'))
                .map((d) => {
                    const idx = d.indexOf(':');
                    const prop = d.slice(0, idx).trim().toLowerCase();
                    const val = d.slice(idx + 1).trim().replace(/[^a-z0-9 ,.'"%\-]/gi, '');
                    return allowed.includes(prop) && val ? `${prop}: ${val}` : null;
                })
                .filter(Boolean)
                .join('; ');
        },

        // --- DOCX interchange ---
        async exportDocx() {
            this.exporting = true;
            this.note = '';
            try {
                this.sync();
                const res = await fetch(this.exportUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    body: JSON.stringify({ html: this.model.html }),
                });
                if (!res.ok) {
                    this.note = 'Export failed.';
                    return;
                }
                const blob = await res.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'form-template.docx';
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            } catch (e) {
                this.note = 'Export failed.';
            } finally {
                this.exporting = false;
            }
        },

        async importDocx(event) {
            const file = event.target.files[0];
            event.target.value = '';
            if (!file) return;
            if (!confirm('Replace the current template with the imported document?')) return;

            this.importing = true;
            this.note = '';
            try {
                const body = new FormData();
                body.append('docx', file);
                body.append('_token', this.csrf);
                // Send current field keys so the server can map {{key}} back to chips.
                body.append('fields', JSON.stringify(
                    (this.fields || []).map((f) => ({ key: f.field_key, label: f.field_label })),
                ));
                const res = await fetch(this.importUrl, { method: 'POST', body });
                const json = await res.json();
                if (res.ok && typeof json.html === 'string') {
                    this.$refs.surface.innerHTML = json.html;
                    this.sync();
                    this.note = 'Imported.';
                } else {
                    this.note = json.message || 'Import failed.';
                }
            } catch (e) {
                this.note = 'Import failed.';
            } finally {
                this.importing = false;
            }
        },
    };
}
