import SignaturePad from 'signature_pad';

/**
 * Alpine component for a signature-capture field. Draws on a canvas and writes
 * the resulting PNG data-URL into a hidden input named after the field key so it
 * posts with the form (the renderer turns it into a stored PNG file).
 */
export function signatureField() {
    return {
        pad: null,
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
                this.$refs.input.value = this.pad.isEmpty() ? '' : this.pad.toDataURL('image/png');
            });
        },
        clear() {
            this.pad.clear();
            this.$refs.input.value = '';
        },
    };
}
