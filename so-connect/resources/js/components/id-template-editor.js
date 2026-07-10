/**
 * Alpine component backing the SuperAdmin ID-template editor.
 *
 * A template now has TWO sides — front and back — each with its own reference
 * image and named rectangular OCR "zones". The user tags one side at a time via
 * a Front/Back switcher; a template can only be saved once BOTH sides have an
 * image and at least one zone.
 *
 * The single hard problem remains coordinate fidelity: zones are PERSISTED in
 * native image pixels but MANIPULATED in display pixels, so we convert only at
 * the boundary (see buildStage()/_snapshotCurrent()/_sidePayload()).
 *
 * Only the active side lives in a Konva stage. The inactive side is kept as a
 * plain snapshot in the `saved` closure map (meta + native coords); switching
 * sides bakes the active rects into that snapshot, tears the stage down, and
 * rebuilds from the target side's snapshot.
 *
 * Konva objects must never pass through Alpine's reactive Proxy (it wraps them
 * and breaks internal identity checks), so every Konva ref lives in this
 * closure — NOT on the returned reactive object.
 */
import Konva from 'konva';
import { warp } from '../lib/perspective-warp';

const MAX_DISPLAY_WIDTH = 900;
// Crop stage also caps height so a portrait/tall photo fits the viewport
// instead of scaling to full width and overflowing.
const MAX_CROP_DISPLAY_HEIGHT = 520;
// ISO/IEC 7810 ID-1 (bank/most student cards): 85.60 × 53.98 mm. Stored as the
// landscape (long/short) ratio; a vertical ID is the reciprocal.
const ID1_LONG = 85.6;
const ID1_SHORT = 53.98;
// Cap the live-warp preview's long edge; the on-save warp uses full resolution.
const PREVIEW_LONG_EDGE = 360;

/**
 * Order four arbitrary points into [top-left, top-right, bottom-right,
 * bottom-left] tracing the quad's perimeter. The anchors are dragged
 * independently, so their array order is NOT their geometric order — feeding an
 * out-of-order (self-intersecting) quad to the homography collapses the warp.
 * Sorting by angle around the centroid guarantees a convex winding regardless
 * of how the user placed the handles; we then rotate the start to the top-left.
 *
 * @param {Array<[number,number]>} pts four [x,y] points (image y-down)
 * @returns {Array<[number,number]>} the same points as [TL, TR, BR, BL]
 */
function orderCorners(pts) {
    const cx = (pts[0][0] + pts[1][0] + pts[2][0] + pts[3][0]) / 4;
    const cy = (pts[0][1] + pts[1][1] + pts[2][1] + pts[3][1]) / 4;
    // In image coords (y down) increasing atan2 winds clockwise: TL→TR→BR→BL.
    const cw = [...pts].sort(
        (a, b) => Math.atan2(a[1] - cy, a[0] - cx) - Math.atan2(b[1] - cy, b[0] - cx));
    // Rotate so the corner nearest the origin (min x+y) leads the sequence.
    let start = 0;
    let min = Infinity;
    cw.forEach((p, i) => {
        const s = p[0] + p[1];
        if (s < min) { min = s; start = i; }
    });
    return [0, 1, 2, 3].map((i) => cw[(start + i) % 4]);
}

export function idTemplateEditor(config) {
    // --- non-reactive Konva state (closure-scoped, never proxied) ---
    // Belongs to the ACTIVE side only; rebuilt on every side switch.
    let stage = null;
    let layer = null;
    let transformer = null;
    let imageNode = null;
    const rects = []; // Konva.Rect[], index-aligned with `this.zones`
    let scale = 1; // naturalWidth / displayWidth
    let displayWidth = 0;

    // --- crop/straighten state (also closure-scoped, active side only) ---
    let rawImg = null; // decoded pre-straighten <img>, kept for re-crop
    let rawFile = null; // the original uploaded File, for skip-straighten
    let cropStage = null;
    let cropLayer = null;
    let cropPoly = null; // Konva.Line closing the 4 corners
    const cropAnchors = []; // 4 Konva.Circle, ordered [TL, TR, BR, BL]
    let cropScale = 1; // naturalWidth / cropDisplayWidth
    let previewRAF = null; // coalesces rapid drag repaints to one per frame

    // Snapshot of each side: what's needed to rebuild its stage and serialize it.
    // The ACTIVE side's live truth is the reactive props + Konva rects; the
    // inactive side lives here. `_snapshotCurrent()` refreshes the active entry.
    const saved = { front: null, back: null };

    const data = config.data || {};

    return {
        // --- reactive state ---
        id: data.id || null,
        name: data.name || '',
        orientation: data.orientation === 'horizontal' ? 'horizontal' : 'vertical',
        isActive: data.is_active ?? true,
        isDefault: data.is_default ?? false,

        // Which side is being tagged. The reactive props below mirror this side.
        currentSide: 'front',

        // --- active-side working state (populated by _restore) ---
        imagePath: '',
        naturalWidth: 0,
        naturalHeight: 0,
        zones: [],
        selectedIndex: null,
        note: '',
        saving: false,

        // 'crop' shows the 4-corner straighten stage; 'zones' the OCR-zone editor.
        // Null until an image exists for this side (shows the upload prompt).
        mode: null,
        canReCrop: false, // true once a raw image is held in the closure
        aspectMode: 'id1', // 'id1' | 'free' | 'custom'
        customW: 1000,
        customH: 1585,
        outputLongEdge: 1024,

        // --- config ---
        storeUrl: config.storeUrl,
        updateUrl: config.updateUrl,
        uploadUrl: config.uploadUrl,
        csrf: config.csrf,
        assetBase: config.assetBase || '/storage',

        // Native coords loaded from the snapshot, consumed once when the stage is
        // (re)built. Editing thereafter reads geometry from the Konva rects.
        _pendingCoords: [],

        init() {
            saved.front = this._sideFromData(
                data.image_path, data.image_width, data.image_height, data.zones);
            saved.back = this._sideFromData(
                data.back_image_path, data.back_image_width, data.back_image_height, data.back_zones);
            this.currentSide = 'front';
            this._restore('front');
        },

        /** Build a side snapshot from persisted server data. */
        _sideFromData(path, w, h, zonesArr) {
            const list = zonesArr || [];
            return {
                imagePath: path || '',
                naturalWidth: w || 0,
                naturalHeight: h || 0,
                mode: path ? 'zones' : null,
                canReCrop: false,
                rawImg: null,
                rawFile: null,
                zones: list.map((z) => ({
                    name: z.name || '',
                    label: z.label || '',
                    field: z.field || 'student_id',
                    regex: z.regex || '',
                })),
                coords: list.map((z) => ({
                    x1: z.x1 || 0, y1: z.y1 || 0, x2: z.x2 || 0, y2: z.y2 || 0,
                })),
            };
        },

        _blankSide() {
            return {
                imagePath: '', naturalWidth: 0, naturalHeight: 0, mode: null,
                canReCrop: false, rawImg: null, rawFile: null, zones: [], coords: [],
            };
        },

        /** True when a side has an image AND at least one zone (drives badges). */
        isSideReady(side) {
            if (side === this.currentSide) {
                return !!this.imagePath && this.zones.length >= 1;
            }
            const s = saved[side];
            return !!(s && s.imagePath && s.zones && s.zones.length >= 1);
        },

        // --- side switching ---

        switchSide(target) {
            if (target === this.currentSide) return;
            this._snapshotCurrent();

            if (stage) { stage.destroy(); stage = null; rects.length = 0; }
            if (cropStage) { cropStage.destroy(); cropStage = null; cropAnchors.length = 0; }
            transformer = null;
            this.selectedIndex = null;

            this.currentSide = target;
            this._restore(target);
        },

        /** Refresh the active side's snapshot from the reactive props + Konva rects. */
        _snapshotCurrent() {
            const coords = this.zones.map((z, i) => {
                const r = rects[i];
                if (r) this._bake(r);
                return {
                    x1: r ? Math.round(r.x() * scale) : 0,
                    y1: r ? Math.round(r.y() * scale) : 0,
                    x2: r ? Math.round((r.x() + r.width()) * scale) : 0,
                    y2: r ? Math.round((r.y() + r.height()) * scale) : 0,
                };
            });
            saved[this.currentSide] = {
                imagePath: this.imagePath,
                naturalWidth: this.naturalWidth,
                naturalHeight: this.naturalHeight,
                mode: this.mode,
                canReCrop: this.canReCrop,
                rawImg,
                rawFile,
                zones: this.zones.map((z) => ({
                    name: z.name, label: z.label, field: z.field, regex: z.regex,
                })),
                coords,
            };
        },

        /** Load a side's snapshot into the reactive props and (re)build its stage. */
        _restore(side) {
            const s = saved[side] || this._blankSide();
            rawImg = s.rawImg || null;
            rawFile = s.rawFile || null;
            this.imagePath = s.imagePath;
            this.naturalWidth = s.naturalWidth;
            this.naturalHeight = s.naturalHeight;
            this.canReCrop = s.canReCrop;
            this.zones = s.zones.map((z) => ({ ...z }));
            this._pendingCoords = s.coords.map((c) => ({ ...c }));
            this.mode = s.mode;
            this.note = '';
            this.selectedIndex = null;

            if (s.mode === 'zones' && s.imagePath) {
                this.$nextTick(() => this.loadImage(this.imageUrl(s.imagePath)));
            } else if (s.mode === 'crop' && rawImg) {
                this.$nextTick(() => this.buildCropStage());
            }
        },

        /** Absolute URL for a disk-relative uploaded asset (public disk). */
        imageUrl(path) {
            if (!path) return '';
            if (/^(https?:|data:|\/)/.test(path)) return path;
            return `${this.assetBase}/${path}`.replace(/([^:])\/\//g, '$1/');
        },

        // --- reference image upload (for the active side) ---
        // A picked file is NOT uploaded immediately: we decode it locally and
        // enter the crop step so the SuperAdmin can straighten it first. The
        // upload happens from straighten()/skipStraighten().
        uploadImage(event) {
            const file = event.target.files[0];
            event.target.value = '';
            if (!file) return;
            rawFile = file;
            // A brand-new image invalidates any zones drawn against the old one.
            this._pendingCoords = [];
            this.zones = [];
            this.imagePath = '';
            this.note = '';

            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = async () => {
                try { await img.decode(); } catch (e) { /* older browsers */ }
                URL.revokeObjectURL(url);
                rawImg = img;
                this.canReCrop = true;
                this.naturalWidth = img.naturalWidth;
                this.naturalHeight = img.naturalHeight;
                this.enterCropMode();
            };
            img.onerror = () => {
                URL.revokeObjectURL(url);
                this.note = 'Could not read that image file.';
            };
            img.src = url;
        },

        /** POST a Blob/File to the upload route; returns its disk path or null. */
        async _uploadBlob(blob, filename) {
            const body = new FormData();
            body.append('asset', blob, filename);
            body.append('_token', this.csrf);
            try {
                const res = await fetch(this.uploadUrl, { method: 'POST', body });
                const json = await res.json();
                if (res.ok && json.path) return json.path;
                this.note = json.message || 'Upload failed.';
                return null;
            } catch (e) {
                this.note = 'Upload failed.';
                return null;
            }
        },

        // --- crop / straighten step ---

        enterCropMode() {
            this.mode = 'crop';
            this.note = '';
            // Wait for x-show to reveal the crop container before Konva measures it.
            this.$nextTick(() => this.buildCropStage());
        },

        buildCropStage() {
            const container = this.$refs.cropStage;
            if (!container || !rawImg || !this.naturalWidth) return;

            if (cropStage) { cropStage.destroy(); cropAnchors.length = 0; }

            // Scale the whole image to fit within both the container width and a
            // max height (uniform scale keeps cropScale — natural/display — valid).
            const maxW = Math.min(container.clientWidth || MAX_DISPLAY_WIDTH, MAX_DISPLAY_WIDTH);
            const fit = Math.min(maxW / this.naturalWidth, MAX_CROP_DISPLAY_HEIGHT / this.naturalHeight);
            const cropDisplayWidth = Math.max(1, Math.round(this.naturalWidth * fit));
            const displayHeight = Math.max(1, Math.round(this.naturalHeight * fit));
            cropScale = this.naturalWidth / cropDisplayWidth;

            cropStage = new Konva.Stage({ container, width: cropDisplayWidth, height: displayHeight });
            cropLayer = new Konva.Layer();
            cropStage.add(cropLayer);
            cropLayer.add(new Konva.Image({ image: rawImg, width: cropDisplayWidth, height: displayHeight }));

            // Start with the corners at the image's own corners: [TL, TR, BR, BL].
            const corners = [
                [0, 0],
                [cropDisplayWidth, 0],
                [cropDisplayWidth, displayHeight],
                [0, displayHeight],
            ];
            cropPoly = new Konva.Line({
                points: corners.flat(),
                stroke: '#2563eb',
                strokeWidth: 2,
                closed: true,
                fill: 'rgba(37,99,235,0.08)',
            });
            cropLayer.add(cropPoly);

            corners.forEach(([x, y]) => {
                const anchor = new Konva.Circle({
                    x, y, radius: 8,
                    fill: '#ffffff', stroke: '#2563eb', strokeWidth: 2,
                    draggable: true,
                });
                anchor.on('dragmove', () => {
                    this._clampAnchor(anchor, cropDisplayWidth, displayHeight);
                    this._syncCropPoly();
                    this._schedulePreview();
                });
                cropLayer.add(anchor);
                cropAnchors.push(anchor);
            });

            cropLayer.draw();
            this.renderPreview();
        },

        _clampAnchor(anchor, w, h) {
            anchor.x(Math.max(0, Math.min(anchor.x(), w)));
            anchor.y(Math.max(0, Math.min(anchor.y(), h)));
        },

        _syncCropPoly() {
            if (!cropPoly) return;
            cropPoly.points(cropAnchors.flatMap((a) => [a.x(), a.y()]));
            cropLayer.batchDraw();
        },

        /**
         * The 4 corners in SOURCE NATIVE px, ordered [TL, TR, BR, BL].
         * Ordering is geometric (see orderCorners) so a straightened result is
         * produced no matter the sequence in which the anchors were dragged.
         */
        _cornersNative() {
            const raw = cropAnchors.map((a) => [a.x() * cropScale, a.y() * cropScale]);
            return orderCorners(raw);
        },

        /** The ID-1 aspect ratio (width/height) for the chosen orientation. */
        _id1Ratio() {
            return this.orientation === 'vertical'
                ? ID1_SHORT / ID1_LONG // portrait
                : ID1_LONG / ID1_SHORT; // landscape
        },

        /** Output size (native px) from the chosen aspect + long edge. */
        targetOutputSize() {
            const corners = this._cornersNative();
            const dist = (a, b) => Math.hypot(a[0] - b[0], a[1] - b[1]);
            // Average opposing edges to estimate the quad's true proportions.
            const quadW = (dist(corners[0], corners[1]) + dist(corners[3], corners[2])) / 2;
            const quadH = (dist(corners[0], corners[3]) + dist(corners[1], corners[2])) / 2;

            let ratio; // width / height
            if (this.aspectMode === 'id1') {
                ratio = this._id1Ratio();
            } else if (this.aspectMode === 'custom') {
                const cw = parseFloat(this.customW) || 0;
                const ch = parseFloat(this.customH) || 0;
                ratio = cw > 0 && ch > 0 ? cw / ch : quadW / quadH;
            } else {
                ratio = quadH > 0 ? quadW / quadH : 1;
            }
            if (!isFinite(ratio) || ratio <= 0) ratio = 1;

            const longEdge = Math.max(64, parseInt(this.outputLongEdge, 10) || 1024);
            return ratio >= 1
                ? { w: longEdge, h: Math.round(longEdge / ratio) }
                : { w: Math.round(longEdge * ratio), h: longEdge };
        },

        /** Recompute output size + repaint the preview (e.g. aspect changed). */
        onAspectChange() {
            this._schedulePreview();
        },

        _schedulePreview() {
            if (previewRAF) return;
            previewRAF = requestAnimationFrame(() => {
                previewRAF = null;
                this.renderPreview();
            });
        },

        /** Live-warp a capped-resolution preview into the preview canvas. */
        renderPreview() {
            if (this.mode !== 'crop' || !rawImg || cropAnchors.length !== 4) return;
            const canvas = this.$refs.preview;
            if (!canvas) return;

            const { w, h } = this.targetOutputSize();
            const long = Math.max(w, h) || 1;
            const s = long > PREVIEW_LONG_EDGE ? PREVIEW_LONG_EDGE / long : 1;
            const pw = Math.max(1, Math.round(w * s));
            const ph = Math.max(1, Math.round(h * s));

            try {
                const warped = warp(rawImg, this._cornersNative(), pw, ph);
                canvas.width = pw;
                canvas.height = ph;
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, pw, ph);
                ctx.drawImage(warped, 0, 0);
            } catch (e) {
                this.note = 'Preview unavailable: ' + e.message;
            }
        },

        /** Full-resolution warp → upload the straightened crop → zones step. */
        async straighten() {
            if (!rawImg || cropAnchors.length !== 4) return;
            this.saving = true;
            this.note = '';
            try {
                const { w, h } = this.targetOutputSize();
                const canvas = warp(rawImg, this._cornersNative(), w, h);
                const blob = await new Promise((resolve) =>
                    canvas.toBlob(resolve, 'image/jpeg', 0.92));
                if (!blob) { this.note = 'Straighten failed.'; return; }
                const path = await this._uploadBlob(blob, 'straightened.jpg');
                if (path) this._enterZones(path);
            } catch (e) {
                this.note = 'Straighten failed: ' + e.message;
            } finally {
                this.saving = false;
            }
        },

        /** Skip straightening: upload the raw file untouched → zones step. */
        async skipStraighten() {
            if (!rawFile) return;
            this.saving = true;
            this.note = '';
            try {
                const path = await this._uploadBlob(rawFile, rawFile.name || 'reference.jpg');
                if (path) this._enterZones(path);
            } finally {
                this.saving = false;
            }
        },

        _enterZones(path) {
            this.imagePath = path;
            this._pendingCoords = [];
            this.zones = [];
            this.mode = 'zones';
            this.loadImage(this.imageUrl(path));
        },

        /** Back to the crop step; the raw image is still held in the closure. */
        reCrop() {
            if (!rawImg) {
                this.note = 'Re-crop is only available right after uploading a new image.';
                return;
            }
            if (this.zones.length && !window.confirm(
                'Re-cropping resets the zones you have drawn. Continue?')) return;
            this.zones = [];
            this._pendingCoords = [];
            this.imagePath = '';
            this.enterCropMode();
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

        /** Convert a side snapshot into its prefixed payload keys (display already native). */
        _sidePayload(s, prefix) {
            const zones = s.zones.map((z, i) => {
                const c = s.coords[i] || { x1: 0, y1: 0, x2: 0, y2: 0 };
                let { x1, y1, x2, y2 } = c;
                if (x2 < x1) [x1, x2] = [x2, x1];
                if (y2 < y1) [y1, y2] = [y2, y1];
                x1 = Math.max(0, Math.min(x1, s.naturalWidth));
                x2 = Math.max(0, Math.min(x2, s.naturalWidth));
                y1 = Math.max(0, Math.min(y1, s.naturalHeight));
                y2 = Math.max(0, Math.min(y2, s.naturalHeight));
                return {
                    name: z.name, label: z.label, field: z.field,
                    regex: z.regex || null, x1, y1, x2, y2,
                };
            });
            return {
                [`${prefix}image_path`]: s.imagePath,
                [`${prefix}image_width`]: s.naturalWidth,
                [`${prefix}image_height`]: s.naturalHeight,
                [`${prefix}zones`]: zones,
            };
        },

        async save() {
            // Freeze the active side, then require both sides to be complete.
            this._snapshotCurrent();

            for (const side of ['front', 'back']) {
                const label = side === 'front' ? 'front' : 'back';
                const s = saved[side];
                if (!s || !s.imagePath) {
                    this.note = `Upload a reference image for the ${label} of the ID.`;
                    this.switchSide(side);
                    return;
                }
                if (s.zones.length === 0) {
                    this.note = `Add at least one zone on the ${label} of the ID.`;
                    this.switchSide(side);
                    return;
                }
                if (s.zones.some((z) => !/^[a-z0-9_]+$/.test(z.name))) {
                    this.note = 'Zone keys may only contain lowercase letters, numbers and underscores.';
                    this.switchSide(side);
                    return;
                }
            }

            const payload = {
                name: this.name,
                orientation: this.orientation,
                is_active: this.isActive,
                is_default: this.isDefault,
                ...this._sidePayload(saved.front, ''),
                ...this._sidePayload(saved.back, 'back_'),
            };

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
