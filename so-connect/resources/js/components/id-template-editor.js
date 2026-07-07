/**
 * Alpine component backing the SuperAdmin ID-template editor.
 *
 * A reference ID image is drawn into a Konva stage at a fixed display width;
 * the SuperAdmin draws/resizes named rectangular "zones" over ID features. The
 * single hard problem here is coordinate fidelity: zones are PERSISTED in native
 * image pixels but MANIPULATED in display pixels, so we convert only at the
 * boundary (see serialize()/buildStage()).
 *
 * Konva objects must never pass through Alpine's reactive Proxy (it wraps them
 * and breaks internal identity checks), so every Konva ref lives in this
 * closure — NOT on the returned reactive object.
 */
import Konva from 'konva';

const MAX_DISPLAY_WIDTH = 900;

export function idTemplateEditor(config) {
    // --- non-reactive Konva state (closure-scoped, never proxied) ---
    let stage = null;
    let layer = null;
    let transformer = null;
    let imageNode = null;
    const rects = []; // Konva.Rect[], index-aligned with `this.zones`
    let scale = 1; // naturalWidth / displayWidth
    let displayWidth = 0;

    const data = config.data || {};

    return {
        // --- reactive state ---
        id: data.id || null,
        name: data.name || '',
        isActive: data.is_active ?? true,
        isDefault: data.is_default ?? false,
        imagePath: data.image_path || '',
        naturalWidth: data.image_width || 0,
        naturalHeight: data.image_height || 0,
        zones: (data.zones || []).map((z) => ({
            name: z.name || '',
            label: z.label || '',
            field: z.field || 'student_id',
            regex: z.regex || '',
        })),
        selectedIndex: null,
        note: '',
        saving: false,

        // --- config ---
        storeUrl: config.storeUrl,
        updateUrl: config.updateUrl,
        uploadUrl: config.uploadUrl,
        csrf: config.csrf,
        assetBase: config.assetBase || '/storage',

        // Native coords loaded from the server, consumed once when the stage is
        // (re)built. Editing thereafter reads geometry from the Konva rects.
        _pendingCoords: (data.zones || []).map((z) => ({
            x1: z.x1 || 0, y1: z.y1 || 0, x2: z.x2 || 0, y2: z.y2 || 0,
        })),

        init() {
            if (this.imagePath) {
                this.loadImage(this.imageUrl(this.imagePath));
            }
        },

        /** Absolute URL for a disk-relative uploaded asset (public disk). */
        imageUrl(path) {
            if (!path) return '';
            if (/^(https?:|data:|\/)/.test(path)) return path;
            return `${this.assetBase}/${path}`.replace(/([^:])\/\//g, '$1/');
        },

        // --- reference image upload ---
        async uploadImage(event) {
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
                    this.imagePath = json.path;
                    // A brand-new image invalidates any drawn zones.
                    this._pendingCoords = [];
                    this.zones = [];
                    this.loadImage(this.imageUrl(json.path));
                } else {
                    this.note = json.message || 'Upload failed.';
                }
            } catch (e) {
                this.note = 'Upload failed.';
            }
        },

        /** Load the image, then build the stage only once it has decoded. */
        loadImage(url) {
            const img = new Image();
            img.onload = async () => {
                // naturalWidth is 0 until the bitmap is decoded — awaiting decode
                // guarantees a valid scale (else scale = Infinity).
                try { await img.decode(); } catch (e) { /* older browsers */ }
                this.naturalWidth = img.naturalWidth;
                this.naturalHeight = img.naturalHeight;
                this.buildStage(img);
            };
            img.src = url;
        },

        buildStage(img) {
            const container = this.$refs.stage;
            if (!container || !this.naturalWidth) return;

            if (stage) { stage.destroy(); rects.length = 0; }

            // Lock the display width at build time; never reflow on resize (that
            // would change `scale` and drift the saved coordinates).
            displayWidth = Math.min(container.clientWidth || MAX_DISPLAY_WIDTH, MAX_DISPLAY_WIDTH);
            scale = this.naturalWidth / displayWidth;
            const displayHeight = (displayWidth * this.naturalHeight) / this.naturalWidth;

            stage = new Konva.Stage({ container, width: displayWidth, height: displayHeight });
            layer = new Konva.Layer();
            stage.add(layer);

            imageNode = new Konva.Image({ image: img, width: displayWidth, height: displayHeight, listening: true });
            layer.add(imageNode);

            transformer = new Konva.Transformer({
                rotateEnabled: false,
                borderStroke: '#2563eb',
                anchorStroke: '#2563eb',
                anchorSize: 8,
            });
            layer.add(transformer);

            // Rebuild rects from the loaded native coords (native -> display).
            this._pendingCoords.forEach((c, i) => {
                this._addRect(c.x1 / scale, c.y1 / scale, (c.x2 - c.x1) / scale, (c.y2 - c.y1) / scale, i);
            });

            stage.on('click tap', (e) => {
                if (e.target === stage || e.target === imageNode) this.selectZone(null);
            });

            layer.draw();
        },

        _addRect(x, y, w, h, index) {
            const rect = new Konva.Rect({
                x, y,
                width: Math.max(5, w),
                height: Math.max(5, h),
                stroke: '#ef4444',
                strokeWidth: 2,
                fill: 'rgba(239,68,68,0.15)',
                draggable: true,
                name: 'zone',
            });
            rect.on('click tap', () => this.selectZone(index));
            rect.on('transformend dragend', () => { this._bake(rect); });
            layer.add(rect);
            rects[index] = rect;
            layer.draw();
        },

        /** Fold a Transformer's scaleX/scaleY back into width/height. */
        _bake(rect) {
            rect.width(Math.max(5, rect.width() * rect.scaleX()));
            rect.height(Math.max(5, rect.height() * rect.scaleY()));
            rect.scaleX(1);
            rect.scaleY(1);
        },

        addZone() {
            if (!stage) { this.note = 'Upload a reference image first.'; return; }
            const i = this.zones.length;
            this.zones.push({
                name: `zone_${i + 1}`,
                label: `Zone ${i + 1}`,
                field: 'student_id',
                regex: '',
            });
            this._addRect(20, 20 + i * 12, displayWidth * 0.3, 40, i);
            this.selectZone(i);
            this.note = '';
        },

        removeZone(i) {
            if (rects[i]) rects[i].destroy();
            rects.splice(i, 1);
            this.zones.splice(i, 1);
            this._reindexRects();
            this.selectZone(null);
            if (layer) layer.draw();
        },

        /** Re-bind click handlers after a splice shifts rect indices. */
        _reindexRects() {
            rects.forEach((r, idx) => {
                r.off('click tap');
                r.on('click tap', () => this.selectZone(idx));
            });
        },

        selectZone(i) {
            this.selectedIndex = i;
            if (!transformer) return;
            if (i === null || i === undefined || !rects[i]) {
                transformer.nodes([]);
            } else {
                transformer.nodes([rects[i]]);
            }
            layer.draw();
        },

        /** Build the persisted payload, converting each rect display -> native. */
        serialize() {
            const zones = this.zones.map((z, i) => {
                const r = rects[i];
                if (r) this._bake(r);
                let x1 = r ? Math.round(r.x() * scale) : 0;
                let y1 = r ? Math.round(r.y() * scale) : 0;
                let x2 = r ? Math.round((r.x() + r.width()) * scale) : 0;
                let y2 = r ? Math.round((r.y() + r.height()) * scale) : 0;
                if (x2 < x1) [x1, x2] = [x2, x1];
                if (y2 < y1) [y1, y2] = [y2, y1];
                x1 = Math.max(0, Math.min(x1, this.naturalWidth));
                x2 = Math.max(0, Math.min(x2, this.naturalWidth));
                y1 = Math.max(0, Math.min(y1, this.naturalHeight));
                y2 = Math.max(0, Math.min(y2, this.naturalHeight));
                return {
                    name: z.name,
                    label: z.label,
                    field: z.field,
                    regex: z.regex || null,
                    x1, y1, x2, y2,
                };
            });

            return {
                name: this.name,
                image_path: this.imagePath,
                image_width: this.naturalWidth,
                image_height: this.naturalHeight,
                is_active: this.isActive,
                is_default: this.isDefault,
                zones,
            };
        },

        /** Re-project native -> display and warn if a zone drifts >1px. */
        _roundTripOk(payload) {
            return payload.zones.every((z, i) => {
                const r = rects[i];
                if (!r) return true;
                const dx = Math.abs(z.x1 / scale - r.x());
                const dy = Math.abs(z.y1 / scale - r.y());
                if (dx > 1 || dy > 1) {
                    console.warn(`Zone ${z.name} round-trip drift`, { dx, dy });
                    return false;
                }
                return true;
            });
        },

        async save() {
            if (!this.imagePath) { this.note = 'Upload a reference image first.'; return; }
            if (this.zones.length === 0) { this.note = 'Add at least one zone.'; return; }
            if (this.zones.some((z) => !/^[a-z0-9_]+$/.test(z.name))) {
                this.note = 'Zone keys may only contain lowercase letters, numbers and underscores.';
                return;
            }

            const payload = this.serialize();
            this._roundTripOk(payload);

            this.saving = true;
            this.note = '';
            try {
                const url = this.id ? this.updateUrl : this.storeUrl;
                const method = this.id ? 'PUT' : 'POST';
                const res = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify(payload),
                });
                const json = await res.json();
                if (res.ok && json.redirect) {
                    window.location.href = json.redirect;
                    return;
                }
                if (res.status === 422 && json.errors) {
                    this.note = Object.values(json.errors).flat()[0] || 'Validation failed.';
                } else {
                    this.note = json.message || 'Save failed.';
                }
            } catch (e) {
                this.note = 'Save failed.';
            } finally {
                this.saving = false;
            }
        },
    };
}
