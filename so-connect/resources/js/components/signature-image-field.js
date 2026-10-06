import { extractSignatureInk } from './signature-ink';

/**
 * Alpine component for an image-based signature FORM FIELD.
 *
 * The user either keeps their saved profile signature (autofilled) or uploads a
 * photo of a signature on paper. The photo is reduced to its ink client-side
 * (see signature-ink.js) and written as a transparent-PNG data-URL into a hidden
 * input named after the field key. The ID-scan wizard can inject an already
 * extracted signature via `signature-set`.
 *
 * The photo itself is never submitted: if the ink cannot be found, the upload is
 * rejected with an explanation rather than posted raw. The server re-extracts
 * whatever arrives regardless, so this is a fast failure, not the safeguard.
 *
 * With a `verifyUrl`, a processed upload is (debounced) POSTed to ask whether it
 * is recognized among the stored signatures — advisory only, never blocks.
 */
export function signatureImageField(config = {}) {
    const options = typeof config === 'object' && config !== null ? config : {};

    return {
        verifyUrl: options.verifyUrl || null,
        enrollUrl: options.enrollUrl || null,
        compare: !!options.compare,
        savedUrl: options.savedUrl || null,
        savedPath: options.savedPath || null,
        preview: null,
        usingSaved: false,
        verifyState: 'idle',
        matchedName: '',
        extractError: '',
        // Interactive naming of an unrecognized signature (Normal mode only).
        ownerName: '',
        saveState: 'idle', // idle|saving|saved|error
        saveError: '',
        savedName: '',
        _debounce: null,
        _fromScan: false,

        init() {
            if (this.savedPath) {
                this.$refs.input.value = this.savedPath;
                this.preview = this.savedUrl;
                this.usingSaved = true;
                return;
            }
            // A validation failure elsewhere on the form round-trips this
            // field's value back via old() — Blade already rendered it onto
            // the hidden input, but nothing had shown it as a preview yet,
            // which made a signature that survived the retry look like it
            // hadn't (and invited clearing/redrawing a value that was fine).
            const restored = this.$refs.input.value;
            if (restored && restored.startsWith('data:')) {
                this.preview = restored;
            }
        },

        async onUpload(event) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;
            this.extractError = '';
            try {
                const dataUrl = await extractSignatureInk(await this.readAsDataUrl(file));
                this.$refs.input.value = dataUrl;
                this.preview = dataUrl;
                this.usingSaved = false;
                this._fromScan = false;
                this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
                this.scheduleVerify(dataUrl);
            } catch (e) {
                // Never fall back to posting the raw photo: the server would
                // reject it anyway, and the whole point is to keep the
                // signature, not the picture it came from.
                this.clear();
                this.extractError = "We couldn't find a signature in that photo. "
                    + 'Use a well-lit shot of the signature on plain paper, with nothing else in frame.';
                event.target.value = '';
            }
        },

        clear() {
            this.$refs.input.value = '';
            this.preview = null;
            this.usingSaved = false;
            this._fromScan = false;
            this.extractError = '';
            this.verifyState = 'idle';
            this.matchedName = '';
            this.resetNaming();
            clearTimeout(this._debounce);
        },

        resetNaming() {
            this.ownerName = '';
            this.saveState = 'idle';
            this.saveError = '';
            this.savedName = '';
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
            // A new drawing supersedes any prior "saved as" result.
            this.resetNaming();
            if (!this.verifyUrl || !dataUrl || !dataUrl.startsWith('data:')) return;
            clearTimeout(this._debounce);
            this.verifyState = 'checking';
            this._debounce = setTimeout(() => this.verify(dataUrl), 600);
        },

        /**
         * Name an unrecognized signature: POST the current drawing + typed name
         * to the enroll endpoint, which files it as a profile. Normal mode only.
         */
        async saveOwnerName() {
            const name = this.ownerName.trim();
            const dataUrl = this.$refs.input.value;
            if (!this.enrollUrl || !name || !dataUrl || !dataUrl.startsWith('data:')) return;
            this.saveState = 'saving';
            this.saveError = '';
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const res = await fetch(this.enrollUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ signature: dataUrl, name }),
                });
                if (!res.ok) {
                    const json = await res.json().catch(() => ({}));
                    this.saveState = 'error';
                    this.saveError = json.message || "We couldn't save that signature. Please try again.";
                    return;
                }
                const json = await res.json();
                this.saveState = 'saved';
                this.savedName = json.name || name;
            } catch (e) {
                this.saveState = 'error';
                this.saveError = "We couldn't save that signature. Please try again.";
            }
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
