import SignaturePad from "signature_pad";
import { extractSignatureInk } from "./signature-ink";

/**
 * Alpine component for a signature-capture PAD (used by the profile Signature
 * card). Draws on a canvas and writes the resulting PNG data-URL into a hidden
 * input named after the field key so it posts with the form.
 *
 * When a `verifyUrl` is configured, every finished stroke is (debounced) POSTed
 * there to ask whether the drawn signature is recognized; advisory only.
 */
export function signatureField(config = {}) {
    const options = typeof config === "object" && config !== null ? config : {};

    return {
        pad: null,
        verifyUrl: options.verifyUrl || null,
        verifyState: "idle", // idle|checking|recognized|not_recognized|no_signatures|unavailable
        matchedName: "",
        extractError: "",
        _debounce: null,
        _fromScan: false,

        init() {
            const canvas = this.$refs.canvas;
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const rect = canvas.getBoundingClientRect();
            canvas.width = (rect.width || canvas.width) * ratio;
            canvas.height = (rect.height || canvas.height) * ratio;
            canvas.getContext("2d").scale(ratio, ratio);

            this.pad = new SignaturePad(canvas, {
                backgroundColor: "rgba(255,255,255,1)",
            });
            this.pad.addEventListener("endStroke", () => {
                const value = this.pad.isEmpty()
                    ? ""
                    : this.pad.toDataURL("image/png");
                this.$refs.input.value = value;
                this._fromScan = false;
                this.scheduleVerify(value);
            });
        },

        clear() {
            this.pad.clear();
            this.$refs.input.value = "";
            this.extractError = "";
            this.verifyState = "idle";
            this.matchedName = "";
            this._fromScan = false;
            clearTimeout(this._debounce);
        },

        fromDataUrl(dataUrl) {
            if (!dataUrl || !this.pad) return;
            this.pad.clear();
            const canvas = this.$refs.canvas;
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            this.pad.fromDataURL(dataUrl, {
                width: canvas.width / ratio,
                height: canvas.height / ratio,
            });
            this.$refs.input.value = dataUrl;
            this.$refs.input.dispatchEvent(
                new Event("input", { bubbles: true }),
            );
            this._fromScan = true;
            this.scheduleVerify(dataUrl);
        },

        clearFromScan() {
            if (this._fromScan) this.clear();
        },

        async onUpload(event) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;
            this.extractError = "";
            try {
                const rawDataUrl = await this.readAsDataUrl(file);
                const extractedDataUrl = await extractSignatureInk(rawDataUrl);
                this.fromDataUrl(extractedDataUrl);
                event.target.value = "";
            } catch (e) {
                this.extractError =
                    "We couldn't find a signature in that photo. " +
                    "Use a well-lit shot of the signature on plain paper, with nothing else in frame.";
                event.target.value = "";
            }
        },

        readAsDataUrl(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = reject;
                reader.readAsDataURL(file);
            });
        },

        scheduleVerify(dataUrl) {
            if (!this.verifyUrl) return;
            clearTimeout(this._debounce);
            if (!dataUrl) {
                this.verifyState = "idle";
                this.matchedName = "";
                return;
            }
            this.verifyState = "checking";
            this._debounce = setTimeout(() => this.verify(dataUrl), 600);
        },

        async verify(dataUrl) {
            try {
                const csrf =
                    document.querySelector('meta[name="csrf-token"]')
                        ?.content || "";
                const res = await fetch(this.verifyUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": csrf,
                        Accept: "application/json",
                    },
                    body: JSON.stringify({ signature: dataUrl }),
                });
                if (!res.ok) {
                    this.verifyState = "unavailable";
                    return;
                }
                const json = await res.json();
                this.verifyState = json.status || "unavailable";
                this.matchedName = json.matched_user || "";
            } catch (e) {
                this.verifyState = "unavailable";
            }
        },
    };
}
