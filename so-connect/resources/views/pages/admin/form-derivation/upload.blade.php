@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Derive Form from DOCX" />

    <div class="space-y-6">

        {{-- Header --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Derive Form from DOCX</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Upload a <span class="font-medium text-gray-700 dark:text-gray-200">blank</span> DOCX form.
                        The system auto-detects its fields so you can review and confirm them — no manual entry.
                    </p>
                </div>
                <a href="{{ route('admin.templates.index') }}"
                   class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800">
                    Back
                </a>
            </div>
        </div>

        {{-- Flash --}}
        @if(session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        {{-- Errors --}}
        @if($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                <ul class="list-disc pl-4 space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('admin.form-derivation.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            {{-- Form name --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Form Details</h3>
                <div>
                    <label for="form_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Form Name <span class="text-error-500">*</span>
                    </label>
                    <input type="text" id="form_name" name="form_name"
                           value="{{ old('form_name') }}"
                           placeholder="e.g. Organization Membership Form"
                           class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90 @error('form_name') border-error-400 @enderror" />
                    @error('form_name')
                        <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- File Upload (drag-drop pattern from templates/upload) --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Blank DOCX File</h3>

                <div x-data="{
                    isDragging: false,
                    fileName: null,
                    handleDrop(e) {
                        this.isDragging = false;
                        const file = e.dataTransfer.files[0];
                        if (file && file.name.toLowerCase().endsWith('.docx')) {
                            this.fileName = file.name;
                            const dt = new DataTransfer();
                            dt.items.add(file);
                            this.$refs.fileInput.files = dt.files;
                        }
                    },
                    handleChange(e) {
                        const file = e.target.files[0];
                        this.fileName = file ? file.name : null;
                    }
                }">
                    <div @drop.prevent="handleDrop($event)"
                         @dragover.prevent="isDragging = true"
                         @dragleave.prevent="isDragging = false"
                         @click="$refs.fileInput.click()"
                         :class="isDragging ? 'border-brand-400 bg-brand-50 dark:border-brand-600 dark:bg-brand-900/10' : 'border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/30'"
                         class="cursor-pointer rounded-xl border-2 border-dashed p-10 text-center transition-colors">

                        <input x-ref="fileInput" type="file" name="docx_file" accept=".docx"
                               @change="handleChange($event)" class="hidden" />

                        <template x-if="!fileName">
                            <div>
                                <svg class="mx-auto mb-3 h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Drag &amp; drop a DOCX file here, or click to browse</p>
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">.docx only — max 10 MB</p>
                            </div>
                        </template>

                        <template x-if="fileName">
                            <div>
                                <svg class="mx-auto mb-2 h-8 w-8 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300" x-text="fileName"></p>
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Click to change file</p>
                            </div>
                        </template>
                    </div>

                    @error('docx_file')
                        <p class="mt-2 text-xs text-error-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Actions --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.templates.index') }}"
                       class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        Cancel
                    </a>
                    <button type="submit"
                            class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Upload &amp; Detect Fields
                    </button>
                </div>
            </div>

        </form>
    </div>
@endsection
