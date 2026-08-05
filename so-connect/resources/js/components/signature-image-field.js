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
        savedUrl: options.savedUrl || null,
        savedPath: options.savedPath || null,
        preview: null,
        usingSaved: false,
        verifyState: 'idle',
        matchedName: '',
        extractError: '',
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
