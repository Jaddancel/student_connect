@props(['form'])

@php
    $canScan = $form && ($form->allows_guest_scan || auth()->check());
@endphp

@if($canScan)
<div x-data="{
    file: null,
    fileName: null,
    status: 'idle',
    errorMsg: null,
    pollTimer: null,
    isDragging: false,
    cameraOpen: false,
    cameraError: null,
    stream: null,

    handleDrop(e) {
        this.isDragging = false;
        const f = e.dataTransfer.files[0];
        if (f && /^image\/(jpeg|png|jpg|webp)$/.test(f.type)) {
            this.file = f;
            this.fileName = f.name;
        }
    },
    handleChange(e) {
        const f = e.target.files[0];
        if (f) { this.file = f; this.fileName = f.name; }
    },

    async openCamera() {
        this.cameraError = null;
        this.cameraOpen = true;
        await this.$nextTick();
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } },
                audio: false,
            });
            this.$refs.video.srcObject = this.stream;
            await this.$refs.video.play();
        } catch (err) {
            this.cameraError = err.name === 'NotAllowedError'
                ? 'Camera permission denied. Please allow camera access and try again.'
                : 'Could not access camera. Try uploading a file instead.';
            this.closeCamera();
        }
    },
    closeCamera() {
        if (this.stream) {
            this.stream.getTracks().forEach(t => t.stop());
            this.stream = null;
        }
        this.cameraOpen = false;
    },
    capture() {
        const video = this.$refs.video;
        const canvas = this.$refs.canvas;
        canvas.width  = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        canvas.toBlob(blob => {
            this.file = new File([blob], 'camera-capture.jpg', { type: 'image/jpeg' });
            this.fileName = 'camera-capture.jpg';
            this.closeCamera();
        }, 'image/jpeg', 0.92);
    },

    async upload() {
        if (!this.file) return;
        this.status = 'uploading';
        this.errorMsg = null;

        const fd = new FormData();
        fd.append('image', this.file);

        try {
            const res = await fetch('{{ route('api.forms.scan', $form) }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: fd,
            });

            if (!res.ok) {
                const body = await res.json().catch(() => ({}));
                this.errorMsg = body.message || 'Upload failed. Please try again.';
                this.status = 'error';
                return;
            }

            const data = await res.json();
            this.status = 'processing';
            this.poll(data.scan_id);

        } catch (e) {
            this.errorMsg = 'Network error. Please try again.';
            this.status = 'error';
        }
    },
    poll(scanId) {
        clearInterval(this.pollTimer);
        let attempts = 0;
        const MAX_ATTEMPTS = 30; // 60 seconds at 2s intervals
        this.pollTimer = setInterval(async () => {
            attempts++;
            if (attempts > MAX_ATTEMPTS) {
                clearInterval(this.pollTimer);
                this.status = 'error';
                this.errorMsg = 'OCR is taking too long. Please try again.';
                return;
            }
            try {
                const res = await fetch(`/api/form-scans/${scanId}/result`);
                if (!res.ok) {
                    clearInterval(this.pollTimer);
                    this.status = 'error';
                    this.errorMsg = 'Could not check scan status. Please try again.';
                    return;
                }
                const data = await res.json();

                if (data.status === 'done') {
                    clearInterval(this.pollTimer);
                    this.status = 'done';
                    window.dispatchEvent(new CustomEvent('ocr-result', { detail: { fieldMap: data.ocr_result || {} } }));
                } else if (data.status === 'failed') {
                    clearInterval(this.pollTimer);
                    this.status = 'error';
                    this.errorMsg = 'OCR processing failed. Please try a clearer image.';
                }
            } catch {
                clearInterval(this.pollTimer);
                this.status = 'error';
                this.errorMsg = 'Network error while checking scan status. Please try again.';
            }
        }, 2000);
    },
    reset() {
        clearInterval(this.pollTimer);
        this.closeCamera();
        this.file = null;
        this.fileName = null;
        this.status = 'idle';
        this.errorMsg = null;
        this.cameraError = null;
        this.$refs.fileInput.value = '';
    }
}"
     class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">

    <div class="mb-3 flex items-center justify-between">
        <div>
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Pre-fill via Scanned Form</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Upload a photo or use your camera — we'll auto-fill the fields below.
            </p>
        </div>
        <button type="button" x-show="status !== 'idle' || cameraOpen" @click="reset()"
                class="text-xs text-gray-400 underline hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
            Reset
        </button>
    </div>

    {{-- Camera error banner --}}
    <div x-show="cameraError" class="mb-3 rounded-lg bg-error-50 px-3 py-2 text-xs text-error-700 dark:bg-error-900/20 dark:text-error-400" x-text="cameraError"></div>

    {{-- Camera viewfinder --}}
    <div x-show="cameraOpen" class="relative overflow-hidden rounded-xl bg-black">
        <video x-ref="video" autoplay playsinline muted class="w-full rounded-xl"></video>
        <canvas x-ref="canvas" class="hidden"></canvas>

        <div class="absolute inset-x-0 bottom-4 flex justify-center gap-3">
            <button type="button" @click="closeCamera()"
                    class="rounded-full bg-white/20 px-4 py-2 text-sm font-medium text-white backdrop-blur hover:bg-white/30">
                Cancel
            </button>
            <button type="button" @click="capture()"
                    class="rounded-full bg-white px-5 py-2 text-sm font-semibold text-gray-900 shadow hover:bg-gray-100">
                Capture
            </button>
        </div>
    </div>

    {{-- Drop zone (shown when idle and camera is closed) --}}
    <div x-show="status === 'idle' && !cameraOpen">
        <div @drop.prevent="handleDrop($event)"
             @dragover.prevent="isDragging = true"
             @dragleave.prevent="isDragging = false"
             @click="$refs.fileInput.click()"
             :class="isDragging ? 'border-brand-400 bg-brand-50 dark:border-brand-600 dark:bg-brand-900/10' : 'border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/30'"
             class="cursor-pointer rounded-xl border-2 border-dashed p-6 text-center transition-colors">

            <input x-ref="fileInput" type="file" accept="image/jpeg,image/png,image/jpg,image/webp"
                   @change="handleChange($event)" class="hidden" />

            <template x-if="!fileName">
                <div>
                    <svg class="mx-auto mb-2 h-8 w-8 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M3 16l4-4 4 4 5-6 5 6M3 20h18M5 8h.01M19 8h.01" />
                    </svg>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Drag &amp; drop or click to upload a scan</p>
                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">JPEG, PNG, WebP — max 10 MB</p>
                </div>
            </template>

            <template x-if="fileName">
                <div>
                    <svg class="mx-auto mb-1 h-6 w-6 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300" x-text="fileName"></p>
                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Click to change</p>
                </div>
            </template>
        </div>

        {{-- Action buttons --}}
        <div class="mt-3 flex items-center justify-between gap-2">
            <button type="button" @click="openCamera()"
                    class="flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Use Camera
            </button>

            <button type="button" x-show="fileName" @click="upload()"
                    class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600">
                Scan &amp; Pre-fill
            </button>
        </div>
    </div>

    {{-- Uploading --}}
    <div x-show="status === 'uploading'" class="flex items-center gap-3 py-4">
        <svg class="h-5 w-5 animate-spin text-brand-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
        </svg>
        <span class="text-sm text-gray-600 dark:text-gray-400">Uploading scan…</span>
    </div>

    {{-- Processing --}}
    <div x-show="status === 'processing'" class="flex items-center gap-3 py-4">
        <svg class="h-5 w-5 animate-spin text-brand-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
        </svg>
        <span class="text-sm text-gray-600 dark:text-gray-400">Extracting text from scan… this may take a few seconds.</span>
    </div>

    {{-- Done --}}
    <div x-show="status === 'done'" class="flex items-center gap-3 py-4">
        <svg class="h-5 w-5 text-success-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="text-sm font-medium text-success-700 dark:text-success-400">
            Fields pre-filled! Review the highlighted values below and correct any errors before submitting.
        </span>
    </div>

    {{-- Error --}}
    <div x-show="status === 'error'" class="flex items-start gap-3 py-4">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-error-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="text-sm text-error-700 dark:text-error-400" x-text="errorMsg"></span>
    </div>

</div>
@endif
