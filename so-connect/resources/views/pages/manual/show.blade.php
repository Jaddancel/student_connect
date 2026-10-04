@extends('layouts.app')

@php
    /** @var \App\Models\ManualFormSession $session */
    $status = $session->status;
    $downloadUrl = route('manual.download', $session) . ($token !== '' ? '?t=' . $token : '');
    $statusUrl = route('manual.status', $session) . ($token !== '' ? '?t=' . $token : '');
@endphp

@section('content')
    <x-common.page-breadcrumb :pageTitle="($form?->name ?? 'Manual filling')" />

    <p class="mx-auto mb-4 max-w-3xl text-xs text-gray-500 dark:text-gray-400">
        Form initiated on: {{ $session->created_at?->format('M j, Y') }} {{ $session->created_at?->format('g:i A') }}
    </p>

    @if ($documents->count() > 1)
        <div class="mx-auto max-w-3xl space-y-5">
            <p class="text-sm text-gray-600">Print each document. Upload a completed scan for every document with handwritten fields; documents without handwritten fields need no scan.</p>
            @foreach ($documents as $document)
                @php
                    $needsScan = ! empty($document->session_schema['extractable_fields']);
                    $query = $token !== '' ? '?t=' . urlencode($token) : '';
                @endphp
                <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]"
                    x-data="manualScanUploader({
                        status: @js($document->status),
                        error: @js($document->parse_error ?? ''),
                        warnings: @js($document->parse_warnings ?? []),
                        token: @js($token),
                        statusUrl: @js(route('manual.documents.status', [$session, $document]) . $query),
                        uploadUrl: @js(route('manual.documents.upload', [$session, $document])),
                        retryUrl: @js(route('manual.documents.retry', [$session, $document])),
                        csrf: @js(csrf_token())
                    })"
                    @if (! $document->partial_pdf_path)
                        x-effect="if (status === 'awaiting_scan' || status === 'review') window.location.reload()"
                    @endif>
                    <h3 class="font-semibold">Document {{ $loop->iteration }} — {{ $document->template?->template_name ?: 'Printed template' }}</h3>
                    <p class="mt-1 text-sm" x-text="status.replaceAll('_', ' ')"></p>
                    <p class="mt-1 text-sm text-red-600" x-show="error" x-text="error"></p>
                    @if ($document->partial_pdf_path)
                        <a class="mt-3 inline-block rounded-lg bg-brand-500 px-4 py-2 text-sm text-white"
                            href="{{ route('manual.documents.download', [$session, $document]) . $query }}" target="_blank" rel="noopener">Download PDF</a>
                    @endif
                    @if ($needsScan)
                        <div class="mt-4" x-show="status === 'awaiting_scan' || status === 'failed' || status === 'review'">
                            <p class="text-sm">Upload all pages of this document in order (PDF, JPG or PNG).</p>
                            <input class="mt-2 block text-sm" type="file" multiple accept=".pdf,.jpg,.jpeg,.png" @change="onPick($event)">
                            <p class="mt-1 text-xs" x-show="files.length" x-text="files.length + ' file(s) selected'"></p>
                            <button type="button" class="mt-2 rounded-lg bg-brand-500 px-4 py-2 text-sm text-white disabled:opacity-50"
                                :disabled="!files.length || uploading" @click="upload()">Upload &amp; read</button>
                            <button type="button" class="mt-2 rounded-lg border px-4 py-2 text-sm"
                                x-show="status === 'failed'" @click="retry()">Retry saved scan</button>
                        </div>
                    @else
                        <p class="mt-2 text-sm">No handwritten fields — no scan required.</p>
                    @endif
                </div>
            @endforeach
        </div>
    @else
    <div class="mx-auto max-w-3xl space-y-6"
        x-data="manualScanUploader({
            status: @js($status),
            error: @js($session->parse_error ?? ''),
            warnings: @js($session->parse_warnings ?? []),
            token: @js($token),
            statusUrl: @js($statusUrl),
            uploadUrl: @js(route('manual.upload', $session)),
            retryUrl: @js(route('manual.retry', $session)),
            csrf: @js(csrf_token()),
        })">

        {{-- ── Preparing ─────────────────────────────────────────────── --}}
        <template x-if="status === 'preparing'">
            <div class="rounded-2xl border border-gray-200 bg-white p-8 text-center dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="mx-auto mb-3 h-8 w-8 animate-spin rounded-full border-2 border-brand-500 border-t-transparent"></div>
                <p class="text-sm font-medium text-gray-700 dark:text-white/90">Preparing your printable form…</p>
                <p class="mt-1 text-xs text-gray-400">This only takes a moment.</p>
            </div>
            @endif
        </template>

        {{-- ── Awaiting scan (download + upload) ─────────────────────── --}}
        <template x-if="status === 'awaiting_scan' || status === 'failed'">
            <div class="space-y-6">
                <div x-show="error" x-cloak class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400" x-text="error"></div>

                <template x-if="warnings.length">
                    <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                        <template x-for="w in warnings" :key="w"><p x-text="w"></p></template>
                    </div>
                </template>

                <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">1. Print &amp; fill by hand</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Download the partially filled form, print it, and complete the remaining fields by hand.</p>
                    <a href="{{ $downloadUrl }}" target="_blank" rel="noopener"
                        class="mt-3 inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Download the form (PDF)
                    </a>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">2. Upload the completed scan</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Scan or photograph every page in order (PDF, JPG or PNG).</p>

                    <div class="mt-3 rounded-xl border-2 border-dashed p-6 text-center transition"
                        :class="dragging ? 'border-brand-400 bg-brand-50/50 dark:bg-brand-500/5' : 'border-gray-300 dark:border-gray-700'"
                        @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="onDrop($event)">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Drag &amp; drop pages here, or</p>
                        <button type="button" @click="$refs.picker.click()" class="mt-2 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Choose files</button>
                        <input type="file" x-ref="picker" class="hidden" multiple accept=".pdf,.jpg,.jpeg,.png" @change="onPick($event)" />
                    </div>

                    <template x-if="files.length">
                        <ul class="mt-3 space-y-1 text-sm">
                            <template x-for="(f, i) in files" :key="i">
                                <li class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-1.5 dark:bg-white/[0.03]">
                                    <span class="truncate text-gray-700 dark:text-gray-300" x-text="f.name"></span>
                                    <button type="button" @click="removeFile(i)" class="text-xs font-medium text-error-500 hover:text-error-600">Remove</button>
                                </li>
                            </template>
                        </ul>
                    </template>

                    <div class="mt-4 flex items-center gap-3">
                        <button type="button" @click="upload()" :disabled="!files.length || uploading"
                            class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:opacity-50">
                            <span x-show="!uploading">Upload &amp; read</span>
                            <span x-show="uploading" x-cloak>Uploading…</span>
                        </button>
                        <button type="button" x-show="status === 'failed'" @click="retry()" x-cloak
                            class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Retry reading the last scan</button>
                    </div>
                </div>
            </div>
        </template>

        {{-- ── Parsing ───────────────────────────────────────────────── --}}
        <template x-if="status === 'parsing'">
            <div class="rounded-2xl border border-gray-200 bg-white p-8 text-center dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="mx-auto mb-3 h-8 w-8 animate-spin rounded-full border-2 border-brand-500 border-t-transparent"></div>
                <p class="text-sm font-medium text-gray-700 dark:text-white/90">Reading your handwriting…</p>
                <p class="mt-1 text-xs text-gray-400">You can leave this page — your draft is saved.</p>
            </div>
        </template>
    </div>

    {{-- ── Review (parsed values, editable, real submit) ─────────────── --}}
    @if ($status === \App\Models\ManualFormSession::STATUS_REVIEW && $review)
        <div class="mx-auto mt-6 max-w-3xl space-y-4">
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                We read your handwriting and filled it in below. Please check every value before submitting.
            </div>

            @if (! empty($review['lowConfidence']) || ! empty($review['unresolved']))
                <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                    @if (! empty($review['lowConfidence']))
                        <p><strong>Please double-check:</strong> {{ implode(', ', $review['lowConfidence']) }} — we were not fully confident.</p>
                    @endif
                    @if (! empty($review['unresolved']))
                        <p><strong>Could not read:</strong> {{ implode(', ', $review['unresolved']) }} — fill these in yourself.</p>
                    @endif
                </div>
            @endif

            @if (! empty($session->parse_warnings))
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-400">
                    @foreach ($session->parse_warnings as $w)<p>{{ $w }}</p>@endforeach
                </div>
            @endif

            @include('components.form.builder-form', $review)
        </div>
    @endif

    @if (in_array($status, [\App\Models\ManualFormSession::STATUS_SUBMITTED, \App\Models\ManualFormSession::STATUS_EXPIRED], true))
        <div class="mx-auto max-w-3xl rounded-2xl border border-gray-200 bg-white p-8 text-center dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-sm font-medium text-gray-700 dark:text-white/90">
                {{ $status === \App\Models\ManualFormSession::STATUS_SUBMITTED ? 'This draft was already submitted.' : 'This draft has expired.' }}
            </p>
            <a href="{{ route('forms.directory') }}" class="mt-3 inline-block text-sm font-medium text-brand-500 hover:underline">Back to forms</a>
        </div>
    @endif
@endsection
