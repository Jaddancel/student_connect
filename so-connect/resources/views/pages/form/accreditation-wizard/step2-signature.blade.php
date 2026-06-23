@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Application for Recognition/Renewal" />

    <div class="space-y-6">

        <x-admin.wizard-progress :step="2" :total="3" :labels="[1 => 'Workplan', 2 => 'Signature', 3 => 'Review & Submit']" />

        {{-- Header --}}
        <div class="rounded-2xl border border-gray-200 bg-palette-lime-pale p-5 shadow-[inset_0_4px_0_var(--color-palette-lime)] dark:border-gray-800 dark:bg-white/[0.03] dark:shadow-[inset_0_4px_0_rgb(165_255_91_/_0.3)] lg:p-6">
            <div class="text-center">
                <h2 class="text-xl font-bold uppercase tracking-widest text-gray-900 dark:text-white">
                    Step 2 — President Signature
                </h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Upload or capture a photo of the president's signature. Use your rear camera on mobile for best results.
                </p>
            </div>
        </div>

        @if($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                <ul class="list-disc pl-4 space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form action="{{ route('accreditation.wizard.step2.save') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div x-data="{
                tab: 'camera',
                stream: null,
                cameras: [],
                selectedCamera: null,
                capturing: false,
                fileName: null,
                previewSrc: null,
                async startCamera() {
                    try {
                        const constraints = { video: this.selectedCamera
                            ? { deviceId: { exact: this.selectedCamera } }
                            : { facingMode: 'environment' } };
                        this.stream = await navigator.mediaDevices.getUserMedia(constraints);
                        this.$refs.video.srcObject = this.stream;
                        if (!this.selectedCamera) {
                            const devices = await navigator.mediaDevices.enumerateDevices();
                            this.cameras = devices.filter(d => d.kind === 'videoinput');
                        }
                    } catch (e) {
                        this.tab = 'upload';
                    }
                },
                capture() {
                    const canvas = this.$refs.canvas;
                    const video = this.$refs.video;
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    canvas.getContext('2d').drawImage(video, 0, 0);
                    this.previewSrc = canvas.toDataURL('image/jpeg', 0.9);
                    canvas.toBlob(blob => {
                        const dt = new DataTransfer();
                        dt.items.add(new File([blob], 'signature.jpg', { type: 'image/jpeg' }));
                        this.$refs.fileInput.files = dt.files;
                        this.fileName = 'Captured signature';
                    }, 'image/jpeg', 0.9);
                },
                stopCamera() {
                    if (this.stream) this.stream.getTracks().forEach(t => t.stop());
                    this.stream = null;
                },
                handleFile(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    this.fileName = file.name;
                    const reader = new FileReader();
                    reader.onload = ev => this.previewSrc = ev.target.result;
                    reader.readAsDataURL(file);
                },
                async switchCamera(deviceId) {
                    this.stopCamera();
                    this.selectedCamera = deviceId;
                    await this.startCamera();
                }
            }" @destroy="stopCamera()">

                {{-- Tab selector --}}
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="mb-5 flex rounded-lg border border-gray-200 p-0.5 dark:border-gray-700">
                        <button type="button" @click="tab='camera'; $nextTick(() => startCamera())"
                                :class="tab==='camera' ? 'bg-brand-500 text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/[0.04]'"
                                class="flex-1 rounded-md py-2 text-sm font-medium transition">
                            Camera
                        </button>
                        <button type="button" @click="tab='upload'; stopCamera()"
                                :class="tab==='upload' ? 'bg-brand-500 text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/[0.04]'"
                                class="flex-1 rounded-md py-2 text-sm font-medium transition">
                            Upload File
                        </button>
                    </div>

                    {{-- Camera tab --}}
                    <div x-show="tab==='camera'" x-init="$nextTick(() => { if (tab==='camera') startCamera(); })">
                        <div class="relative overflow-hidden rounded-xl bg-gray-900" style="min-height:200px">
                            <video x-ref="video" autoplay playsinline
                                   class="h-full w-full object-cover rounded-xl"></video>
                            <canvas x-ref="canvas" class="hidden"></canvas>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            @if(false) {{-- Alpine manages this --}} @endif
                            <template x-if="cameras.length > 1">
                                <select @change="switchCamera($event.target.value)"
                                        class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-white/90">
                                    <template x-for="cam in cameras" :key="cam.deviceId">
                                        <option :value="cam.deviceId" :selected="cam.deviceId===selectedCamera" x-text="cam.label||'Camera'"></option>
                                    </template>
                                </select>
                            </template>

                            <button type="button" @click="capture()"
                                    class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600">
                                Capture
                            </button>
                        </div>
                    </div>

                    {{-- Upload tab --}}
                    <div x-show="tab==='upload'" class="space-y-3">
                        <div @click="$refs.fileInput2.click()"
                             class="cursor-pointer rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 p-8 text-center dark:border-gray-700 dark:bg-gray-900/30">
                            <svg class="mx-auto mb-2 h-8 w-8 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Click to upload signature image</p>
                            <input type="file" x-ref="fileInput2" accept="image/jpeg,image/png"
                                   @change="handleFile($event); $refs.fileInput.files = $event.target.files"
                                   class="hidden" />
                        </div>
                    </div>

                    {{-- Shared hidden input --}}
                    <input type="file" x-ref="fileInput" name="signature_file" class="hidden" />

                    {{-- Preview --}}
                    <template x-if="previewSrc">
                        <div class="mt-4">
                            <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Preview</p>
                            <img :src="previewSrc" alt="Signature preview" class="h-24 rounded border border-gray-200 dark:border-gray-700 object-contain bg-white" />
                        </div>
                    </template>
                </div>

                @error('signature_file')
                    <p class="text-xs text-error-500">{{ $message }}</p>
                @enderror

                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex justify-between gap-3">
                        <a href="{{ route('organization-recognition') }}"
                           class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                            ← Back
                        </a>
                        <button type="submit"
                                :disabled="!fileName"
                                :class="fileName ? 'bg-brand-500 hover:bg-brand-600' : 'bg-gray-300 cursor-not-allowed dark:bg-gray-700'"
                                class="rounded-lg px-6 py-2.5 text-sm font-medium text-white transition">
                            Continue: Review →
                        </button>
                    </div>
                </div>

            </div>
        </form>

    </div>
@endsection
