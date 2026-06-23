@props([
    'form',
    'title' => 'Scan a filled form',
    'description' => 'Use your camera or upload a photo. We will read the text and pre-fill the fields below for you to review.',
])

@php
    // Result URL carries a placeholder swapped client-side once we have a scan id.
    $scanUrl = route('api.forms.scan', $form);
    $resultUrlTemplate = route('api.form-scans.result', ['scan' => '__ID__']);
@endphp

<div
    x-data="{
        tab: 'camera',
        stream: null,
        cameras: [],
        selectedCamera: null,
        hasMultipleCameras: false,
        scanStatus: 'idle', // idle | uploading | processing | done | error
        errorMsg: null,
        pollTimer: null,
        scanUrl: @js($scanUrl),
        resultUrlTemplate: @js($resultUrlTemplate),
        csrf: document.querySelector('meta[name=&quot;csrf-token&quot;]')?.content ?? '',

        init() {
            // Attempt camera on mount; silently fall back to upload if unavailable.
            this.$nextTick(() => this.startCamera());
        },

        async selectTab(tab) {
            this.tab = tab;
            if (tab === 'camera') {
                await this.startCamera();
            } else {
                this.stopCamera();
            }
        },

        async startCamera() {
            if (!navigator.mediaDevices?.getUserMedia) {
                this.fallbackToUpload('Camera is not supported on this device.');
                return;
            }
            this.stopCamera();
            try {
                const constraints = {
                    video: this.selectedCamera
                        ? { deviceId: { exact: this.selectedCamera } }
                        : { facingMode: 'environment' },
                };
                this.stream = await navigator.mediaDevices.getUserMedia(constraints);
                this.$refs.video.srcObject = this.stream;
                await this.enumerateCameras();
            } catch (e) {
                this.fallbackToUpload('Camera unavailable. Please upload a photo instead.');
            }
        },

        async enumerateCameras() {
            try {
                const devices = await navigator.mediaDevices.enumerateDevices();
                this.cameras = devices.filter(d => d.kind === 'videoinput');
                this.hasMultipleCameras = this.cameras.length > 1;
                if (!this.selectedCamera && this.stream) {
                    const track = this.stream.getVideoTracks()[0];
                    this.selectedCamera = track?.getSettings?.().deviceId ?? null;
                }
            } catch (e) { /* enumeration is best-effort */ }
        },

        async switchCamera() {
            if (this.cameras.length < 2) return;
            const idx = this.cameras.findIndex(c => c.deviceId === this.selectedCamera);
            this.selectedCamera = this.cameras[(idx + 1) % this.cameras.length].deviceId;
            await this.startCamera();
        },

        fallbackToUpload(message) {
            this.tab = 'upload';
            this.errorMsg = message;
        },

        capture() {
            const video = this.$refs.video;
            if (!video || !video.videoWidth) return;
            const canvas = this.$refs.canvas;
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            canvas.toBlob((blob) => {
                if (blob) this.upload(blob, 'scan.jpg');
            }, 'image/jpeg', 0.92);
        },

        onFileChosen(event) {
            const file = event.target.files[0];
            if (file) this.upload(file, file.name);
        },

        async upload(fileOrBlob, filename) {
            this.scanStatus = 'uploading';
            this.errorMsg = null;
            this.stopCamera();

            const fd = new FormData();
            fd.append('image', fileOrBlob, filename);

            try {
                const res = await fetch(this.scanUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                    body: fd,
                });
                if (!res.ok) throw new Error('Upload failed (' + res.status + ')');
                const data = await res.json();
                this.scanStatus = 'processing';
                this.pollResult(data.scan_id);
            } catch (e) {
                this.scanStatus = 'error';
                this.errorMsg = 'Upload failed. Please try again.';
            }
        },

        pollResult(scanId) {
            const url = this.resultUrlTemplate.replace('__ID__', scanId);
            this.pollTimer = setInterval(async () => {
                try {
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    if (data.status === 'done') {
                        clearInterval(this.pollTimer);
                        this.scanStatus = 'done';
                        window.dispatchEvent(new CustomEvent('ocr-result', {
                            detail: { fieldMap: data.ocr_result || {} },
                        }));
                    } else if (data.status === 'failed') {
                        clearInterval(this.pollTimer);
                        this.scanStatus = 'error';
                        this.errorMsg = data.error_message || 'Scan failed. Please try again.';
                    }
                } catch (e) {
                    clearInterval(this.pollTimer);
                    this.scanStatus = 'error';
                    this.errorMsg = 'Could not retrieve scan result.';
                }
            }, 2000);
        },

        stopCamera() {
            if (this.stream) {
                this.stream.getTracks().forEach(t => t.stop());
                this.stream = null;
            }
        },

        retry() {
            this.scanStatus = 'idle';
            this.errorMsg = null;
            if (this.tab === 'camera') this.startCamera();
        },

        destroy() {
            this.stopCamera();
            if (this.pollTimer) clearInterval(this.pollTimer);
        },
    }"
    x-init="init()"
    x-destroy="destroy()"
    class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6"
>
    <div class="mb-4 flex items-start justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mb-4 inline-flex rounded-lg border border-gray-200 p-1 dark:border-gray-700">
        <button type="button" @click="selectTab('camera')"
            :class="tab === 'camera' ? 'bg-brand-500 text-white' : 'text-gray-600 dark:text-gray-300'"
            class="rounded-md px-4 py-1.5 text-sm font-medium transition">Camera</button>
        <button type="button" @click="selectTab('upload')"
            :class="tab === 'upload' ? 'bg-brand-500 text-white' : 'text-gray-600 dark:text-gray-300'"
            class="rounded-md px-4 py-1.5 text-sm font-medium transition">Upload File</button>
    </div>

    {{-- Camera tab --}}
    <div x-show="tab === 'camera'" x-cloak>
        <div class="relative overflow-hidden rounded-xl bg-black/90">
            <video x-ref="video" autoplay playsinline muted class="mx-auto max-h-80 w-full object-contain"></video>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <button type="button" @click="capture()"
                :disabled="scanStatus === 'uploading' || scanStatus === 'processing'"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600 disabled:opacity-50">
                Capture &amp; Scan
            </button>
            <button type="button" x-show="hasMultipleCameras" @click="switchCamera()"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                Switch Camera
            </button>
        </div>
    </div>

    {{-- Upload tab --}}
    <div x-show="tab === 'upload'" x-cloak>
        <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 px-6 py-10 text-center transition hover:border-brand-400 dark:border-gray-700">
            <svg class="mb-2 h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            </svg>
            <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Click to upload a photo of the form</span>
            <span class="mt-1 text-xs text-gray-400">JPG, PNG or WEBP, max 10 MB</span>
            <input type="file" accept="image/*" capture="environment" class="hidden" @change="onFileChosen($event)" />
        </label>
    </div>

    {{-- Hidden capture canvas --}}
    <canvas x-ref="canvas" class="hidden"></canvas>

    {{-- Status --}}
    <div class="mt-4" x-show="scanStatus !== 'idle'" x-cloak>
        <div x-show="scanStatus === 'uploading' || scanStatus === 'processing'"
            class="flex items-center gap-3 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-300">
            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <span x-text="scanStatus === 'uploading' ? 'Uploading image…' : 'Reading the form…'"></span>
        </div>

        <div x-show="scanStatus === 'done'"
            class="flex items-center gap-2 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
            Scan complete — fields below were pre-filled. Please review and correct them.
        </div>

        <div x-show="scanStatus === 'error'"
            class="flex items-center justify-between gap-3 rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
            <span x-text="errorMsg || 'Something went wrong.'"></span>
            <button type="button" @click="retry()" class="font-medium underline">Try again</button>
        </div>
    </div>

    {{-- Camera-denied fallback notice --}}
    <p x-show="errorMsg && tab === 'upload' && scanStatus === 'idle'" x-cloak
        class="mt-3 text-xs text-gray-500 dark:text-gray-400" x-text="errorMsg"></p>
</div>
