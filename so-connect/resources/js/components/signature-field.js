import SignaturePad from 'signature_pad';

/**
 * Alpine component for a signature-capture field. Draws on a canvas and writes
 * the resulting PNG data-URL into a hidden input named after the field key so it
 * posts with the form (the renderer turns it into a stored PNG file).
 *
 * When a `verifyUrl` is configured, every finished stroke is (debounced)
 * POSTed there to ask whether the drawn signature is recognized among the
 * signatures stored in the system. The result is advisory only — it renders a
 * badge and never blocks submission.
 */
export function signatureField(config = {}) {
    // Pages historically passed the field key as a bare string; tolerate it.
    const options = typeof config === 'object' && config !== null ? config : {};

    return {
        pad: null,
        verifyUrl: options.verifyUrl || null,
        verifyState: 'idle', // idle|checking|recognized|not_recognized|no_signatures|unavailable
        matchedName: '',
        _debounce: null,
        _fromScan: false, // current pad content came from an ID scan, not the user

        init() {
            const canvas = this.$refs.canvas;
            // Match the backing store to the displayed size for crisp lines.
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const rect = canvas.getBoundingClientRect();
            canvas.width = (rect.width || canvas.width) * ratio;
            canvas.height = (rect.height || canvas.height) * ratio;
            canvas.getContext('2d').scale(ratio, ratio);

            this.pad = new SignaturePad(canvas, { backgroundColor: 'rgba(255,255,255,1)' });
            this.pad.addEventListener('endStroke', () => {
                const value = this.pad.isEmpty() ? '' : this.pad.toDataURL('image/png');
                this.$refs.input.value = value;
                this._fromScan = false; // the user drew — this is theirs now
                this.scheduleVerify(value);
            });
        },

        clear() {
            this.pad.clear();
            this.$refs.input.value = '';
            this.verifyState = 'idle';
            this.matchedName = '';
            this._fromScan = false;
            clearTimeout(this._debounce);
        },

        /**
         * Accept an externally-captured signature (the ID-scan wizard's crop,
         * delivered via a `signature-set` event). Never replaces anything the
         * user drew or a value already pending in the input.
         */
        fromDataUrl(dataUrl) {
            if (!dataUrl || !this.pad) return;
            if (!this.pad.isEmpty() || this.$refs.input.value) return;
            const canvas = this.$refs.canvas;
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            this.pad.fromDataURL(dataUrl, {
                width: canvas.width / ratio,
                height: canvas.height / ratio,
            });
            this.$refs.input.value = dataUrl;
            this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
            this._fromScan = true;
            this.scheduleVerify(dataUrl);
        },

        /**
         * `signature-clear` handler: a rescan is taking back its earlier crop.
         * Only clears when the pad still shows the scanned signature — a
         * user-drawn one stays.
         */
        clearFromScan() {
            if (this._fromScan) this.clear();
        },

        scheduleVerify(dataUrl) {
            if (!this.verifyUrl) return;
            clearTimeout(this._debounce);
            if (!dataUrl) {
                this.verifyState = 'idle';
                this.matchedName = '';
                return;
            }
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
