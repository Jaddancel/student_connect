@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Form OCR Settings" />

    <div class="space-y-6">

        {{-- Header --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">OCR Settings</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Configure OCR scanning options for <span class="font-medium text-gray-700 dark:text-gray-200">{{ $form->name }}</span>.
                    </p>
                </div>
                <a href="{{ route('admin.form-derivation.upload') }}"
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

        @if($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                <ul class="list-disc pl-4 space-y-0.5">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('admin.forms.settings.update', $form) }}" method="POST" class="space-y-6">
            @csrf
            @method('PATCH')

            {{-- Scan options --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Scan Options</h3>

                <div class="space-y-5">
                    {{-- allows_guest_scan --}}
                    <div class="flex items-start gap-4">
                        <div class="mt-0.5 flex h-5 items-center">
                            <input type="hidden" name="allows_guest_scan" value="0" />
                            <input type="checkbox" id="allows_guest_scan" name="allows_guest_scan" value="1"
                                   {{ $form->allows_guest_scan ? 'checked' : '' }}
                                   class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600" />
                        </div>
                        <div>
                            <label for="allows_guest_scan" class="cursor-pointer text-sm font-medium text-gray-800 dark:text-white/90">
                                Allow guest OCR scans
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                When enabled, unauthenticated users can upload a scanned form image to pre-fill the form fields.
                            </p>
                        </div>
                    </div>

                    {{-- directory_assignment_key --}}
                    <div>
                        <label for="directory_assignment_key" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Assign to sign-up route
                        </label>
                        <select id="directory_assignment_key" name="directory_assignment_key"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                            <option value="">— None —</option>
                            @foreach($directoryKeys as $key => $label)
                                <option value="{{ $key }}" {{ $form->directory_assignment_key === $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Links this form's OCR scanner to the selected public sign-up page.
                        </p>
                    </div>
                </div>
            </div>

            {{-- OCR Region calibration (advanced, collapsible) --}}
            @if($form->fields->isNotEmpty())
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6"
                     x-data="{ open: false }">
                    <button type="button" @click="open = !open"
                            class="flex w-full items-center justify-between text-left">
                        <div>
                            <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">OCR Region Calibration</h3>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Define the bounding box (% of image) where each field label appears on the scanned form.
                                Optional — improves accuracy over label-proximity matching.
                            </p>
                        </div>
                        <svg :class="open ? 'rotate-180' : ''" class="ml-4 h-5 w-5 shrink-0 text-gray-400 transition-transform dark:text-gray-500"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="open" x-transition class="mt-4 space-y-4">
                        @foreach($form->fields as $field)
                            <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/30">
                                <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ $field->field_label }}
                                    <span class="ml-1 font-mono text-xs text-gray-400 dark:text-gray-500">{{ $field->field_key }}</span>
                                </p>
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                                    @foreach(['x_pct' => 'X %', 'y_pct' => 'Y %', 'width_pct' => 'W %', 'height_pct' => 'H %', 'page' => 'Page'] as $sub => $lbl)
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ $lbl }}</label>
                                            <input type="number" step="{{ $sub === 'page' ? '1' : '0.001' }}"
                                                   min="0" max="{{ $sub === 'page' ? '99' : '1' }}"
                                                   name="ocr_region[{{ $field->id }}][{{ $sub }}]"
                                                   value="{{ $field->ocr_region[$sub] ?? '' }}"
                                                   placeholder="{{ $sub === 'page' ? '1' : '0.0' }}"
                                                   class="dark:bg-dark-900 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Actions --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.form-derivation.upload') }}"
                       class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        Cancel
                    </a>
                    <button type="submit"
                            class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Save Settings
                    </button>
                </div>
            </div>
        </form>

    </div>
@endsection
