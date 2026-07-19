/**
 * Konva zonal editor for waiver templates. Upload a reference waiver image, draw
 * rectangular zones by click-drag, name + type each (text / signature / stamp),
 * and save. Zones are persisted in native IMAGE pixels (the stage is scaled to
 * fit, so on-canvas coordinates are divided back out by `scale`).
 *
 * Konva objects must never be stored in Alpine's reactive state (its Proxy
 * breaks Konva's identity checks), so rects/stage live in closure scope; only
 * zone metadata (name/type) is reactive.
 */
import Konva from 'konva';

export function waiverTemplateEditor(config) {
    let stage = null;
    let layer = null;
    let transformer = null;
    let imageObj = null;
    let scale = 1; // stage px per image px
    const rects = []; // Konva.Rect[], index-aligned with this.zones

    return {
        storeUrl: config.storeUrl,
        indexUrl: config.indexUrl,
        csrf: config.csrf,
        zoneTypes: config.zoneTypes || ['text', 'signature', 'stamp'],

        name: '',
        imageFile: null,
        imageWidth: 0,
        imageHeight: 0,
        hasImage: false,
        zones: [], // [{name, type}]
        selected: -1,
        saving: false,
        error: '',

        uploadImage(event) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;
            this.imageFile = file;
            const img = new Image();
            img.onload = () => {
                this.imageWidth = img.naturalWidth;
                this.imageHeight = img.naturalHeight;
                imageObj = img;
                this.zones = [];
                rects.length = 0;
                this.hasImage = true;
                this.$nextTick(() => this.buildStage());
            };
            img.src = URL.createObjectURL(file);
        },

        buildStage() {
            const container = this.$refs.stage;
            const maxW = container.clientWidth || 700;
            scale = Math.min(1, maxW / this.imageWidth);
            const w = Math.round(this.imageWidth * scale);
            const h = Math.round(this.imageHeight * scale);

            if (stage) stage.destroy();
            stage = new Konva.Stage({ container, width: w, height: h });
            layer = new Konva.Layer();
            stage.add(layer);
            layer.add(new Konva.Image({ image: imageObj, width: w, height: h }));
            transformer = new Konva.Transformer({ rotateEnabled: false, keepRatio: false });
            layer.add(transformer);
            layer.draw();

            this.wireDrawing();
        },

        wireDrawing() {
            let start = null;
            let temp = null;

            stage.on('mousedown touchstart', (e) => {
                // Only start a new box on the image/background, not on an existing rect.
                if (e.target.getClassName && e.target.getClassName() === 'Rect') return;
                start = stage.getPointerPosition();
                temp = new Konva.Rect({
                    x: start.x, y: start.y, width: 0, height: 0,
                    stroke: '#f43f5e', strokeWidth: 2, fill: 'rgba(244,63,94,0.15)', draggable: true,
                });
                layer.add(temp);
            });

            stage.on('mousemove touchmove', () => {
                if (!start || !temp) return;
                const pos = stage.getPointerPosition();
                temp.width(pos.x - start.x);
                temp.height(pos.y - start.y);
                layer.batchDraw();
            });

            stage.on('mouseup touchend', () => {
                if (!temp) return;
                let x = temp.x(); let y = temp.y(); let w = temp.width(); let h = temp.height();
                if (w < 0) { x += w; w = -w; }
                if (h < 0) { y += h; h = -h; }
                if (w < 8 || h < 8) { temp.destroy(); temp = null; start = null; layer.draw(); return; }

                temp.setAttrs({ x, y, width: w, height: h });
                const idx = rects.length;
                rects.push(temp);
                this.zones.push({ name: 'zone_' + (idx + 1), type: 'text' });
                temp.on('click tap', () => this.select(idx));
                this.select(idx);
                temp = null; start = null;
                layer.draw();
            });
        },

        select(idx) {
            this.selected = idx;
            transformer.nodes(idx >= 0 && rects[idx] ? [rects[idx]] : []);
            layer.draw();
        },

        removeZone(idx) {
            if (rects[idx]) rects[idx].destroy();
            rects.splice(idx, 1);
            this.zones.splice(idx, 1);
            // Re-bind click handlers to the shifted indexes.
            rects.forEach((rect, i) => {
                rect.off('click tap');
                rect.on('click tap', () => this.select(i));
            });
            this.select(-1);
            layer.draw();
        },

        zonePixels(index) {
            const r = rects[index];
            if (!r) return { x: 0, y: 0, w: 0, h: 0 };
            return {
                x: Math.round(r.x() / scale),
                y: Math.round(r.y() / scale),
                w: Math.round((r.width() * r.scaleX()) / scale),
                h: Math.round((r.height() * r.scaleY()) / scale),
            };
        },

        async save() {
            this.error = '';
            if (!this.imageFile) { this.error = 'Upload a reference image first.'; return; }
            if (!this.name.trim()) { this.error = 'Give the template a name.'; return; }
            if (this.zones.length === 0) { this.error = 'Draw at least one zone.'; return; }

            this.saving = true;
            const form = new FormData();
            form.append('name', this.name);
            form.append('image', this.imageFile);
            form.append('image_width', String(this.imageWidth));
            form.append('image_height', String(this.imageHeight));
            this.zones.forEach((z, i) => {
                const px = this.zonePixels(i);
                form.append(`zones[${i}][name]`, z.name);
                form.append(`zones[${i}][type]`, z.type);
                form.append(`zones[${i}][x]`, String(px.x));
                form.append(`zones[${i}][y]`, String(px.y));
                form.append(`zones[${i}][w]`, String(px.w));
                form.append(`zones[${i}][h]`, String(px.h));
            });

            try {
                const res = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' },
                    body: form,
                });
                if (res.ok) {
                    const json = await res.json().catch(() => ({}));
                    window.location = json.redirect || this.indexUrl;
                } else {
                    const json = await res.json().catch(() => ({}));
                    this.error = json.message
                        || (json.errors ? Object.values(json.errors)[0][0] : null)
                        || 'Could not save the template.';
                }
            } catch (e) {
                this.error = 'Could not save the template.';
            } finally {
                this.saving = false;
            }
        },
    };
}
