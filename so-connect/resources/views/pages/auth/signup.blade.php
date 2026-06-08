@extends('layouts.directory-layout')

@section('content')
<style>
@keyframes scan-sweep {
    0%   { top: 0%; opacity: 1; }
    45%  { opacity: 1; }
    50%  { top: 100%; opacity: 0; }
    50.001% { top: 0%; opacity: 0; }
    55%  { opacity: 1; }
    100% { top: 100%; opacity: 1; }
}
.scan-sweep {
    position: absolute;
    left: 0; right: 0;
    height: 2px;
    animation: scan-sweep 2.4s linear infinite;
    background: linear-gradient(90deg, transparent, rgba(165,255,91,0.9), transparent);
    box-shadow: 0 0 18px 5px rgba(165,255,91,0.45);
    pointer-events: none;
    z-index: 20;
}
@keyframes corner-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}
.corner-pulse { animation: corner-pulse 1.6s ease-in-out infinite; }
</style>

<div
    x-data="ocrFormApp({{ Js::from(!empty($ocrForm)) }}, {{ Js::from(!empty($ocrForm) ? route('api.forms.scan', $ocrForm) : '') }}, {{ Js::from($errors->any()) }})"
    @ocr-result.window="applyOcrResult($event.detail.fieldMap)"
    class="space-y-5"
>

    {{-- ══ STEP PROGRESS BAR ══════════════════════════════════════════════ --}}
    @if(!empty($ocrForm))
    <div class="flex items-center gap-2 px-1">
        {{-- Step 1 --}}
        <button type="button" @click="step = 1"
            class="flex shrink-0 items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold transition-all"
            :class="{
                'bg-palette-lime text-gray-900': step === 1,
                'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400': step > 1,
                'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500': step < 1
            }">
            <span x-show="step <= 1"
                class="flex h-5 w-5 items-center justify-center rounded-full bg-black/10 text-[10px] font-bold dark:bg-white/10"
                :class="step === 1 ? 'bg-black/15' : ''">1</span>
            <svg x-show="step > 1" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
            Scan
        </button>

        <div class="relative h-px flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
            <div class="absolute inset-y-0 left-0 rounded-full bg-success-400 transition-all duration-500"
                :style="step > 1 ? 'width:100%' : 'width:0%'"></div>
        </div>

        {{-- Step 2 --}}
        <div class="flex shrink-0 items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold transition-all"
            :class="{
                'bg-palette-lime text-gray-900': step === 2,
                'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500': step < 2
            }">
            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-black/10 text-[10px] font-bold dark:bg-white/10">2</span>
            Fill Details
        </div>
    </div>
    @endif

    {{-- ══ STEP 1 · OCR SCANNER ════════════════════════════════════════════ --}}
    @if(!empty($ocrForm))
    <div x-show="step === 1" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-4">

        {{-- Hero header --}}
        <div class="text-center">
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white">Scan Your Form</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Take a photo of your filled paper form — we'll auto-fill the digital fields for you.
            </p>
        </div>

        {{-- Scanner card --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900/60">

            {{-- Viewfinder --}}
            <div
                class="relative w-full cursor-pointer overflow-hidden bg-gray-950 transition-shadow"
                style="aspect-ratio: 4 / 3; max-height: 360px;"
                @drop.prevent="handleDrop($event)"
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @click="if (status === 'idle' && !cameraActive) $refs.fileInput.click()"
                :class="isDragging ? 'ring-2 ring-inset ring-palette-lime' : ''"
            >
                {{-- Corner brackets --}}
                <div class="pointer-events-none absolute inset-0 z-10 p-4" :class="status === 'processing' ? 'corner-pulse' : ''">
                    <div class="absolute left-4 top-4 h-9 w-9 rounded-tl-sm border-l-[3px] border-t-[3px] border-palette-lime"></div>
                    <div class="absolute right-4 top-4 h-9 w-9 rounded-tr-sm border-r-[3px] border-t-[3px] border-palette-lime"></div>
                    <div class="absolute bottom-4 left-4 h-9 w-9 rounded-bl-sm border-b-[3px] border-l-[3px] border-palette-lime"></div>
                    <div class="absolute bottom-4 right-4 h-9 w-9 rounded-br-sm border-b-[3px] border-r-[3px] border-palette-lime"></div>
                </div>

                {{-- Idle: no file --}}
                <template x-if="!previewUrl && status === 'idle'">
                    <div class="flex h-full flex-col items-center justify-center gap-3 px-8 text-center select-none">
                        <svg class="h-14 w-14 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-gray-400">Position your form here</p>
                            <p class="mt-0.5 text-xs text-gray-600">or tap to choose a file</p>
                        </div>
                    </div>
                </template>

                {{-- Image preview --}}
                <template x-if="previewUrl">
                    <img :src="previewUrl" class="absolute inset-0 h-full w-full object-contain" alt="Form preview" />
                </template>

                {{-- Scan sweep line --}}
                <div x-show="status === 'processing'" class="scan-sweep"></div>

                {{-- Uploading overlay --}}
                <div x-show="status === 'uploading'" class="absolute inset-0 z-30 flex flex-col items-center justify-center gap-3 bg-gray-950/75">
                    <svg class="h-9 w-9 animate-spin text-palette-lime" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    <span class="text-sm font-semibold text-white">Uploading…</span>
                </div>

                {{-- Done overlay --}}
                <div x-show="status === 'done'" x-transition class="absolute inset-0 z-30 flex flex-col items-center justify-center gap-3 bg-gray-950/60">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-success-500 shadow-lg shadow-success-500/40">
                        <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <span class="text-sm font-bold text-white">Scan complete!</span>
                </div>

                {{-- Error overlay --}}
                <div x-show="status === 'error'" x-transition class="absolute inset-0 z-30 flex flex-col items-center justify-center gap-3 bg-gray-950/70 px-8 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-error-500 shadow-lg shadow-error-500/40">
                        <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                    <span class="text-sm font-semibold text-white" x-text="errorMsg"></span>
                </div>

                {{-- Live camera feed --}}
                <template x-if="cameraActive">
                    <div class="absolute inset-0 z-20 flex flex-col bg-black">
                        <video x-ref="cameraVideo" autoplay playsinline muted class="h-full w-full object-cover"></video>
                        <canvas x-ref="cameraCanvas" class="hidden"></canvas>
                        <div class="absolute inset-x-0 bottom-4 flex justify-center gap-3">
                            <button type="button" @click="closeCamera()"
                                    class="rounded-full bg-white/20 px-5 py-2.5 text-sm font-semibold text-white backdrop-blur hover:bg-white/30">
                                Cancel
                            </button>
                            <button type="button" @click="capture()"
                                    class="rounded-full bg-palette-lime px-6 py-2.5 text-sm font-bold text-gray-900 shadow hover:brightness-95">
                                Capture
                            </button>
                        </div>
                    </div>
                </template>

                {{-- Hidden file input for drag/click --}}
                <input x-ref="fileInput" type="file" accept="image/jpeg,image/png,image/jpg,image/webp"
                    class="hidden" @change="handleChange($event)" />
            </div>

            {{-- Card body --}}
            <div class="p-5 space-y-4">

                {{-- Status message --}}
                <div class="text-center text-sm">
                    <p x-show="status === 'idle' && !file" class="text-gray-400 dark:text-gray-500 text-xs">
                        JPEG, PNG, WebP &middot; Max 10 MB
                    </p>
                    <p x-show="status === 'idle' && file" class="font-medium text-gray-700 dark:text-gray-300 truncate" x-text="fileName"></p>
                    <p x-show="status === 'processing'" class="animate-pulse font-medium text-brand-500">
                        Extracting text&hellip; this may take a few seconds
                    </p>
                    <p x-show="status === 'done'" class="font-semibold text-success-600 dark:text-success-400">
                        Fields extracted! Review the highlighted values in the form.
                    </p>
                </div>

                {{-- Camera error --}}
                <p x-show="cameraError" x-text="cameraError" class="text-center text-xs text-error-600 dark:text-error-400"></p>

                {{-- Capture / Upload buttons (idle) --}}
                <div x-show="status === 'idle'" class="grid grid-cols-2 gap-3">
                    {{-- Take Photo — opens live camera viewfinder --}}
                    <button type="button" @click="openCamera()"
                            class="flex select-none items-center justify-center gap-2 rounded-xl bg-gray-900 px-4 py-4 text-sm font-semibold text-white transition active:scale-[0.97] dark:bg-gray-700 hover:bg-gray-800 dark:hover:bg-gray-600">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Take Photo
                    </button>

                    {{-- Choose file --}}
                    <label class="flex cursor-pointer select-none items-center justify-center gap-2 rounded-xl border-2 border-gray-200 px-4 py-4 text-sm font-semibold text-gray-700 transition hover:border-brand-400 hover:text-brand-600 active:scale-[0.97] dark:border-gray-700 dark:text-gray-300 dark:hover:border-brand-600 dark:hover:text-brand-400">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <span>Choose File</span>
                        <input type="file" accept="image/jpeg,image/png,image/jpg,image/webp" class="hidden" @change="handleChange($event)">
                    </label>
                </div>

                {{-- Primary action button --}}
                <div x-show="status === 'idle' && file">
                    <button type="button" @click="upload()"
                        class="w-full rounded-xl bg-palette-lime py-4 text-sm font-bold text-gray-900 transition hover:brightness-95 active:scale-[0.98] shadow-sm">
                        Scan &amp; Pre-fill Form
                    </button>
                </div>

                <div x-show="status === 'done'">
                    <button type="button" @click="step = 2"
                        class="w-full rounded-xl bg-palette-lime py-4 text-sm font-bold text-gray-900 transition hover:brightness-95 active:scale-[0.98] shadow-sm">
                        Continue to Form &rarr;
                    </button>
                </div>

                <div x-show="status === 'error'" class="flex gap-3">
                    <button type="button" @click="reset()"
                        class="flex-1 rounded-xl border-2 border-gray-200 py-4 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 active:scale-[0.98] dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        Try Again
                    </button>
                    <button type="button" @click="skipScan()"
                        class="flex-1 rounded-xl bg-palette-lime py-4 text-sm font-bold text-gray-900 transition hover:brightness-95 active:scale-[0.98]">
                        Fill Manually
                    </button>
                </div>
            </div>
        </div>

        {{-- Skip link --}}
        <p class="text-center">
            <button type="button" @click="skipScan()"
                class="text-sm text-gray-400 underline underline-offset-2 transition hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                Skip scanning — I'll fill the form manually
            </button>
        </p>

    </div>
    @endif

    {{-- ══ STEP 2 · FORM ═══════════════════════════════════════════════════ --}}
    <div x-show="{{ !empty($ocrForm) ? 'step === 2' : 'true' }}"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="space-y-5">

        {{-- OCR banner --}}
        @if(!empty($ocrForm))
        <div x-show="ocrUsed"
            class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/20 dark:bg-amber-500/5">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-xs leading-relaxed text-amber-700 dark:text-amber-400">
                Fields highlighted in <strong>yellow</strong> were pre-filled by OCR scan.
                Please verify each value before submitting — OCR is not perfect.
            </p>
        </div>
        @endif

        {{-- Form header --}}
        <div class="rounded-2xl border border-gray-200 bg-palette-lime-pale p-5 shadow-[inset_0_4px_0_var(--color-palette-lime)] dark:border-gray-800 dark:bg-white/[0.03] dark:shadow-[inset_0_4px_0_rgb(165_255_91_/_0.3)]">
            <div class="text-center">
                <h1 class="text-xl font-bold uppercase tracking-widest text-gray-900 dark:text-white">
                    Directory of Student Leader
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Complete all fields accurately. Fields marked <span class="text-error-500">*</span> are required.
                </p>
            </div>
        </div>

        @if($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <form action="{{ route('student-leader-directory.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
            @csrf

            {{-- ── SECTION 1 · PERIOD & IDENTITY ───────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Period &amp; Identity</h3>

                {{-- Student ID --}}
                <div class="mb-4">
                    <label for="student_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Student ID <span class="text-error-500">*</span>
                    </label>
                    <input type="text" id="student_id" name="student_id" inputmode="numeric" required
                        placeholder="e.g. 2021123456"
                        value="{{ old('student_id') }}"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('student_id') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    @error('student_id')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                </div>

                {{-- Semester / Season / School Year --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="semester" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Semester <span class="text-error-500">*</span>
                        </label>
                        <select id="semester" name="semester"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('semester') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                            <option value="">Select semester</option>
                            <option value="1st" @selected(old('semester', $currentSemester ?? '') === '1st')>1st Semester</option>
                            <option value="2nd" @selected(old('semester', $currentSemester ?? '') === '2nd')>2nd Semester</option>
                            <option value="summer" @selected(old('semester', $currentSemester ?? '') === 'summer')>Summer</option>
                        </select>
                        @error('semester')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="season" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Season <span class="text-error-500">*</span>
                        </label>
                        <select id="season" name="season"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('season') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                            <option value="">Select season</option>
                            <option value="summer" @selected(old('season') === 'summer')>Summer</option>
                            <option value="fall" @selected(old('season') === 'fall')>Fall</option>
                        </select>
                        @error('season')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="school_year" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            First School Year <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="school_year" name="school_year"
                            value="{{ old('school_year', $currentSchoolYear ?? '') }}"
                            placeholder="e.g. 2024–2025"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('school_year') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('school_year')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- First / Middle / Last Name --}}
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="first_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            First Name <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="first_name" name="first_name" placeholder="First name"
                            value="{{ old('first_name') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('first_name') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('first_name')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="middle_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Middle Name
                        </label>
                        <input type="text" id="middle_name" name="middle_name" placeholder="Middle name (optional)"
                            value="{{ old('middle_name') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    <div>
                        <label for="last_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Last Name <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="last_name" name="last_name" placeholder="Last name"
                            value="{{ old('last_name') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('last_name') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('last_name')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Email / Position / Contact --}}
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Email Address <span class="text-error-500">*</span>
                        </label>
                        <input type="email" id="email" name="email" placeholder="New officer's login email"
                            value="{{ old('email') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('email') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('email')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="position" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Position <span class="text-error-500">*</span>
                        </label>
                        <select id="position" name="position"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('position') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                            <option value="">Select position</option>
                            <option value="President"  @selected(old('position') === 'President')>President</option>
                            <option value="Treasurer"  @selected(old('position') === 'Treasurer')>Treasurer</option>
                            <option value="Auditor"    @selected(old('position') === 'Auditor')>Auditor</option>
                            <option value="Secretary"  @selected(old('position') === 'Secretary')>Secretary</option>
                            <option value="Others"     @selected(old('position') === 'Others')>Others</option>
                        </select>
                        @error('position')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="contact_number" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Contact Number <span class="text-error-500">*</span>
                        </label>
                        <input type="tel" id="contact_number" name="contact_number" placeholder="e.g. 09XX-XXX-XXXX"
                            value="{{ old('contact_number') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('contact_number') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('contact_number')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Photo --}}
                <div class="mt-4" x-data="{ preview: null }">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Photo <span class="text-error-500">*</span>
                        <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">(2×2 ID photo, JPG/PNG)</span>
                    </label>
                    <div class="flex items-start gap-4">
                        <div class="flex h-28 w-24 shrink-0 items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/30">
                            <template x-if="preview">
                                <img :src="preview" class="h-full w-full object-cover" alt="Photo preview" />
                            </template>
                            <template x-if="!preview">
                                <span class="px-2 text-center text-xs text-gray-400 dark:text-gray-500">No photo</span>
                            </template>
                        </div>
                        <div class="flex-1">
                            <label for="photo"
                                class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed {{ $errors->has('photo') ? 'border-error-500 bg-error-50 dark:border-error-500/40 dark:bg-error-500/5' : 'border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/30' }} px-4 py-5 transition hover:border-brand-400 hover:bg-brand-50 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                                <svg class="mb-2 h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 16v-8m-4 4h8M20.25 6.375c0 .621-.504 1.125-1.125 1.125H4.875A1.125 1.125 0 013.75 6.375V5.625A1.125 1.125 0 014.875 4.5h14.25A1.125 1.125 0 0120.25 5.625v.75zM4.5 7.5h15V18a1.5 1.5 0 01-1.5 1.5h-12A1.5 1.5 0 014.5 18V7.5z" />
                                </svg>
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Click to upload photo</span>
                                <span class="mt-1 text-xs text-gray-400 dark:text-gray-500">JPG, PNG — max 2 MB</span>
                                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png"
                                    class="hidden"
                                    @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                            </label>
                            @error('photo')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                {{-- ID Photos --}}
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div x-data="{ preview: null }">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Front of ID <span class="text-error-500">*</span>
                            <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">(JPG/PNG)</span>
                        </label>
                        <div class="flex items-start gap-3">
                            <div class="flex h-20 w-28 shrink-0 items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/30">
                                <template x-if="preview">
                                    <img :src="preview" class="h-full w-full object-cover" alt="Front of ID preview" />
                                </template>
                                <template x-if="!preview">
                                    <span class="px-2 text-center text-xs text-gray-400 dark:text-gray-500">No image</span>
                                </template>
                            </div>
                            <div class="flex-1">
                                <label for="id_photo_front"
                                    class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed {{ $errors->has('id_photo_front') ? 'border-error-500 bg-error-50 dark:border-error-500/40 dark:bg-error-500/5' : 'border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/30' }} px-4 py-4 transition hover:border-brand-400 hover:bg-brand-50 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                                    <svg class="mb-1 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 16v-8m-4 4h8M20.25 6.375c0 .621-.504 1.125-1.125 1.125H4.875A1.125 1.125 0 013.75 6.375V5.625A1.125 1.125 0 014.875 4.5h14.25A1.125 1.125 0 0120.25 5.625v.75zM4.5 7.5h15V18a1.5 1.5 0 01-1.5 1.5h-12A1.5 1.5 0 014.5 18V7.5z" /></svg>
                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Click to upload</span>
                                    <span class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">JPG, PNG — max 2 MB</span>
                                    <input id="id_photo_front" name="id_photo_front" type="file" accept="image/jpeg,image/png" class="hidden"
                                        @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                                </label>
                                @error('id_photo_front')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <div x-data="{ preview: null }">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Back of ID <span class="text-error-500">*</span>
                            <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">(JPG/PNG)</span>
                        </label>
                        <div class="flex items-start gap-3">
                            <div class="flex h-20 w-28 shrink-0 items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/30">
                                <template x-if="preview">
                                    <img :src="preview" class="h-full w-full object-cover" alt="Back of ID preview" />
                                </template>
                                <template x-if="!preview">
                                    <span class="px-2 text-center text-xs text-gray-400 dark:text-gray-500">No image</span>
                                </template>
                            </div>
                            <div class="flex-1">
                                <label for="id_photo_back"
                                    class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed {{ $errors->has('id_photo_back') ? 'border-error-500 bg-error-50 dark:border-error-500/40 dark:bg-error-500/5' : 'border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/30' }} px-4 py-4 transition hover:border-brand-400 hover:bg-brand-50 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                                    <svg class="mb-1 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 16v-8m-4 4h8M20.25 6.375c0 .621-.504 1.125-1.125 1.125H4.875A1.125 1.125 0 013.75 6.375V5.625A1.125 1.125 0 014.875 4.5h14.25A1.125 1.125 0 0120.25 5.625v.75zM4.5 7.5h15V18a1.5 1.5 0 01-1.5 1.5h-12A1.5 1.5 0 014.5 18V7.5z" /></svg>
                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Click to upload</span>
                                    <span class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">JPG, PNG — max 2 MB</span>
                                    <input id="id_photo_back" name="id_photo_back" type="file" accept="image/jpeg,image/png" class="hidden"
                                        @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                                </label>
                                @error('id_photo_back')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Organization / Faculty Advisers --}}
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2"
                    x-data="{
                        orgId: '{{ old('organization_id') }}',
                        get orgName() {
                            const opts = document.getElementById('organization_id')?.options ?? [];
                            for (const o of opts) { if (o.value == this.orgId) return o.text; }
                            return '';
                        }
                    }">
                    <div>
                        <label for="organization_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Name of Organization <span class="text-error-500">*</span>
                        </label>
                        <select id="organization_id" name="organization_id" x-model="orgId"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('organization_id') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                            <option value="">Select organization</option>
                            @foreach ($organizations as $org)
                                <option value="{{ $org->organization_id }}" {{ old('organization_id') == $org->organization_id ? 'selected' : '' }}>
                                    {{ $org->organization_name }}
                                </option>
                            @endforeach
                        </select>
                        <input type="hidden" name="organization_name" :value="orgName" />
                        @error('organization_id')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                    </div>

                    <div x-data="{
                        rows: {{ Js::from(array_values(array_filter((array) old('faculty_advisers', ['']), fn($v) => $v !== null))) }},
                        addRow() { this.rows.push('') },
                        removeRow(i) { if (this.rows.length > 1) this.rows.splice(i, 1) }
                    }">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Name of Faculty Advisers
                        </label>
                        <div class="space-y-2">
                            <template x-for="(row, i) in rows" :key="i">
                                <div class="flex items-center gap-2">
                                    <input type="text" name="faculty_advisers[]"
                                        :value="row"
                                        @input="rows[i] = $event.target.value"
                                        placeholder="Faculty adviser name"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                    <button type="button" @click="removeRow(i)"
                                        x-show="rows.length > 1"
                                        class="shrink-0 rounded-lg border border-gray-300 p-2.5 text-gray-400 transition hover:border-error-400 hover:text-error-500 dark:border-gray-700 dark:text-gray-500">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                        <button type="button" @click="addRow()"
                            class="mt-2 flex items-center gap-1.5 text-sm text-brand-500 transition hover:text-brand-600">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add adviser
                        </button>
                    </div>
                </div>
            </div>

            {{-- ── SECTION 2 · BASIC INFORMATION ───────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Basic Information</h3>

                <div class="space-y-4">

                    {{-- 1. Age / Sex / Religion --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label for="age" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                <span class="mr-1 font-semibold text-gray-500 dark:text-gray-500">1.</span>
                                Age <span class="text-error-500">*</span>
                            </label>
                            <input type="number" id="age" name="age" min="1" max="99"
                                value="{{ old('age') }}" placeholder="Age"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('age') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            @error('age')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="sex" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Sex <span class="text-error-500">*</span>
                            </label>
                            <select id="sex" name="sex"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('sex') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                                <option value="">Select</option>
                                <option value="Male"   @selected(old('sex') === 'Male')>Male</option>
                                <option value="Female" @selected(old('sex') === 'Female')>Female</option>
                            </select>
                            @error('sex')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="religious_affiliation" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Religious Affiliation
                            </label>
                            <input type="text" id="religious_affiliation" name="religious_affiliation"
                                value="{{ old('religious_affiliation') }}" placeholder="e.g. Roman Catholic"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        </div>
                    </div>

                    {{-- 2. Nationality --}}
                    <div>
                        <label for="nationality" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            <span class="mr-1 font-semibold text-gray-500 dark:text-gray-500">2.</span>
                            Nationality <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="nationality" name="nationality"
                            value="{{ old('nationality') }}" placeholder="e.g. Filipino"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('nationality') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('nationality')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- 3. Birthplace --}}
                    <div>
                        <label for="birthplace" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            <span class="mr-1 font-semibold text-gray-500 dark:text-gray-500">3.</span>
                            Birthplace
                        </label>
                        <input type="text" id="birthplace" name="birthplace"
                            value="{{ old('birthplace') }}" placeholder="City / Province"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    {{-- 4. Birthday --}}
                    <div>
                        <label for="birthday" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            <span class="mr-1 font-semibold text-gray-500 dark:text-gray-500">4.</span>
                            Birthday <span class="text-error-500">*</span>
                        </label>
                        <input type="date" id="birthday" name="birthday"
                            value="{{ old('birthday') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('birthday') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('birthday')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- 5. Present Address --}}
                    <div>
                        <label for="present_address" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            <span class="mr-1 font-semibold text-gray-500 dark:text-gray-500">5.</span>
                            Present Address <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="present_address" name="present_address"
                            value="{{ old('present_address') }}" placeholder="Street, Barangay, City / Municipality"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('present_address') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('present_address')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- 6. Home Address --}}
                    <div>
                        <label for="home_address" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            <span class="mr-1 font-semibold text-gray-500 dark:text-gray-500">6.</span>
                            Home Address
                        </label>
                        <input type="text" id="home_address" name="home_address"
                            value="{{ old('home_address') }}" placeholder="Permanent home address (if different)"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                    </div>

                    {{-- 7. Parents / Guardian --}}
                    <div>
                        <label for="parents_guardian" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            <span class="mr-1 font-semibold text-gray-500 dark:text-gray-500">7.</span>
                            Parents / Guardian <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="parents_guardian" name="parents_guardian"
                            value="{{ old('parents_guardian') }}" placeholder="Name of parent or guardian"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('parents_guardian') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                        @error('parents_guardian')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- 8. Course / Year Level --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="course" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                <span class="mr-1 font-semibold text-gray-500 dark:text-gray-500">8.</span>
                                Course <span class="text-error-500">*</span>
                            </label>
                            <input type="text" id="course" name="course"
                                value="{{ old('course') }}" placeholder="e.g. BS Computer Science"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('course') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            @error('course')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="year_level" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Year Level <span class="text-error-500">*</span>
                            </label>
                            <input type="text" id="year_level" name="year_level"
                                value="{{ old('year_level') }}" placeholder="e.g. 3rd Year"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('year_level') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            @error('year_level')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    {{-- 9. Talents and Hobbies --}}
                    <div>
                        <label for="talents_hobbies" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            <span class="mr-1 font-semibold text-gray-500 dark:text-gray-500">9.</span>
                            Talents and Hobbies
                        </label>
                        <textarea id="talents_hobbies" name="talents_hobbies" rows="3"
                            placeholder="List your talents and hobbies"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ old('talents_hobbies') }}</textarea>
                    </div>

                    {{-- 10. Source of Financial Support --}}
                    <div x-data="{
                        scholarship: {{ in_array('scholarship', (array) old('financial_support', [])) ? 'true' : 'false' }},
                        others: {{ in_array('others', (array) old('financial_support', [])) ? 'true' : 'false' }}
                    }">
                        <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-400">
                            <span class="mr-1 font-semibold text-gray-500 dark:text-gray-500">10.</span>
                            Source of Financial Support
                            <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">(Please check all that apply)</span>
                        </p>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/30 space-y-3">

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="financial_support[]" value="parents_guardians"
                                    {{ in_array('parents_guardians', (array) old('financial_support', [])) ? 'checked' : '' }}
                                    class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Parents / Guardians</span>
                            </label>

                            <div class="space-y-2">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="financial_support[]" value="scholarship"
                                        x-model="scholarship"
                                        class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Scholarship</span>
                                </label>
                                <div x-show="scholarship" x-transition class="pl-7">
                                    <input type="text" name="scholar_provider"
                                        value="{{ old('scholar_provider') }}" placeholder="Specify scholarship provider"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                </div>
                            </div>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="financial_support[]" value="assistantship"
                                    {{ in_array('assistantship', (array) old('financial_support', [])) ? 'checked' : '' }}
                                    class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Assistantship</span>
                            </label>

                            <div class="space-y-2">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="financial_support[]" value="others" x-model="others"
                                        class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Others</span>
                                </label>
                                <div x-show="others" x-transition class="pl-7">
                                    <input type="text" name="others_specify"
                                        value="{{ old('others_specify') }}" placeholder="Specify other source"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>

            {{-- ── ACCOUNT SETUP ────────────────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6"
                x-data="directoryPasswordTools()">
                <h3 class="mb-4 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">Account Setup</h3>

                <div class="space-y-4">
                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Password <span class="text-error-500">*</span>
                        </label>
                        <div class="relative">
                            <input :type="showPassword ? 'text' : 'password'" id="password" name="password"
                                x-model="password" placeholder="Create a secure password"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('password') ? 'border-error-500' : 'border-gray-300' }} bg-transparent py-2.5 pl-4 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            <button type="button" @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                                <svg x-show="!showPassword" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg x-show="showPassword"  width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        @error('password')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror

                        <div class="mt-2 space-y-1" x-show="password.length > 0">
                            <div class="flex items-center gap-2 text-xs" :class="minLength ? 'text-success-600 dark:text-success-400' : 'text-gray-400 dark:text-gray-500'">
                                <svg x-show="minLength"  width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                <svg x-show="!minLength" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="4"/></svg>
                                <span>At least 8 characters</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs" :class="hasUpper ? 'text-success-600 dark:text-success-400' : 'text-gray-400 dark:text-gray-500'">
                                <svg x-show="hasUpper"  width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                <svg x-show="!hasUpper" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="4"/></svg>
                                <span>One uppercase letter</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs" :class="hasLower ? 'text-success-600 dark:text-success-400' : 'text-gray-400 dark:text-gray-500'">
                                <svg x-show="hasLower"  width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                <svg x-show="!hasLower" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="4"/></svg>
                                <span>One lowercase letter</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs" :class="hasNumber ? 'text-success-600 dark:text-success-400' : 'text-gray-400 dark:text-gray-500'">
                                <svg x-show="hasNumber"  width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                <svg x-show="!hasNumber" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="4"/></svg>
                                <span>One number</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs" :class="hasSpecial ? 'text-success-600 dark:text-success-400' : 'text-gray-400 dark:text-gray-500'">
                                <svg x-show="hasSpecial"  width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                <svg x-show="!hasSpecial" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="4"/></svg>
                                <span>One special character (!@#$%...)</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Confirm Password <span class="text-error-500">*</span>
                        </label>
                        <div class="relative">
                            <input :type="showConfirm ? 'text' : 'password'" id="password_confirmation" name="password_confirmation"
                                x-model="confirmPassword" placeholder="Re-enter your password"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border {{ $errors->has('password_confirmation') ? 'border-error-500' : 'border-gray-300' }} bg-transparent py-2.5 pl-4 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            <button type="button" @click="showConfirm = !showConfirm"
                                class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                                <svg x-show="!showConfirm" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg x-show="showConfirm"  width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <p x-show="confirmPassword.length > 0 && !passwordsMatch" class="mt-1 text-xs text-error-500">Passwords do not match.</p>
                        <p x-show="confirmPassword.length > 0 && passwordsMatch"  class="mt-1 text-xs text-success-600 dark:text-success-400">Passwords match.</p>
                    </div>
                </div>
            </div>

            {{-- ── SUBMISSION ───────────────────────────────────────────── --}}
            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 sm:items-end">

                    <div>
                        <label for="date_filed" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Date Filed
                        </label>
                        <input type="date" id="date_filed" name="date_filed"
                            value="{{ old('date_filed', now()->toDateString()) }}"
                            readonly
                            class="h-11 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900/50 dark:text-white/60 cursor-not-allowed" />
                    </div>

                    <div x-data="{ preview: null }">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Signature <span class="text-error-500">*</span>
                            <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">(photo of signature, JPG/PNG)</span>
                        </label>
                        <label for="signature"
                            class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed {{ $errors->has('signature') ? 'border-error-500 bg-error-50 dark:border-error-500/40 dark:bg-error-500/5' : 'border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/30' }} px-4 py-4 transition hover:border-brand-400 hover:bg-brand-50 dark:hover:border-brand-600 dark:hover:bg-brand-900/10">
                            <template x-if="preview">
                                <img :src="preview" class="mb-2 max-h-16 object-contain" alt="Signature preview" />
                            </template>
                            <template x-if="!preview">
                                <svg class="mb-2 h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 3.487a2.25 2.25 0 113.182 3.182L8.5 18.213l-4.5 1 1-4.5L16.862 3.487z" />
                                </svg>
                            </template>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400"
                                x-text="preview ? 'Change signature' : 'Click to upload signature'"></span>
                            <span class="mt-1 text-xs text-gray-400 dark:text-gray-500">JPG, PNG — max 2 MB</span>
                            <input id="signature" name="signature" type="file" accept="image/jpeg,image/png"
                                class="hidden"
                                @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                        </label>
                        @error('signature')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                        <p class="mt-1 text-center text-xs text-gray-400 dark:text-gray-500">Signature over Printed Name</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="reset"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        Clear
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Submit
                    </button>
                </div>
            </div>

        </form>
    </div>

</div>
@endsection

@push('scripts')
<script>
window.ocrFormApp = function (hasOcr, scanUrl, hasErrors) {
    return {
        hasOcr,
        scanUrl,
        step: (hasOcr && !hasErrors) ? 1 : 2,

        // Scanner state
        file: null,
        previewUrl: null,
        fileName: null,
        status: 'idle',
        errorMsg: null,
        pollTimer: null,
        isDragging: false,
        ocrUsed: false,
        ocrFilled: {},

        // Camera state
        cameraActive: false,
        cameraStream: null,
        cameraError: null,

        handleDrop(e) {
            this.isDragging = false;
            const f = e.dataTransfer.files[0];
            if (f && /^image\/(jpeg|jpg|png|webp)$/.test(f.type)) this._setFile(f);
        },
        handleChange(e) {
            const f = e.target.files[0];
            if (f) this._setFile(f);
        },
        _setFile(f) {
            this.file = f;
            this.fileName = f.name;
            if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
            this.previewUrl = URL.createObjectURL(f);
            this.status = 'idle';
            this.errorMsg = null;
        },
        async upload() {
            if (!this.file || !this.scanUrl) return;
            this.status = 'uploading';
            this.errorMsg = null;

            const fd = new FormData();
            fd.append('scan', this.file);

            try {
                const res = await fetch(this.scanUrl, {
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

            } catch {
                this.errorMsg = 'Network error. Please check your connection and try again.';
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
                    this.errorMsg = 'OCR is taking too long. Please try again or fill the form manually.';
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
                        window.dispatchEvent(new CustomEvent('ocr-result', {
                            detail: { fieldMap: data.ocr_result || {} }
                        }));
                        setTimeout(() => { if (this.status === 'done') this.step = 2; }, 1800);
                    } else if (data.status === 'failed') {
                        clearInterval(this.pollTimer);
                        this.status = 'error';
                        this.errorMsg = 'OCR processing failed. Please try a clearer photo of the form.';
                    }
                } catch {
                    clearInterval(this.pollTimer);
                    this.status = 'error';
                    this.errorMsg = 'Network error while checking scan status. Please try again.';
                }
            }, 2000);
        },
        async openCamera() {
            this.cameraError = null;
            this.cameraActive = true;
            await this.$nextTick();
            try {
                this.cameraStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } },
                    audio: false,
                });
                this.$refs.cameraVideo.srcObject = this.cameraStream;
                await this.$refs.cameraVideo.play();
            } catch (err) {
                this.cameraError = err.name === 'NotAllowedError'
                    ? 'Camera permission denied. Allow camera access and try again.'
                    : 'Could not open camera. Try choosing a file instead.';
                this.closeCamera();
            }
        },
        closeCamera() {
            if (this.cameraStream) {
                this.cameraStream.getTracks().forEach(t => t.stop());
                this.cameraStream = null;
            }
            this.cameraActive = false;
        },
        capture() {
            const video = this.$refs.cameraVideo;
            const canvas = this.$refs.cameraCanvas;
            canvas.width  = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            canvas.toBlob(blob => {
                this._setFile(new File([blob], 'camera-capture.jpg', { type: 'image/jpeg' }));
                this.closeCamera();
            }, 'image/jpeg', 0.92);
        },
        reset() {
            clearInterval(this.pollTimer);
            this.closeCamera();
            if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
            this.file = null;
            this.previewUrl = null;
            this.fileName = null;
            this.status = 'idle';
            this.errorMsg = null;
            this.cameraError = null;
            if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        },
        skipScan() {
            this.step = 2;
        },
        applyOcrResult(fieldMap) {
            this.ocrUsed = true;
            for (const [key, value] of Object.entries(fieldMap)) {
                if (value === null || value === undefined || value === '') continue;
                const el = document.querySelector(`[name='${key}']`);
                if (!el) continue;
                el.value = value;
                el.classList.add('border-yellow-400', 'bg-yellow-50/40', 'dark:bg-yellow-500/5', 'dark:border-yellow-500/50');
                el.classList.remove('border-gray-300', 'dark:border-gray-700');
                el.dispatchEvent(new Event('input',  { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
                this.ocrFilled[key] = true;
            }
        },
    };
};

window.directoryPasswordTools = function () {
    return {
        password: '',
        confirmPassword: '',
        showPassword: false,
        showConfirm: false,
        get minLength()      { return this.password.length >= 8; },
        get hasUpper()       { return /[A-Z]/.test(this.password); },
        get hasLower()       { return /[a-z]/.test(this.password); },
        get hasNumber()      { return /[0-9]/.test(this.password); },
        get hasSpecial()     { return /[^A-Za-z0-9]/.test(this.password); },
        get allMet()         { return this.minLength && this.hasUpper && this.hasLower && this.hasNumber && this.hasSpecial; },
        get passwordsMatch() { return this.confirmPassword !== '' && this.password === this.confirmPassword; },
    };
};
</script>
@endpush
