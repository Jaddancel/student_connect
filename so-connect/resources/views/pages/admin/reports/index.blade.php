@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Reports" />

    <div class="space-y-6"
        x-data="{
            busy: false,
            label: '',
            error: '',
            // Generation can take a while (document conversion), so run it
            // through fetch: the modal stays up until the file arrives, then the
            // blob is saved without leaving the page.
            async generate(event) {
                const form = event.target;
                const format = event.submitter?.value || 'pdf';
                const url = new URL(form.action, window.location.origin);
                for (const [key, value] of new FormData(form)) {
                    if (value !== '') url.searchParams.append(key, value);
                }
                url.searchParams.set('format', format);

                this.error = '';
                this.label = form.dataset.report + ' (' + (format === 'docx' ? 'Word' : 'PDF') + ')';
                this.busy = true;
                try {
                    const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    if (!response.ok) {
                        const json = await response.json().catch(() => ({}));
                        const first = json.errors ? Object.values(json.errors).flat()[0] : null;
                        throw new Error(first || json.message || 'The report could not be generated.');
                    }
                    const disposition = response.headers.get('Content-Disposition') || '';
                    const name = (disposition.match(/filename=&quot;?([^&quot;;]+)/) || [])[1] || 'report.' + format;
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(await response.blob());
                    link.download = name;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    URL.revokeObjectURL(link.href);
                    this.busy = false;
                } catch (e) {
                    this.error = e.message || 'The report could not be generated.';
                }
            },
        }">

        {{-- Generation progress / error modal --}}
        <div x-show="busy" x-cloak x-transition.opacity
            class="fixed inset-0 z-[100000] flex items-center justify-center bg-gray-900/50 p-4 backdrop-blur-sm"
            role="dialog" aria-modal="true" aria-live="polite" @keydown.escape.window="if (error) busy = false">
            <div class="w-full max-w-sm rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-xl dark:border-gray-800 dark:bg-gray-900">
                <template x-if="!error">
                    <div>
                        <div class="mx-auto h-14 w-14 animate-spin rounded-full border-4 border-brand-200 border-t-brand-500" aria-hidden="true"></div>
                        <h3 class="mt-5 text-base font-semibold text-gray-800 dark:text-white/90">Generating report…</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="label"></p>
                        <p class="mt-3 text-xs text-gray-400">This can take a few moments. Your download will start automatically.</p>
                    </div>
                </template>
                <template x-if="error">
                    <div>
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-error-50 text-error-600 dark:bg-error-500/10">
                            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                        </div>
                        <h3 class="mt-5 text-base font-semibold text-gray-800 dark:text-white/90">Report could not be generated</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400" x-text="error"></p>
                        <button type="button" @click="busy = false; error = ''"
                            class="mt-5 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">Close</button>
                    </div>
                </template>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-500 dark:text-gray-400">Reports are generated from the active report templates.</p>
            @if ((int) auth()->user()->user_type === 2)
                <a href="{{ route('admin.report-templates.index') }}" class="text-sm font-medium text-brand-500 hover:text-brand-600">Manage report templates →</a>
            @endif
        </div>

        @if ($reports->isEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white px-6 py-16 text-center text-sm text-gray-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-400">
                No reports are available to you yet.
            </div>
        @else
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                @foreach ($reports as $entry)
                    @php $report = $entry['model']; @endphp
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 [&_svg]:h-5 [&_svg]:w-5">
                                {!! \App\Helpers\MenuHelper::getIconSvg($report->icon ?: 'pages') !!}
                            </span>
                            <div class="min-w-0">
                                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $report->name }}</h3>
                                @if ($report->description)
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $report->description }}</p>
                                @endif
                            </div>
                        </div>

                        @if (! $entry['valid'])
                            <p class="mt-4 rounded-lg bg-error-50 px-3 py-2 text-xs font-medium text-error-600 dark:bg-error-500/10 dark:text-error-400">
                                This template refers to data that no longer exists. Edit it to fix its tokens.
                            </p>
                        @else
                            <form method="GET" action="{{ route('reports.generate', $report) }}" data-report="{{ $report->name }}"
                                @submit.prevent="generate($event)" class="mt-5 space-y-3">
                                @if ($entry['notice'])
                                    <p class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:bg-white/[0.03] dark:text-gray-400">{{ $entry['notice'] }}</p>
                                @endif
                                @foreach ($entry['parameters'] as $parameter)
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                            {{ $parameter['label'] }}@if ($parameter['required']) <span class="text-error-500">*</span>@endif
                                        </label>
                                        @if ($parameter['type'] === 'entity')
                                            <select name="params[{{ $parameter['name'] }}]" @required($parameter['required'])
                                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                                                <option value="">{{ $parameter['required'] ? 'Choose…' : 'All' }}</option>
                                                @foreach ($parameter['options'] ?? [] as $option)
                                                    <option value="{{ $option['value'] }}" @selected($option['value'] === $parameter['selected'])>{{ $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input name="params[{{ $parameter['name'] }}]" @required($parameter['required'])
                                                type="{{ $parameter['type'] === 'number' ? 'number' : ($parameter['type'] === 'date' ? 'date' : 'text') }}"
                                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90" />
                                        @endif
                                    </div>
                                @endforeach

                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" name="format" value="pdf"
                                        class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                                        Download PDF
                                    </button>
                                    <button type="submit" name="format" value="docx"
                                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                                        Download Word
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
