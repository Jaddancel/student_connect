/**
 * Two-step signup wizard for the student-leader directory form.
 *
 * Step 1 is a live-camera ID scanner (with an ID-card finder overlay and an
 * upload fallback): a captured/uploaded front-ID photo is POSTed to `/id-scan`,
 * and the returned universal-keyed `fields` map pre-fills Step 2's inputs. The
 * captured frame is written into the real `id_photo_front` file input via a
 * DataTransfer so the server's existing `store()` handling is untouched.
 *
 * Step 2 is the existing directory form, shown once the user continues.
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
        stream: null,
        cameraOn: false,
        cameraError: '',
        scanning: false,
        scanNote: '',
        frontPreview: null,
        detected: {}, // universal_key => value we applied to an input

        init() {
            // A failed submit round-trips old() into Step 2 — land the user there.
            if (config.hasErrors) this.step = 2;
        },

        /** Detected fields as a list for x-for rendering. */
        get detectedEntries() {
            return Object.entries(this.detected).map(([key, value]) => ({ key, value }));
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

        /** Grab the current video frame and treat it as the front-ID photo. */
        capture() {
            const video = this.$refs.video;
            if (!video || !video.videoWidth) return;

            const canvas = this.$refs.canvas;
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

            canvas.toBlob((blob) => {
                if (!blob) return;
                const file = new File([blob], 'id-front.jpg', { type: 'image/jpeg' });
                this.applyFrontFile(file);
                this.stopCamera();
            }, 'image/jpeg', 0.92);
        },

        // --- upload fallback ---
        onUploadFront(event) {
            const file = event.target.files && event.target.files[0];
            if (file) this.applyFrontFile(file, true); // file is already on the input
        },

        /**
         * Put the front-ID file onto the real `id_photo_front` input (unless the
         * native change already did), preview it, and scan it.
         */
        applyFrontFile(file, alreadyOnInput = false) {
            const input = document.getElementById('id_photo_front');
            if (input && !alreadyOnInput) {
                const dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
            }
            if (this.frontPreview) URL.revokeObjectURL(this.frontPreview);
            this.frontPreview = URL.createObjectURL(file);
            this.scan(file);
        },

        async scan(file) {
            this.scanning = true;
            this.scanNote = 'Reading your ID…';
            try {
                const body = new FormData();
                body.append('photo', file);
                body.append('_token', this.csrf);
                const res = await fetch(this.scanUrl, { method: 'POST', body });
                const json = await res.json();

                const fields = (json && json.fields) || {};
                // Back-compat: older templates only expose student_id at top level.
                if (json && json.student_id && !fields.student_id) {
                    fields.student_id = json.student_id;
                }

                this.applyPrefill(fields);
                this.scanNote = Object.keys(this.detected).length
                    ? 'Auto-filled from your ID — please verify each field.'
                    : 'No details detected — you can fill the form manually.';
            } catch (e) {
                this.scanNote = 'Could not read the ID — you can fill the form manually.';
            } finally {
                this.scanning = false;
            }
        },

        /** Map universal keys onto this form's inputs, never clobbering typed values. */
        applyPrefill(fields) {
            this.detected = {};
            Object.entries(fields || {}).forEach(([key, value]) => {
                if (value === null || value === undefined || value === '') return;
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
