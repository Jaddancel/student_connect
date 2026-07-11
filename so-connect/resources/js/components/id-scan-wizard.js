/**
 * Two-step signup wizard for the student-leader directory form.
 *
 * Step 1 is a live-camera ID scanner (with an ID-card finder overlay and an
 * upload fallback) that captures BOTH sides of the ID in sequence: the user
 * scans the front, then the back. Each captured/uploaded photo is normalized
 * (downscaled/re-encoded under the server's 2 MB cap) and POSTed to `/id-scan`
 * with its `side`, and the returned universal-keyed `fields` maps are MERGED
 * (front wins ties) to pre-fill Step 2's inputs. The normalized photos are
 * written into the real `id_photo_front` / `id_photo_back` file inputs via a
 * DataTransfer so the server's existing `store()` handling is untouched.
 *
 * Step 2 is the existing directory form, shown once both sides are captured.
 *
 * Only the client UI changes — the same multipart POST is submitted either way.
 */

// universal-field key → this form's (non-canonical) input name. Composite
// `course_year` is intentionally omitted (the form splits it into course +
// year_level); file/image universal fields can't populate <input type=file>.
const UNIVERSAL_TO_INPUT = {
    first_name: 'first_name',
    middle_name: 'middle_name',
    last_name: 'last_name',
    contact_number: 'contact_number',
    address: 'present_address',
    home_address: 'home_address',
    age: 'age',
    sex: 'sex',
    religion: 'religious_affiliation',
    nationality: 'nationality',
    birthday: 'birthday',
    birthplace: 'birthplace',
    parents_guardian: 'parents_guardian',
    talents_hobbies: 'talents_hobbies',
    student_id: 'student_id',
};

// Every scanned photo is re-encoded through a canvas to fit the server's 2 MB
// upload cap (`max:2048` on both /id-scan and the final form submit) — phone
// captures/uploads routinely exceed it, which made scanning fail the instant a
// photo landed. Re-encoding also bakes EXIF rotation into the pixels; the OCR
// sidecar reads pixels only, so a sideways phone photo would miss every zone.
const PHOTO_MAX_EDGE = 1600; // px, long edge after downscale — ample for zone OCR
const PHOTO_MAX_BYTES = 1900 * 1024; // safely under the 2048 KB validation cap
const PHOTO_QUALITIES = [0.85, 0.7, 0.55]; // JPEG quality ladder, first fit wins

export function idScanWizard(config = {}) {
    return {
        step: 1,
        scanUrl: config.scanUrl,
        csrf: config.csrf,
        // 'vertical' (portrait, default) | 'horizontal' — sizes the finder overlay
        // to match the active template's ID orientation.
        orientation: config.orientation === 'horizontal' ? 'horizontal' : 'vertical',
        stream: null,
        cameraOn: false,
        cameraError: '',
        scanning: false,
        scanNote: '',
        scanSide: 'front', // which side the camera/upload currently targets
        frontPreview: null,
        backPreview: null,
        detected: {}, // universal_key => value we applied to an input (across both sides)

        init() {
            // A failed submit round-trips old() into Step 2 — land the user there.
            if (config.hasErrors) this.step = 2;
        },

        /** CSS aspect-ratio for the finder overlay, per orientation. */
        get overlayAspect() {
            return this.orientation === 'vertical' ? '1 / 1.586' : '1.586 / 1';
        },

        /** Both sides captured — required before continuing to the form. */
        get canContinue() {
            return !!this.frontPreview && !!this.backPreview;
        },

        previewFor(side) {
            return side === 'back' ? this.backPreview : this.frontPreview;
        },

        /** Detected fields as a list for x-for rendering. */
        get detectedEntries() {
            return Object.entries(this.detected).map(([key, value]) => ({ key, value }));
        },

        // --- side selection ---
        selectSide(side) {
            this.scanSide = side === 'back' ? 'back' : 'front';
            this.scanNote = '';
        },

        // --- camera ---
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

        /** Grab the current video frame as the current side's ID photo. */
        capture() {
            const video = this.$refs.video;
            if (!video || !video.videoWidth) return;

            const side = this.scanSide;
            const canvas = this.$refs.canvas;
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

            canvas.toBlob((blob) => {
                if (!blob) return;
                const file = new File([blob], `id-${side}.jpg`, { type: 'image/jpeg' });
                this.applyFile(file, side);
                this.stopCamera();
            }, 'image/jpeg', 0.92);
        },

        // --- upload fallback ---
        onUpload(event, side) {
            const file = event.target.files && event.target.files[0];
            if (file) this.applyFile(file, side);
        },

        /**
         * Normalize a captured/uploaded photo, put it onto the real
         * `id_photo_<side>` input (replacing any oversized original so the
         * eventual form POST passes the same 2 MB cap), preview it, and scan it.
         */
        async applyFile(file, side) {
            const prepared = await this.normalizePhoto(file, side);

            const inputId = side === 'back' ? 'id_photo_back' : 'id_photo_front';
            const input = document.getElementById(inputId);
            if (input) {
                const dt = new DataTransfer();
                dt.items.add(prepared);
                input.files = dt.files;
            }
            const key = side === 'back' ? 'backPreview' : 'frontPreview';
            if (this[key]) URL.revokeObjectURL(this[key]);
            this[key] = URL.createObjectURL(prepared);
            this.scan(prepared, side);
        },

        /**
         * Downscale + re-encode a photo as JPEG within PHOTO_MAX_EDGE /
         * PHOTO_MAX_BYTES. Returns the original file when it cannot be decoded
         * — the server then reports it as an invalid photo instead of us
         * guessing here.
         */
        async normalizePhoto(file, side) {
            let source = null;
            let objectUrl = null;
            try {
                if (window.createImageBitmap) {
                    source = await createImageBitmap(file);
                } else {
                    objectUrl = URL.createObjectURL(file);
                    source = new Image();
                    source.src = objectUrl;
                    await source.decode();
                }
                const width = source.naturalWidth || source.width;
                const height = source.naturalHeight || source.height;
                if (!width || !height) return file;

                const scale = Math.min(1, PHOTO_MAX_EDGE / Math.max(width, height));
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(width * scale));
                canvas.height = Math.max(1, Math.round(height * scale));
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#fff'; // PNG transparency would turn black in JPEG
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(source, 0, 0, canvas.width, canvas.height);

                let blob = null;
                for (const quality of PHOTO_QUALITIES) {
                    blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
                    if (blob && blob.size <= PHOTO_MAX_BYTES) break;
                }
                if (!blob) return file;

                return new File([blob], `id-${side}.jpg`, { type: 'image/jpeg' });
            } catch (e) {
                return file;
            } finally {
                if (objectUrl) URL.revokeObjectURL(objectUrl);
                if (source && typeof source.close === 'function') source.close();
            }
        },

        async scan(file, side) {
            this.scanning = true;
            this.scanNote = `Reading the ${side} of your ID…`;
            try {
                const body = new FormData();
                body.append('photo', file);
                body.append('side', side);
                body.append('_token', this.csrf);
                // Ask for JSON explicitly so validation/session errors come back
                // parseable instead of as an HTML redirect, which used to make
                // every problem look like an instant, unexplained scan failure.
                const res = await fetch(this.scanUrl, {
                    method: 'POST',
                    body,
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) {
                    this.scanNote = res.status === 419
                        ? 'This page sat open for a while — refresh it and scan again.'
                        : 'The ID reader hit a problem — you can fill the form manually.';
                    return;
                }
                const json = await res.json();

                const fields = (json && json.fields) || {};
                // Back-compat: older templates only expose student_id at top level.
                if (json && json.student_id && !fields.student_id) {
                    fields.student_id = json.student_id;
                }

                this.applyPrefill(fields);
                this.scanNote = this.noteFor(json);
            } catch (e) {
                this.scanNote = 'Could not read the ID — you can fill the form manually.';
            } finally {
                this.scanning = false;
                // After the front, nudge the user to capture the back next.
                if (side === 'front' && !this.backPreview) this.selectSide('back');
            }
        },

        /**
         * Choose the status message after a scan. Actionable failure reasons the
         * server reports via `note` win over the "read something" message, so a
         * failing side is never masked by an earlier successful one.
         */
        noteFor(json) {
            const note = json && json.note;
            if (note === 'invalid photo') {
                return 'That photo couldn’t be used — retake it or upload a clear JPG/PNG under 2 MB.';
            }
            if (note === 'no active template') {
                return 'ID scanning isn’t configured yet — please fill the form manually.';
            }
            if (note === 'scanner unavailable' || note === 'scanner error') {
                return 'The ID reader is temporarily unavailable — please fill the form manually.';
            }
            if (Object.keys(this.detected).length) {
                return 'Auto-filled from your ID — please verify each field.';
            }
            return 'No details detected — you can fill the form manually.';
        },

        /**
         * Merge universal keys onto this form's inputs, never clobbering typed
         * values and never overwriting a value already detected from the other side.
         */
        applyPrefill(fields) {
            Object.entries(fields || {}).forEach(([key, value]) => {
                if (value === null || value === undefined || value === '') return;
                if (this.detected[key]) return; // first side to detect a key wins
                const name = UNIVERSAL_TO_INPUT[key];
                if (!name) return;
                const input = document.querySelector(`[name="${name}"]`);
                if (!input) return;
                if (!input.value) {
                    input.value = value;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
                input.classList.add('id-autofilled');
                this.detected[key] = value;
            });
        },

        // --- navigation ---
        continueToForm() {
            this.stopCamera();
            this.step = 2;
        },

        backToScan() {
            this.step = 1;
        },
    };
}
