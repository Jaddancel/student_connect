/**
 * Reusable rear-camera capture mixin for Alpine components. Spread into a
 * component that provides `x-ref="video"` and `x-ref="canvas"` elements:
 *
 *   waiverScanField() { return { ...cameraCapture(), ... }; }
 *
 * Provides camera state + start/stop, a full-frame JPEG capture, and a
 * file → data-URL reader (for the "upload instead" path).
 */
export function cameraCapture() {
    return {
        stream: null,
        cameraOn: false,
        cameraError: '',

        async startCamera() {
            this.cameraError = '';
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.cameraError = 'Camera is not available here — please use “Upload instead”.';
                return;
            }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                const video = this.$refs.video;
                if (video) {
                    video.srcObject = this.stream;
                    await video.play();
                }
                this.cameraOn = true;
            } catch (e) {
                this.cameraError = 'Could not access the camera — allow permission or use “Upload instead”.';
                this.cameraOn = false;
            }
        },

        stopCamera() {
            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
                this.stream = null;
            }
            this.cameraOn = false;
        },

        /** Current video frame as a JPEG data URL, or null if the camera isn't ready. */
        captureDataUrl() {
            const video = this.$refs.video;
            if (!video || !video.videoWidth) return null;
            const canvas = this.$refs.canvas;
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            return canvas.toDataURL('image/jpeg', 0.92);
        },

        /** Read an uploaded image File as a data URL (resolves null on failure). */
        fileToDataUrl(file) {
            return new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = () => resolve(null);
                reader.readAsDataURL(file);
            });
        },
    };
}
