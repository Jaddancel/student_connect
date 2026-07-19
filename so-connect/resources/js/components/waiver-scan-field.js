import { cameraCapture } from './camera-capture';

/**
 * WAIVER_SCAN field: capture (or upload) a photo of a signed waiver, keep it as
 * the field's value (a hidden data-URL input, submitted for admin review), and —
 * when a waiver template is bound — send it to /waiver-scan for an advisory
 * validity check (name/date/time match + stamp/signature presence). The server
 * re-validates authoritatively at submit, so a scanner outage never blocks
 * submission.
 */
export function waiverScanField(config) {
    return {
        ...cameraCapture(),

        scanUrl: config.scanUrl,
        csrf: config.csrf,
        template: config.template || null,
        expected: config.expected || {},

        checking: false,
        error: '',
        result: null,
        imageData: '', // the captured/uploaded waiver image (this field's value)

        async captureAndScan() {
            const dataUrl = this.captureDataUrl();
            this.stopCamera();
            if (!dataUrl) {
                this.error = 'Could not capture the image — try again or upload instead.';
                return;
            }
            await this.scan(dataUrl);
        },

        async onUpload(event) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;
            const dataUrl = await this.fileToDataUrl(file);
            if (!dataUrl) {
                this.error = 'Could not read that image.';
                return;
            }
            await this.scan(dataUrl);
        },

        async scan(dataUrl) {
            this.error = '';
            this.result = null;
            this.imageData = dataUrl; // keep it regardless of the scan outcome

            // No bound template ⇒ capture only; an admin reviews it manually.
            if (!this.template || !this.template.zones) {
                return;
            }

            this.checking = true;
            try {
                const res = await fetch(this.scanUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        image: dataUrl,
                        template: this.template,
                        expected: this.expected,
                    }),
                });
                this.result = await res.json();
            } catch (e) {
                this.error = 'Scanner unreachable — you can still submit; an admin will review it.';
            } finally {
                this.checking = false;
            }
        },

        rescan() {
            this.imageData = '';
            this.result = null;
            this.error = '';
        },

        get valid() {
            return !!(this.result && this.result.ok && this.result.valid);
        },

        get resultFields() {
            const fields = this.result && this.result.validation && this.result.validation.fields;
            if (!fields) return [];
            return Object.entries(fields).map(([key, v]) => ({ key, ...v }));
        },
    };
}
