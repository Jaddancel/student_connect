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
 * `config.getFields` is a live getter onto the parent wizard's reactive `fields`
 * array (NOT a snapshot: the parent reassigns `fields` on remove, so a captured
 * reference would go stale), and `config.model` is the parent's reactive
 * `pdf_template` object passed by reference — so the palette stays in sync with
 * Step 1 and edits flow straight into the payload.
 */
const ALLOWED_TAGS = new Set([
    'H1', 'H2', 'H3', 'P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U',
    'UL', 'OL', 'LI', 'SPAN', 'DIV',
    'TABLE', 'THEAD', 'TBODY', 'TFOOT', 'TR', 'TD', 'TH', 'COLGROUP', 'COL',
]);

const TABLE_STYLE_PROPS = [
    'text-align', 'font-size', 'font-family', 'width', 'height',
    'border', 'border-width', 'border-style', 'border-color', 'border-collapse',
    'padding', 'background-color', 'vertical-align',
];

export function pdfTemplateEditor(config) {
    return {
        // Live getter onto the parent's `fields` (see file header). Falls back to
        // an empty list if omitted so the palette simply renders nothing.
        getFields: config.getFields || (() => []),
        universalFields: config.universalFields || [],
        model: config.model,
        csrf: config.csrf,
        exportUrl: config.exportUrl,
        importUrl: config.importUrl,
        uploadUrl: config.uploadUrl,
        assetBase: config.assetBase || '/storage',
        importing: false,
        exporting: false,
        note: '',
        tableActive: false, // caret is inside a table
        _selAnchor: null, // first cell of a drag-selection

        init() {
            // Letterhead: migrate a legacy small "logo" into the single header
            // image, and default containers older templates may lack.
            if (!this.model.header) this.model.header = { align: 'center' };
            if (!this.model.header.image && this.model.header.logo) {
                this.model.header.image = this.model.header.logo;
            }
            if (this.model.header.logo) delete this.model.header.logo;
            if (!this.model.footer) this.model.footer = {};
            if (!this.model.font) this.model.font = { family: "'Times New Roman', Times, serif", size: '12px' };

            const surface = this.$refs.surface;
            if (surface) {
                surface.innerHTML = this.model.html || '';
                this.bindCellSelection(surface);
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
            return (this.getFields() || []).filter(
                (f) => f && f.field_key && !['heading', 'static-text'].includes(f.field_type),
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

        // --- tables ------------------------------------------------------------

        /** The table cell (td/th) containing the current selection, or null. */
        currentCell() {
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return null;
            let node = sel.anchorNode;
            while (node && node !== this.$refs.surface) {
                if (node.nodeType === 1 && (node.tagName === 'TD' || node.tagName === 'TH')) return node;
                node = node.parentNode;
            }
            return null;
        },

        currentTable() {
            const cell = this.currentCell();
            return cell ? cell.closest('table') : null;
        },

        refreshTableState() {
            this.tableActive = !!this.currentTable();
        },

        insertTable(rows, cols) {
            rows = Math.max(1, Math.min(20, rows | 0));
            cols = Math.max(1, Math.min(8, cols | 0));
            const table = document.createElement('table');
            table.setAttribute('style', 'border-collapse: collapse; width: 100%;');
            const tbody = document.createElement('tbody');
            for (let r = 0; r < rows; r++) {
                const tr = document.createElement('tr');
                for (let c = 0; c < cols; c++) {
                    const td = document.createElement('td');
                    td.setAttribute('style', 'border: 1px solid #000; padding: 4px;');
                    td.innerHTML = '<br>';
                    tr.appendChild(td);
                }
                tbody.appendChild(tr);
            }
            table.appendChild(tbody);

            this.$refs.surface.focus();
            this.insertNodeAtCaret(this.$refs.surface, table);
            // A trailing paragraph keeps the caret editable after the table.
            this.insertNodeAtCaret(this.$refs.surface, document.createElement('p'));
            this.bindCellSelection(this.$refs.surface);
            this.refreshTableState();
            this.sync();
        },

        tableAction(action, value = null) {
            const cell = this.currentCell();
            const table = cell ? cell.closest('table') : null;
            if (!table && action !== 'delTable') return;

            const selected = this.selectedCells(table);
            const targets = selected.length ? selected : (cell ? [cell] : []);

            switch (action) {
                case 'rowAbove': this.addRow(cell, 'above'); break;
                case 'rowBelow': this.addRow(cell, 'below'); break;
                case 'colLeft': this.addColumn(table, this.cellColumnIndex(cell), 'left'); break;
                case 'colRight': this.addColumn(table, this.cellColumnIndex(cell), 'right'); break;
                case 'delRow': { const tr = cell?.closest('tr'); if (tr && tr.parentNode.children.length > 1) tr.remove(); break; }
                case 'delCol': this.deleteColumn(table, this.cellColumnIndex(cell)); break;
                case 'delTable': { const t = this.currentTable(); if (t) t.remove(); break; }
                case 'merge': this.mergeCells(table, selected); break;
                case 'split': this.splitCell(cell); break;
                case 'border': targets.forEach((c) => this.setCellStyle(c, 'border', `${parseInt(value, 10) || 0}px solid #000`)); break;
                case 'bg': targets.forEach((c) => this.setCellStyle(c, 'background-color', value)); break;
                case 'colWidth': targets.forEach((c) => this.setCellStyle(c, 'width', `${parseInt(value, 10) || 10}%`)); break;
                case 'rowHeight': { const tr = cell?.closest('tr'); if (tr) [...tr.children].forEach((c) => this.setCellStyle(c, 'height', `${parseInt(value, 10) || 20}px`)); break; }
            }
            this.clearCellSelection();
            this.refreshTableState();
            this.sync();
        },

        setCellStyle(cell, prop, val) {
            cell.style[prop.replace(/-([a-z])/g, (_, c) => c.toUpperCase())] = val;
        },

        addRow(cell, where) {
            const tr = cell?.closest('tr');
            if (!tr) return;
            const clone = tr.cloneNode(true);
            [...clone.children].forEach((c) => { c.innerHTML = '<br>'; c.removeAttribute('colspan'); c.removeAttribute('rowspan'); });
            if (where === 'above') tr.parentNode.insertBefore(clone, tr);
            else tr.parentNode.insertBefore(clone, tr.nextSibling);
        },

        cellColumnIndex(cell) {
            if (!cell) return 0;
            let idx = 0;
            let sib = cell.previousElementSibling;
            while (sib) { idx += parseInt(sib.getAttribute('colspan') || '1', 10); sib = sib.previousElementSibling; }
            return idx;
        },

        addColumn(table, colIndex, side) {
            if (!table) return;
            [...table.rows].forEach((row) => {
                let idx = 0;
                let ref = null;
                for (const c of row.cells) {
                    const span = parseInt(c.getAttribute('colspan') || '1', 10);
                    if (idx <= colIndex && colIndex < idx + span) { ref = c; break; }
                    idx += span;
                }
                const td = document.createElement(row.parentNode.tagName === 'THEAD' ? 'th' : 'td');
                td.setAttribute('style', 'border: 1px solid #000; padding: 4px;');
                td.innerHTML = '<br>';
                if (ref) row.insertBefore(td, side === 'left' ? ref : ref.nextSibling);
                else row.appendChild(td);
            });
        },

        deleteColumn(table, colIndex) {
            if (!table) return;
            [...table.rows].forEach((row) => {
                if (row.cells.length <= 1) return;
                let idx = 0;
                for (const c of [...row.cells]) {
                    const span = parseInt(c.getAttribute('colspan') || '1', 10);
                    if (idx <= colIndex && colIndex < idx + span) { c.remove(); break; }
                    idx += span;
                }
            });
        },

        // --- cell selection (browsers don't natively select TDs) ---
        bindCellSelection(surface) {
            if (surface._cellSelBound) return;
            surface._cellSelBound = true;
            surface.addEventListener('mousedown', (e) => {
                const td = e.target.closest && e.target.closest('td,th');
                this.clearCellSelection();
                this._selAnchor = (td && surface.contains(td)) ? td : null;
            });
            surface.addEventListener('mouseover', (e) => {
                if (!this._selAnchor || e.buttons !== 1) return;
                const td = e.target.closest && e.target.closest('td,th');
                if (!td || td.closest('table') !== this._selAnchor.closest('table')) return;
                this.markRectangle(this._selAnchor, td);
            });
        },

        markRectangle(a, b) {
            const table = a.closest('table');
            if (!table) return;
            this.clearCellSelection();
            const grid = this.buildGrid(table);
            const pa = this.cellPos(grid, a);
            const pb = this.cellPos(grid, b);
            if (!pa || !pb) return;
            const r1 = Math.min(pa.r, pb.r), r2 = Math.max(pa.r, pb.r);
            const c1 = Math.min(pa.c, pb.c), c2 = Math.max(pa.c, pb.c);
            const seen = new Set();
            for (let r = r1; r <= r2; r++) {
                for (let c = c1; c <= c2; c++) {
                    const cell = grid[r] && grid[r][c];
                    if (cell && !seen.has(cell)) { seen.add(cell); cell.classList.add('cell-selected'); }
                }
            }
        },

        selectedCells(table) {
            return table ? [...table.querySelectorAll('td.cell-selected, th.cell-selected')] : [];
        },

        clearCellSelection() {
            this.$refs.surface.querySelectorAll('.cell-selected').forEach((c) => c.classList.remove('cell-selected'));
        },

        /** Grid map accounting for existing colspan/rowspan: grid[r][c] = cell. */
        buildGrid(table) {
            const grid = [];
            [...table.rows].forEach((row, r) => {
                if (!grid[r]) grid[r] = [];
                let c = 0;
                [...row.cells].forEach((cell) => {
                    while (grid[r][c]) c++;
                    const cs = parseInt(cell.getAttribute('colspan') || '1', 10);
                    const rs = parseInt(cell.getAttribute('rowspan') || '1', 10);
                    for (let dr = 0; dr < rs; dr++) {
                        for (let dc = 0; dc < cs; dc++) {
                            if (!grid[r + dr]) grid[r + dr] = [];
                            grid[r + dr][c + dc] = cell;
                        }
                    }
                    c += cs;
                });
            });
            return grid;
        },

        cellPos(grid, cell) {
            for (let r = 0; r < grid.length; r++) {
                for (let c = 0; c < (grid[r] || []).length; c++) {
                    if (grid[r][c] === cell) return { r, c };
                }
            }
            return null;
        },

        mergeCells(table, cells) {
            if (!table || cells.length < 2) return;
            const grid = this.buildGrid(table);
            let minR = Infinity, minC = Infinity, maxR = -1, maxC = -1;
            const set = new Set(cells);
            cells.forEach((cell) => {
                const p = this.cellPos(grid, cell);
                if (!p) return;
                const cs = parseInt(cell.getAttribute('colspan') || '1', 10);
                const rs = parseInt(cell.getAttribute('rowspan') || '1', 10);
                minR = Math.min(minR, p.r); minC = Math.min(minC, p.c);
                maxR = Math.max(maxR, p.r + rs - 1); maxC = Math.max(maxC, p.c + cs - 1);
            });
            const anchor = grid[minR][minC];
            const html = [];
            for (let r = minR; r <= maxR; r++) {
                for (let c = minC; c <= maxC; c++) {
                    const cell = grid[r] && grid[r][c];
                    if (cell && cell !== anchor && set.has(cell) && cell.parentNode) {
                        if (cell.innerHTML.replace(/<br\s*\/?>/gi, '').trim()) html.push(cell.innerHTML);
                        cell.remove();
                    }
                }
            }
            anchor.setAttribute('colspan', String(maxC - minC + 1));
            anchor.setAttribute('rowspan', String(maxR - minR + 1));
            if (html.length) anchor.innerHTML = [anchor.innerHTML, ...html].join(' ');
        },

        splitCell(cell) {
            if (!cell) return;
            const cs = parseInt(cell.getAttribute('colspan') || '1', 10);
            const rs = parseInt(cell.getAttribute('rowspan') || '1', 10);
            cell.removeAttribute('colspan');
            cell.removeAttribute('rowspan');
            // Re-insert the cells the merge had absorbed (blank) on this row.
            for (let i = 1; i < cs; i++) {
                const td = document.createElement('td');
                td.setAttribute('style', 'border: 1px solid #000; padding: 4px;');
                td.innerHTML = '<br>';
                cell.parentNode.insertBefore(td, cell.nextSibling);
            }
            // Rowspan splits: add a blank cell to each spanned row below.
            if (rs > 1) {
                let tr = cell.closest('tr');
                for (let i = 1; i < rs; i++) {
                    tr = tr.nextElementSibling;
                    if (!tr) break;
                    const td = document.createElement('td');
                    td.setAttribute('style', 'border: 1px solid #000; padding: 4px;');
                    td.innerHTML = '<br>';
                    tr.insertBefore(td, tr.firstChild);
                }
            }
        },

        // --- Tab handling (template surface only) ---
        onTab(event) {
            const cell = this.currentCell();
            if (cell) {
                const cells = [...cell.closest('table').querySelectorAll('td,th')];
                const i = cells.indexOf(cell);
                if (event.shiftKey) {
                    if (i > 0) this.focusCell(cells[i - 1]);
                } else if (i < cells.length - 1) {
                    this.focusCell(cells[i + 1]);
                } else {
                    // Tab on the last cell adds a new row (Word behaviour).
                    this.addRow(cell, 'below');
                    const newRow = cell.closest('tr').nextElementSibling;
                    if (newRow) this.focusCell(newRow.cells[0]);
                }
                this.refreshTableState();
                this.sync();
                return;
            }
            // Outside a table: insert an indent (four non-breaking spaces).
            document.execCommand('insertHTML', false, '    ');
            this.sync();
        },

        focusCell(cell) {
            if (!cell) return;
            const range = document.createRange();
            range.selectNodeContents(cell);
            range.collapse(true);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
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

        /** Insert a universal token that prints straight from the profile. */
        insertUniversal(uf) {
            const surface = this.$refs.surface;
            surface.focus();

            const chip = document.createElement('span');
            chip.className = 'field-token';
            chip.setAttribute('data-universal', uf.key);
            chip.setAttribute('contenteditable', 'false');
            chip.textContent = uf.label || uf.key;

            this.insertNodeAtCaret(surface, chip);
            this.insertNodeAtCaret(surface, document.createTextNode(' '));
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
            const field = (this.getFields() || []).find((f) => f.field_key === key);
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
                        || name === 'data-universal'
                        || name === 'data-field-rows'
                        || name === 'data-col'
                        || name === 'contenteditable'
                        || (name === 'colspan' && this.isIntAttr(attr.value))
                        || (name === 'rowspan' && this.isIntAttr(attr.value))
                        || (name === 'class' && el.classList.contains('field-token'));
                    if (!ok) el.removeAttribute(attr.name);
                });
                this.scrub(el);
            });
        },

        isIntAttr(v) {
            return /^[1-9][0-9]?$/.test(String(v || '').trim());
        },

        /** Keep alignment/font styling plus table styling from an inline style. */
        cleanStyle(style) {
            return (style || '').split(';')
                .map((d) => d.trim())
                .filter((d) => d.includes(':'))
                .map((d) => {
                    const idx = d.indexOf(':');
                    const prop = d.slice(0, idx).trim().toLowerCase();
                    const val = d.slice(idx + 1).trim().replace(/[^a-z0-9 ,.'"%#\-()]/gi, '');
                    return TABLE_STYLE_PROPS.includes(prop) && val ? `${prop}: ${val}` : null;
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
                    (this.getFields() || []).map((f) => ({ key: f.field_key, label: f.field_label })),
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
