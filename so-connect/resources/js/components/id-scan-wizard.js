/**
 * Two-step signup wizard for the student-leader directory form.
 *
 * Step 1 is a live-camera ID scanner (with an ID-card finder overlay and an
 * upload fallback) that captures BOTH sides of the ID in sequence: the user
 * scans the front, then the back. Camera captures are cropped to the finder
 * silhouette — only the framed card is scanned, matching the template's
 * tightly-cropped reference image. Each captured/uploaded photo is normalized
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

// Marks the File a scan injected into a signature input, so a rescan can tell
// the injected crop apart from a file the user picked and only discard ours.
const SCAN_SIGNATURE_FILENAME = 'id-signature.png';

/**
 * Map a box measured over an `object-fit: cover` element back into intrinsic
 * source-frame pixels. `view` and `box` are DOMRect-likes in the same
 * coordinate space (both from getBoundingClientRect()).
 *
 * The wizard uses this to crop a webcam capture to the ID finder silhouette:
 * the OCR template's zones are authored against a tightly-cropped reference
 * image of the ID, so only the framed card — never the scene around it — may
 * be sent to the scanner. Returns null when the mapping is degenerate.
 */
export function mapCoverBoxToFrame(frame, view, box) {
    const scale = Math.max(view.width / frame.width, view.height / frame.height);
    if (!Number.isFinite(scale) || scale <= 0) return null;

    // cover centers the scaled frame inside the element and crops the overflow.
    const offsetX = (view.width - frame.width * scale) / 2;
    const offsetY = (view.height - frame.height * scale) / 2;
    const sx = (box.left - view.left - offsetX) / scale;
    const sy = (box.top - view.top - offsetY) / scale;
    const sw = box.width / scale;
    const sh = box.height / scale;

    // Clamp to the frame to absorb border and rounding slop.
    const x1 = Math.max(0, Math.min(frame.width, sx));
    const y1 = Math.max(0, Math.min(frame.height, sy));
    const x2 = Math.max(0, Math.min(frame.width, sx + sw));
    const y2 = Math.max(0, Math.min(frame.height, sy + sh));
    if (x2 - x1 < 1 || y2 - y1 < 1) return null;

    return { sx: x1, sy: y1, sw: x2 - x1, sh: y2 - y1 };
}

export function idScanWizard(config = {}) {
    return {
        step: 1,
        scanUrl: config.scanUrl,
        csrf: config.csrf,
        // 'vertical' (portrait, default) | 'horizontal' — sizes the finder overlay
        // to match the active template's ID orientation.
        orientation: config.orientation === 'horizontal' ? 'horizontal' : 'vertical',
        // Active ID templates ({id, name, orientation, photo, signature_sides});
        // 2+ puts a chooser in front of the camera, and the pick drives the scan.
        templates: Array.isArray(config.templates) ? config.templates : [],
        templateId: null,
        stream: null,
        cameraOn: false,
        cameraError: '',
        scanning: false,
        scanNote: '',
        scanSide: 'front', // which side the camera/upload currently targets
        frontPreview: null,
        backPreview: null,
        detected: {}, // universal_key => value we applied to an input (across both sides)
        // Rescan bookkeeping: exactly what each side's scan wrote into inputs
        // (key => value), so a retry can take back ONLY its own autofill.
        autofilled: { front: {}, back: {} },
        sidesScanned: { front: false, back: false },
        signatureCaptured: false,
        signatureSide: null, // which side produced the signature crop

        init() {
            if (this.templates.length === 1) this.chooseTemplate(this.templates[0].id);
            // A validation failure on some other field forces a reload; the
            // browser can't resubmit the id_photo_front/back file inputs, but
            // the server cached this session's scan (see IdScanRetryCache) —
            // replay it so the user isn't sent back to a bare scanner.
            if (config.retry) this.hydrateFromRetry(config.retry);
            // A failed submit round-trips old() into Step 2 — land the user there.
            if (config.hasErrors) this.step = 2;
        },

        /**
         * Restore previews + detected fields from this session's cached scan
         * (config.retry: { front?: {url, fields}, back?: {url, fields} }).
         * The real id_photo_front/back inputs stay empty client-side — the
         * server falls back to its own cached copy at submit time if they're
         * still empty, so this is purely a UI restore, not a re-upload.
         */
        hydrateFromRetry(retry) {
            ['front', 'back'].forEach((side) => {
                const data = retry[side];
                if (!data) return;
                const key = side === 'back' ? 'backPreview' : 'frontPreview';
                this[key] = data.url;
                this.sidesScanned[side] = true;
                this.applyPrefill(data.fields || {}, side);
            });
            if (this.frontPreview || this.backPreview) {
                this.scanNote = 'Using the ID you already scanned.';
            }
        },

        // --- template chooser (2+ active templates) ---
        get needsChooser() {
            return this.templates.length >= 2 && !this.templateId;
        },

        get selectedTemplate() {
            return this.templates.find((t) => t.id === this.templateId) || null;
        },

        chooseTemplate(id) {
            const template = this.templates.find((t) => t.id === id);
            if (!template) return;
            if (this.templateId && this.templateId !== id) this.resetScans();
            this.templateId = id;
            this.orientation = template.orientation === 'horizontal' ? 'horizontal' : 'vertical';
        },

        changeTemplate() {
            this.stopCamera();
            this.resetScans();
            this.templateId = null;
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

        /**
         * The signature line under the scan results: whether a signature was
         * scanned or none was, phrased by what the chosen template can read.
         * Null until a side has actually been scanned.
         */
        get signatureNote() {
            if (this.signatureCaptured) {
                return { tone: 'success', text: 'Signature captured from your ID.' };
            }
            if (!this.templates.length || (!this.sidesScanned.front && !this.sidesScanned.back)) {
                return null;
            }
            const sides = (this.selectedTemplate && this.selectedTemplate.signature_sides) || null;
            const expected = ['front', 'back'].filter((s) => sides && sides[s]);
            if (!expected.length) {
                return { tone: 'muted', text: 'No signature was scanned from this ID — you can add yours in Step 2.' };
            }
            if (expected.every((s) => this.sidesScanned[s])) {
                return { tone: 'warning', text: 'No signature was detected on your ID — please upload or draw one in Step 2.' };
            }
            const waiting = expected.filter((s) => !this.sidesScanned[s]);
            return { tone: 'muted', text: `Your signature is read from the ${waiting.join(' and ')} of this ID — scan it to capture your signature.` };
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

        /**
         * Grab the finder-silhouette region of the current video frame as the
         * current side's ID photo — cropping to what the overlay told the user
         * is being scanned, so the photo matches the template's tightly-cropped
         * reference geometry.
         */
        capture() {
            const video = this.$refs.video;
            if (!video || !video.videoWidth) return;

            const side = this.scanSide;
            const canvas = this.$refs.canvas;
            const region = this.finderRegion(video);
            canvas.width = Math.round(region.sw);
            canvas.height = Math.round(region.sh);
            canvas.getContext('2d').drawImage(
                video,
                region.sx, region.sy, region.sw, region.sh,
                0, 0, canvas.width, canvas.height,
            );

            canvas.toBlob((blob) => {
                if (!blob) return;
                const file = new File([blob], `id-${side}.jpg`, { type: 'image/jpeg' });
                this.applyFile(file, side);
                this.stopCamera();
            }, 'image/jpeg', 0.92);
        },

        /**
         * The finder-overlay rectangle in intrinsic video-frame pixels — the
         * only part of the frame the user was told is being scanned. Falls back
         * to the full frame when the overlay cannot be measured.
         */
        finderRegion(video) {
            const full = { sx: 0, sy: 0, sw: video.videoWidth, sh: video.videoHeight };
            const finder = this.$refs.finder;
            if (!finder) return full;

            const view = video.getBoundingClientRect();
            const box = finder.getBoundingClientRect();
            if (!view.width || !view.height || !box.width || !box.height) return full;

            return mapCoverBoxToFrame(
                { width: video.videoWidth, height: video.videoHeight },
                view,
                box,
            ) || full;
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
            // A retry of this side must not keep the previous attempt's details.
            this.discardSide(side);

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
                if (this.templateId) body.append('template_id', this.templateId);
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

                this.applyPrefill(fields, side);
                this.applySignatureCrop((json && json.images) || {}, side);
                this.sidesScanned[side] = true;
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
            if (note === 'no back zones') {
                return 'Nothing is read from the back of this ID — you can continue to the form.';
            }
            if (Object.keys(this.detected).length) {
                return 'Auto-filled from your ID — please verify each field.';
            }
            return 'No details detected — you can fill the form manually.';
        },

        /**
         * Find the form control a universal key writes to: an explicit
         * `data-universal-key` marker wins (drilling into wrapper elements),
         * with this form's hardcoded name map as the fallback.
         */
        resolveInput(key) {
            const marked = document.querySelector(`[data-universal-key="${key}"]`);
            if (marked) {
                return marked.matches('input, textarea, select')
                    ? marked
                    : marked.querySelector('input:not([type="hidden"]), textarea, select');
            }
            const name = UNIVERSAL_TO_INPUT[key];
            return name ? document.querySelector(`[name="${name}"]`) : null;
        },

        /**
         * Merge universal keys onto this form's inputs, never clobbering typed
         * values and never overwriting a value already detected from the other side.
         */
        applyPrefill(fields, side) {
            Object.entries(fields || {}).forEach(([key, value]) => {
                if (value === null || value === undefined || value === '') return;
                if (this.detected[key]) return; // first side to detect a key wins
                const input = this.resolveInput(key);
                if (!input || input.type === 'file') return;
                if (!input.value) {
                    input.value = value;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    this.autofilled[side][key] = String(value);
                }
                input.classList.add('id-autofilled');
                this.detected[key] = value;
            });
        },

        /**
         * Where a scanned signature crop may land, within this wizard's own form.
         *
         * A field bound to the universal `signature` key is an explicit target.
         * Without one, a lone signature field is still unambiguous — that box is
         * the signer's — so it is filled too; the binding is an authoring detail
         * the person scanning their ID cannot be expected to have set up. Several
         * unbound signature fields are NOT filled: a form that collects an
         * adviser's or a parent's signature alongside the signer's must say which
         * one the scanner owns (bind "Signature" in the builder's Autofill), or
         * the scan would sign someone else's box.
         */
        signatureTargets() {
            const scope = (this.$root && this.$root.closest('form')) || document;

            const bound = Array.from(scope.querySelectorAll('[data-universal-key="signature"]'));
            if (bound.length) return bound;

            const legacy = scope.querySelector('input[type="file"][name="signature"]');
            if (legacy) return [legacy];

            const fields = Array.from(scope.querySelectorAll('[data-signature-field]'));
            return fields.length === 1 ? fields : [];
        },

        /**
         * A signature-type template zone returns its crop as a data-URL under
         * `images.signature`. Feed it into this form's signature target(s): file
         * inputs get a File via DataTransfer (like the ID photos), signature-pad
         * components get a `signature-set` event and draw it themselves. Anything
         * the user already provided is never replaced.
         */
        applySignatureCrop(images, side) {
            const dataUrl = images && images.signature;
            if (!dataUrl || this.signatureCaptured) return;

            let applied = 0;
            this.signatureTargets().forEach((el) => {
                if (el instanceof HTMLInputElement && el.type === 'file') {
                    if (el.files.length) return; // a manually-picked file wins
                    try {
                        const dt = new DataTransfer();
                        dt.items.add(this.dataUrlToFile(dataUrl));
                        el.files = dt.files;
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                        el.classList.add('id-autofilled');
                        applied++;
                    } catch (e) {
                        // A malformed crop just means no auto-filled signature.
                    }
                } else {
                    // The component keeps its value in a hidden input and ignores
                    // the event when it already holds one (a saved signature, or
                    // one the user drew) — read it back rather than claim a
                    // capture the form never took.
                    const holder = el.querySelector('input[type="hidden"]');
                    el.dispatchEvent(new CustomEvent('signature-set', { detail: { dataUrl } }));
                    if (!holder || holder.value) applied++;
                }
            });

            if (applied) {
                this.signatureCaptured = true;
                this.signatureSide = side;
                this.detected.signature = 'captured from ID';
            }
        },

        dataUrlToFile(dataUrl) {
            const [meta, b64] = dataUrl.split(',', 2);
            const mime = (meta.match(/^data:([^;]+)/) || [])[1] || 'image/png';
            const bytes = atob(b64);
            const buf = new Uint8Array(bytes.length);
            for (let i = 0; i < bytes.length; i++) buf[i] = bytes.charCodeAt(i);
            return new File([buf], SCAN_SIGNATURE_FILENAME, { type: mime });
        },

        /**
         * Take back everything a side's previous scan auto-filled, so a retry
         * starts clean. Inputs the user edited after the autofill keep their
         * value — only fields still holding the scanned value are cleared.
         */
        discardSide(side) {
            Object.entries(this.autofilled[side] || {}).forEach(([key, value]) => {
                const input = this.resolveInput(key);
                if (input && input.value === value) {
                    input.value = '';
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.classList.remove('id-autofilled');
                }
                delete this.detected[key];
            });
            this.autofilled[side] = {};
            if (this.signatureSide === side) this.discardSignature();
            this.sidesScanned[side] = false;
        },

        /** Remove a scanned signature crop wherever applySignatureCrop put it. */
        discardSignature() {
            this.signatureTargets().forEach((el) => {
                if (el instanceof HTMLInputElement && el.type === 'file') {
                    const current = el.files[0];
                    if (current && current.name === SCAN_SIGNATURE_FILENAME) {
                        el.value = '';
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                        el.classList.remove('id-autofilled');
                    }
                } else {
                    el.dispatchEvent(new CustomEvent('signature-clear'));
                }
            });
            delete this.detected.signature;
            this.signatureCaptured = false;
            this.signatureSide = null;
        },

        /** Full reset — used when the user switches to a different ID template. */
        resetScans() {
            this.discardSide('front');
            this.discardSide('back');
            ['id_photo_front', 'id_photo_back'].forEach((id) => {
                const input = document.getElementById(id);
                if (input) input.value = '';
            });
            if (this.frontPreview) URL.revokeObjectURL(this.frontPreview);
            if (this.backPreview) URL.revokeObjectURL(this.backPreview);
            this.frontPreview = null;
            this.backPreview = null;
            this.detected = {};
            this.scanNote = '';
            this.scanSide = 'front';
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
