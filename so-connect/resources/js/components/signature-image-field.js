/**
 * Alpine component for an image-based signature FORM FIELD.
 *
 * The user either keeps their saved profile signature (autofilled) or uploads a
 * photo of a signature; the upload is processed client-side — grayscale +
 * threshold to isolate the ink, cropped to the ink's bounding box, re-encoded as
 * a transparent PNG — then written as a data-URL into a hidden input named after
 * the field key (the renderer stores it as a PNG file, unchanged from the pad).
 * The ID-scan wizard can still inject a cropped signature via `signature-set`.
 *
 * With a `verifyUrl`, a processed upload is (debounced) POSTed to ask whether it
 * is recognized among the stored signatures — advisory only, never blocks.
 */
export function signatureImageField(config = {}) {
    const options = typeof config === 'object' && config !== null ? config : {};

    return {
        verifyUrl: options.verifyUrl || null,
        savedUrl: options.savedUrl || null,
        savedPath: options.savedPath || null,
        preview: null,
        usingSaved: false,
        verifyState: 'idle',
        matchedName: '',
        _debounce: null,
        _fromScan: false,

        init() {
            if (this.savedPath) {
                this.$refs.input.value = this.savedPath;
                this.preview = this.savedUrl;
                this.usingSaved = true;
            }
        },

        async onUpload(event) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;
            try {
                const dataUrl = await this.extractSignature(file);
                this.$refs.input.value = dataUrl;
                this.preview = dataUrl;
                this.usingSaved = false;
                this._fromScan = false;
                this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
                this.scheduleVerify(dataUrl);
            } catch (e) {
                const raw = await this.readAsDataUrl(file);
                this.$refs.input.value = raw;
                this.preview = raw;
                this.usingSaved = false;
            }
        },

        async extractSignature(file) {
            const img = await this.loadImage(await this.readAsDataUrl(file));
            const maxEdge = 1000;
            const scale = Math.min(1, maxEdge / Math.max(img.width, img.height));
            const w = Math.max(1, Math.round(img.width * scale));
            const h = Math.max(1, Math.round(img.height * scale));

            const canvas = document.createElement('canvas');
            canvas.width = w;
            canvas.height = h;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, w, h);

            const data = ctx.getImageData(0, 0, w, h);
            const px = data.data;
            const threshold = 150;
            let minX = w, minY = h, maxX = -1, maxY = -1;

            for (let y = 0; y < h; y++) {
                for (let x = 0; x < w; x++) {
                    const i = (y * w + x) * 4;
                    const lum = 0.299 * px[i] + 0.587 * px[i + 1] + 0.114 * px[i + 2];
                    if (lum < threshold && px[i + 3] > 20) {
                        px[i] = 20; px[i + 1] = 20; px[i + 2] = 30; px[i + 3] = 255;
                        if (x < minX) minX = x;
                        if (x > maxX) maxX = x;
                        if (y < minY) minY = y;
                        if (y > maxY) maxY = y;
                    } else {
                        px[i + 3] = 0;
                    }
                }
            }

            if (maxX < minX || maxY < minY) {
                throw new Error('no ink detected');
            }

            ctx.putImageData(data, 0, 0);

            const pad = 6;
            const cx = Math.max(0, minX - pad);
            const cy = Math.max(0, minY - pad);
            const cw = Math.min(w, maxX + pad) - cx;
            const ch = Math.min(h, maxY + pad) - cy;

            const out = document.createElement('canvas');
            out.width = cw;
            out.height = ch;
            out.getContext('2d').drawImage(canvas, cx, cy, cw, ch, 0, 0, cw, ch);

            return out.toDataURL('image/png');
        },

        clear() {
            this.$refs.input.value = '';
            this.preview = null;
            this.usingSaved = false;
            this._fromScan = false;
            this.verifyState = 'idle';
            this.matchedName = '';
            clearTimeout(this._debounce);
        },

        useSaved() {
            if (!this.savedPath) return;
            this.$refs.input.value = this.savedPath;
            this.preview = this.savedUrl;
            this.usingSaved = true;
            this._fromScan = false;
            this.verifyState = 'idle';
        },

        fromDataUrl(dataUrl) {
            if (!dataUrl || this.$refs.input.value) return;
            this.$refs.input.value = dataUrl;
            this.preview = dataUrl;
            this.usingSaved = false;
            this._fromScan = true;
            this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
            this.scheduleVerify(dataUrl);
        },

        clearFromScan() {
            if (this._fromScan) this.clear();
        },

        readAsDataUrl(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = reject;
                reader.readAsDataURL(file);
            });
        },

        loadImage(src) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => resolve(img);
                img.onerror = reject;
                img.src = src;
            });
        },

        scheduleVerify(dataUrl) {
            if (!this.verifyUrl || !dataUrl || !dataUrl.startsWith('data:')) return;
            clearTimeout(this._debounce);
            this.verifyState = 'checking';
            this._debounce = setTimeout(() => this.verify(dataUrl), 600);
        },

        async verify(dataUrl) {
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const res = await fetch(this.verifyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ signature: dataUrl }),
                });
                if (!res.ok) {
                    this.verifyState = 'unavailable';
                    return;
                }
                const json = await res.json();
                this.verifyState = json.status || 'unavailable';
                this.matchedName = json.matched_user || '';
            } catch (e) {
                this.verifyState = 'unavailable';
            }
        },
    };
}
