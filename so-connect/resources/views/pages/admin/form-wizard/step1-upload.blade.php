@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Create New Form" />

    <div class="space-y-6">

        <x-admin.wizard-progress :step="1" :total="5" :labels="[1 => 'Upload', 2 => 'Details', 3 => 'AI Review', 4 => 'Revise', 5 => 'Confirm']" />

        {{-- Header --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Step 1 — Upload Form Document</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Upload a DOCX file of the paper form. The AI will attempt to detect its fields automatically in the next steps.
                    </p>
                </div>
                <a href="{{ route('admin.templates.index') }}"
                   class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800">
                    Cancel
                </a>
            </div>
        </div>

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

        <form action="{{ route('admin.form-wizard.upload') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            {{-- File Upload --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">DOCX File</h3>

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
                <div class="flex justify-end">
                    <button type="submit"
                            class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Next: Form Details →
                    </button>
                </div>
            </div>
        </form>

    </div>
@endsection
