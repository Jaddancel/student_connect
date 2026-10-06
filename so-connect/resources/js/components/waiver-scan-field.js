import { cameraCapture } from "./camera-capture";

/**
 * WAIVER_SCAN field: one or more signed-waiver photos, each captured/uploaded
 * and (when a template is bound) advisorily scanned independently. The server
 * re-validates every item at submit and, per product decision, rejects the
 * whole submission if a single item fails — see FormRenderController::submit.
 *
 * `waiverScanField` owns the list (add/remove, min 1); each entry renders its
 * own `waiverScanItem` component so capture/camera state stays independent.
 */
export function waiverScanField(config) {
    return {
        scanUrl: config.scanUrl,
        csrf: config.csrf,
        template: config.template || null,
        expected: config.expected || {},
        maxItems: config.maxItems || 10,

        items: [],
        nextId: 1,

        init() {
            const initial = (config.initial || []).filter((v) => !!v);
            this.items = initial.length
                ? initial.map((value) => this.newEntry(value))
                : [this.newEntry("")];
        },

        newEntry(value) {
            return { id: this.nextId++, initialValue: value };
        },

        addItem() {
            if (this.items.length >= this.maxItems) return;
            this.items.push(this.newEntry(""));
        },

        removeItem(id) {
            if (this.items.length <= 1) return;
            this.items = this.items.filter((entry) => entry.id !== id);
        },
    };
}

/** A single waiver capture/upload + advisory scan, scoped to its own camera state. */
export function waiverScanItem(initialValue, config) {
    return {
        ...cameraCapture(),

        scanUrl: config.scanUrl,
        csrf: config.csrf,
        template: config.template,
        expected: config.expected,

        checking: false,
        error: "",
        result: null,
        imageData: initialValue || "",

        async captureAndScan() {
            const dataUrl = this.captureDataUrl();
            this.stopCamera();
            if (!dataUrl) {
                this.error =
                    "Could not capture the image — try again or upload instead.";
                return;
            }
            await this.scan(dataUrl);
        },

        async onUpload(event) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;
            const dataUrl = await this.fileToDataUrl(file);
            if (!dataUrl) {
                this.error = "Could not read that image.";
                return;
            }
            await this.scan(dataUrl);
        },

        async scan(dataUrl) {
            this.error = "";
            this.result = null;
            this.imageData = dataUrl; // keep it regardless of the scan outcome

            // No bound template ⇒ capture only; an admin reviews it manually.
            if (!this.template || !this.template.zones) {
                return;
            }

            this.checking = true;
            try {
                const res = await fetch(this.scanUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": this.csrf,
                        Accept: "application/json",
                    },
                    body: JSON.stringify({
                        image: dataUrl,
                        template: this.template,
                        expected: this.expected,
                    }),
                });
                this.result = await res.json();
            } catch (e) {
                this.error =
                    "Scanner unreachable — you can still submit; an admin will review it.";
            } finally {
                this.checking = false;
            }
        },

        rescan() {
            this.imageData = "";
            this.result = null;
            this.error = "";
        },

        get valid() {
            return !!(this.result && this.result.ok && this.result.valid);
        },

        get resultFields() {
            const fields =
                this.result &&
                this.result.validation &&
                this.result.validation.fields;
            if (!fields) return [];
            return Object.entries(fields).map(([key, v]) => ({ key, ...v }));
        },
    };
}
