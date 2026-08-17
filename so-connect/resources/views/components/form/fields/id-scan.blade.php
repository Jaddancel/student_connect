@php
    /**
     * ID-scan special field (sign-up kit): embeds the live-camera ID scanner.
     * Scanned photos land in the fixed id_photo_front/id_photo_back inputs and
     * detected values autofill sibling fields (via data-universal-key markers
     * or matching input names); the field's own value is the student number.
     *
     * Expects: $field, $old, $inputClass, $special (['scanner' => ...]).
     */
    $scanner = $special['scanner'] ?? null;
    $scannerOrientation = $scanner['orientation'] ?? 'vertical';
    $scannerTemplates = $scanner['templates'] ?? [];
    $scannerRetry = $scanner['retry'] ?? [];
@endphp

<div x-data="idScanWizard({ scanUrl: '{{ route('id-scan.scan') }}', csrf: '{{ csrf_token() }}', orientation: '{{ $scannerOrientation }}', templates: {{ Illuminate\Support\Js::from($scannerTemplates) }}, retry: {{ Illuminate\Support\Js::from($scannerRetry) }}, hasErrors: {{ $errors->any() ? 'true' : 'false' }} })"
    x-init="init()" class="space-y-4">

    {{-- Hidden capture inputs the wizard writes into (fixed names). --}}
    <input type="file" id="id_photo_front" name="id_photo_front" accept="image/jpeg,image/png" class="hidden" @change="onUpload($event, 'front')" />
    <input type="file" id="id_photo_back" name="id_photo_back" accept="image/jpeg,image/png" class="hidden" @change="onUpload($event, 'back')" />

    {{-- Collapsed summary once both sides are captured --}}
    <div x-show="step === 2" x-cloak class="flex flex-wrap items-center gap-3 rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm dark:border-success-500/30 dark:bg-success-500/10">
        <svg class="h-4 w-4 text-success-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
        <span class="font-medium text-success-700 dark:text-success-400">ID scanned — details below were pre-filled where possible.</span>
        <button type="button" @click="backToScan()" class="text-xs font-semibold text-brand-500 transition hover:text-brand-600">Rescan</button>
    </div>

    <div x-show="step === 1" class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="mb-1 text-base font-semibold text-gray-800 dark:text-white/90">Scan both sides of your ID</h3>

        {{-- ID-type chooser — shown while 2+ templates are active and none is picked --}}
        <div x-show="needsChooser" x-cloak>
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                More than one kind of ID can be scanned — pick the one that matches yours to start.
            </p>
            <div class="mx-auto grid max-w-lg grid-cols-1 gap-3 sm:grid-cols-2">
                <template x-for="t in templates" :key="t.id">
                    <button type="button" @click="chooseTemplate(t.id)"
                        class="group flex flex-col items-center gap-2 rounded-xl border border-gray-200 bg-white/60 p-3 transition hover:border-brand-400 hover:bg-brand-50 dark:border-gray-800 dark:bg-white/[0.02] dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                        <template x-if="t.photo">
                            <img :src="t.photo" :alt="t.name" class="h-28 w-full rounded-lg bg-gray-100 object-contain dark:bg-gray-900" />
                        </template>
                        <template x-if="!t.photo">
                            <div class="grid h-28 w-full place-items-center rounded-lg bg-gray-100 text-xs text-gray-400 dark:bg-gray-900">No preview</div>
                        </template>
                        <span class="text-sm font-medium text-gray-700 group-hover:text-brand-600 dark:text-gray-200 dark:group-hover:text-brand-400" x-text="t.name"></span>
                    </button>
                </template>
            </div>
        </div>

        <div x-show="!needsChooser">
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                Fit the <strong x-text="scanSide === 'front' ? 'front of your ID' : 'back of your ID'"></strong>
                inside the frame and capture — we'll read your details to save you typing. Prefer not to use the camera?
                Upload a photo instead. <strong>Both the front and back are required.</strong>
            </p>

            <div x-show="templates.length >= 2 && templateId" x-cloak
                class="mx-auto mb-3 flex max-w-md items-center justify-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <span>ID type: <strong class="text-gray-700 dark:text-gray-200" x-text="selectedTemplate ? selectedTemplate.name : ''"></strong></span>
                <span aria-hidden="true">·</span>
                <button type="button" @click="changeTemplate()" class="font-medium text-brand-500 transition hover:text-brand-600">Change ID type</button>
            </div>

            <div class="mx-auto mb-4 flex max-w-md items-center gap-2 rounded-xl border border-gray-200 bg-white/60 p-1.5 dark:border-gray-800 dark:bg-white/[0.02]">
                <template x-for="side in ['front', 'back']" :key="side">
                    <button type="button" @click="selectSide(side)"
                        class="flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition"
                        :class="scanSide === side ? 'bg-palette-lime text-gray-900' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                        <span x-text="side === 'front' ? 'Front' : 'Back'"></span>
                        <svg x-show="previewFor(side)" x-cloak class="h-4 w-4 text-success-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                    </button>
                </template>
            </div>

            <div class="relative mx-auto aspect-video w-full max-w-md overflow-hidden rounded-xl bg-black">
                <video x-ref="video" playsinline muted class="h-full w-full object-cover"></video>
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <div x-ref="finder" class="rounded-xl border-2 border-palette-lime shadow-[0_0_0_9999px_rgba(0,0,0,0.45)]"
                        :style="'aspect-ratio: ' + overlayAspect + '; ' + (orientation === 'vertical' ? 'height: 90%;' : 'width: 82%;')"></div>
                </div>
                <template x-if="!cameraOn && !previewFor(scanSide)">
                    <div class="absolute inset-0 flex items-center justify-center text-xs text-white/60">Camera is off</div>
                </template>
            </div>
            <canvas x-ref="canvas" class="hidden"></canvas>

            <p x-show="cameraError" x-cloak x-text="cameraError" class="mt-2 text-center text-xs text-error-500"></p>

            <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                <button type="button" x-show="!cameraOn" @click="startCamera()"
                    class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">Start camera</button>
                <button type="button" x-show="cameraOn" @click="capture()" x-cloak
                    class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                    <span x-text="scanSide === 'front' ? 'Capture front' : 'Capture back'"></span>
                </button>
                <button type="button" x-show="cameraOn" @click="stopCamera()" x-cloak
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Stop</button>
                <button type="button" @click="document.getElementById(scanSide === 'back' ? 'id_photo_back' : 'id_photo_front').click()"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Upload instead</button>
            </div>

            <template x-if="frontPreview || backPreview">
                <div class="mt-5 space-y-4 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex flex-wrap items-start gap-4">
                        <template x-if="frontPreview">
                            <figure class="shrink-0">
                                <img :src="frontPreview" alt="Front of ID" class="h-24 w-40 rounded-lg object-cover" />
                                <figcaption class="mt-1 text-center text-[11px] text-gray-400">Front</figcaption>
                            </figure>
                        </template>
                        <template x-if="backPreview">
                            <figure class="shrink-0">
                                <img :src="backPreview" alt="Back of ID" class="h-24 w-40 rounded-lg object-cover" />
                                <figcaption class="mt-1 text-center text-[11px] text-gray-400">Back</figcaption>
                            </figure>
                        </template>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p x-show="scanNote" x-text="scanNote" class="text-sm font-medium text-brand-600 dark:text-brand-400"></p>
                        <template x-if="signatureNote">
                            <p class="mt-1 text-xs font-medium"
                                :class="{
                                    'text-success-600 dark:text-success-500': signatureNote.tone === 'success',
                                    'text-warning-600 dark:text-orange-400': signatureNote.tone === 'warning',
                                    'text-gray-400 dark:text-gray-500': signatureNote.tone === 'muted',
                                }"
                                x-text="signatureNote.text"></p>
                        </template>
                        <template x-if="detectedEntries.length">
                            <dl class="mt-2 grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-2">
                                <template x-for="d in detectedEntries" :key="d.key">
                                    <div class="flex justify-between gap-2 text-xs">
                                        <dt class="text-gray-400" x-text="d.key.replaceAll('_', ' ')"></dt>
                                        <dd class="truncate font-medium text-gray-700 dark:text-gray-200" x-text="d.value"></dd>
                                    </div>
                                </template>
                            </dl>
                        </template>
                    </div>
                </div>
            </template>

            <div class="mt-6 flex items-center justify-end gap-3">
                <span x-show="!canContinue" x-cloak class="text-xs text-gray-400">Capture both sides to continue.</span>
                <button type="button" @click="continueToForm()" :disabled="!canContinue"
                    class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50">
                    Done scanning ↓
                </button>
            </div>
        </div>
    </div>

    {{-- The field's own value: the (scanned or typed) student number. --}}
    <div>
        <input type="text" id="{{ $key }}" name="{{ $key }}" value="{{ $old }}" inputmode="numeric"
            placeholder="{{ $placeholder ?: 'Student ID number' }}" class="{{ $inputClass }}" autocomplete="off" />
        <p class="mt-1 text-xs text-gray-400">Scanning fills this automatically — you can correct it if needed.</p>
    </div>
</div>
