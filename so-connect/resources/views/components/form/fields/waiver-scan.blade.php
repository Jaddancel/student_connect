@php
    /**
     * WAIVER_SCAN field: capture/upload a signed waiver photo. The image is kept
     * as the field value (hidden data-URL input) for admin review; when a waiver
     * template is bound it is also validated live via /waiver-scan.
     *
     * Expects: $field, $key, $old, $inputClass, $special (['waiver' => [...]]).
     */
    $opts = (array) ($field->field_options ?? []);
    $waiverTemplate = \App\Models\IdTemplate::query()
        ->where('kind', 'waiver')
        ->when($opts['waiver_template_id'] ?? null, fn ($q, $id) => $q->where('id_template_id', $id))
        ->orderByDesc('is_default')->orderByDesc('id_template_id')
        ->first();
    $templatePayload = $waiverTemplate ? [
        'reference' => ['width' => (int) $waiverTemplate->image_width, 'height' => (int) $waiverTemplate->image_height],
        'zones' => array_values((array) $waiverTemplate->zones),
    ] : null;
    $expected = (array) ($special['waiver']['expected'] ?? []);
@endphp

<div x-data="waiverScanField({
        scanUrl: '{{ route('waiver.scan') }}',
        csrf: '{{ csrf_token() }}',
        template: {{ Illuminate\Support\Js::from($templatePayload) }},
        expected: {{ Illuminate\Support\Js::from($expected) }},
    })" class="space-y-4">

    {{-- The field value: the captured/uploaded waiver image (data URL). --}}
    <input type="hidden" id="{{ $key }}" name="{{ $key }}" x-model="imageData" value="{{ $old }}" />
    <input type="file" x-ref="upload" accept=".jpg,.jpeg,.png,image/jpeg,image/png" class="hidden" @change="onUpload($event)" />

    <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="mb-1 text-base font-semibold text-gray-800 dark:text-white/90">Scan the signed waiver</h3>
        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
            Fit the whole waiver in the frame and capture — we'll check the details and the seal.
            Prefer not to use the camera? Upload a photo instead.
        </p>

        {{-- Capture stage --}}
        <template x-if="!imageData">
            <div>
                <div class="relative mx-auto aspect-video w-full max-w-md overflow-hidden rounded-xl bg-black">
                    <video x-ref="video" playsinline muted class="h-full w-full object-cover"></video>
                    <template x-if="!cameraOn">
                        <div class="absolute inset-0 flex items-center justify-center text-xs text-white/60">Camera is off</div>
                    </template>
                </div>
                <canvas x-ref="canvas" class="hidden"></canvas>
                <p x-show="cameraError" x-cloak x-text="cameraError" class="mt-2 text-center text-xs text-error-500"></p>

                <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                    <button type="button" x-show="!cameraOn" @click="startCamera()"
                        class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">Start camera</button>
                    <button type="button" x-show="cameraOn" x-cloak @click="captureAndScan()"
                        class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">Capture</button>
                    <button type="button" x-show="cameraOn" x-cloak @click="stopCamera()"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Stop</button>
                    <button type="button" @click="$refs.upload.click()"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Upload instead</button>
                </div>
            </div>
        </template>

        {{-- Review stage (after capture) --}}
        <template x-if="imageData">
            <div class="space-y-4">
                <div class="flex flex-wrap items-start gap-4">
                    <img :src="imageData" alt="Scanned waiver" class="h-32 w-52 rounded-lg border border-gray-200 object-cover dark:border-gray-700" />
                    <div class="min-w-0 flex-1 space-y-2">
                        <p x-show="checking" class="text-sm text-gray-500">Checking the waiver…</p>
                        <p x-show="error" x-cloak x-text="error" class="text-sm text-warning-600 dark:text-orange-400"></p>

                        <template x-if="result && result.ok">
                            <div>
                                <p class="text-sm font-semibold"
                                    :class="valid ? 'text-success-600 dark:text-success-500' : 'text-warning-600 dark:text-orange-400'"
                                    x-text="valid ? 'Waiver looks valid.' : 'Some details need review.'"></p>
                                <div class="mt-1 flex flex-wrap gap-3 text-xs text-gray-500 dark:text-gray-400">
                                    <span :class="result.stamp ? 'text-success-600' : 'text-error-500'"
                                        x-text="(result.stamp ? '✓' : '✕') + ' seal detected'"></span>
                                    <span :class="result.signature ? 'text-success-600' : 'text-error-500'"
                                        x-text="(result.signature ? '✓' : '✕') + ' signature detected'"></span>
                                </div>
                                <dl class="mt-2 grid grid-cols-1 gap-y-1 sm:grid-cols-2 sm:gap-x-4">
                                    <template x-for="f in resultFields" :key="f.key">
                                        <div class="flex justify-between gap-2 text-xs">
                                            <dt class="text-gray-400" x-text="f.key.replaceAll('_', ' ')"></dt>
                                            <dd class="font-medium" :class="f.match ? 'text-success-600' : 'text-error-500'"
                                                x-text="(f.match ? '✓ ' : '✕ ') + f.extracted"></dd>
                                        </div>
                                    </template>
                                </dl>
                            </div>
                        </template>
                    </div>
                </div>
                <button type="button" @click="rescan()"
                    class="text-xs font-semibold text-brand-500 transition hover:text-brand-600">Rescan</button>
            </div>
        </template>
    </div>
</div>
