/**
 * Two-step signup wizard for the student-leader directory form.
 *
 * Step 1 is a live-camera ID scanner (with an ID-card finder overlay and an
 * upload fallback) that captures BOTH sides of the ID in sequence: the user
 * scans the front, then the back. Each captured/uploaded photo is POSTed to
 * `/id-scan` with its `side`, and the returned universal-keyed `fields` maps are
 * MERGED (front wins ties) to pre-fill Step 2's inputs. The captured frames are
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
            if (file) this.applyFile(file, side, true); // file is already on the input
        },

        /**
         * Put a captured file onto the real `id_photo_<side>` input (unless the
         * native change already did), preview it, and scan it.
         */
        applyFile(file, side, alreadyOnInput = false) {
            const inputId = side === 'back' ? 'id_photo_back' : 'id_photo_front';
            const input = document.getElementById(inputId);
            if (input && !alreadyOnInput) {
                const dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
            }
            const key = side === 'back' ? 'backPreview' : 'frontPreview';
            if (this[key]) URL.revokeObjectURL(this[key]);
            this[key] = URL.createObjectURL(file);
            this.scan(file, side);
        },

        async scan(file, side) {
            this.scanning = true;
            this.scanNote = `Reading the ${side} of your ID…`;
            try {
                const body = new FormData();
                body.append('photo', file);
                body.append('side', side);
                body.append('_token', this.csrf);
                const res = await fetch(this.scanUrl, { method: 'POST', body });
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
         * Choose the status message after a scan, distinguishing "read something"
         * from the actionable failure reasons the server reports via `note`.
         */
        noteFor(json) {
            if (Object.keys(this.detected).length) {
                return 'Auto-filled from your ID — please verify each field.';
            }
            const note = json && json.note;
            if (note === 'no active template') {
                return 'ID scanning isn’t configured yet — please fill the form manually.';
            }
            if (note === 'scanner unavailable' || note === 'scanner error') {
                return 'The ID reader is temporarily unavailable — please fill the form manually.';
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
