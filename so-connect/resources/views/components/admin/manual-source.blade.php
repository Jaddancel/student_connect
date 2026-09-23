@props([
    // A submission payload that may carry a `_manual_source` block written when
    // the request was completed by hand and parsed (see the manual-filling flow).
    'payload' => [],
])

@php
    use App\Forms\FieldType;
    use App\Forms\SubmissionPresenter;

    $source = is_array($payload['_manual_source'] ?? null) ? $payload['_manual_source'] : null;
    $scans = $source ? SubmissionPresenter::attachments(['_scans' => $source['scan_paths'] ?? []], '_scans', FieldType::MULTI_IMAGE) : [];
    $warnings = (array) ($source['parse_warnings'] ?? []);
@endphp

@if ($source)
    <div class="rounded-2xl border border-brand-200 bg-brand-50/40 dark:border-brand-500/30 dark:bg-brand-500/[0.05]">
        <div class="flex items-center justify-between border-b border-brand-100 px-6 py-4 dark:border-brand-500/20">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-brand-700 dark:text-brand-300">Completed by hand</h3>
            @if (! empty($source['parser_model']))
                <span class="text-[11px] text-brand-500/80 dark:text-brand-300/70">read by {{ $source['parser_model'] }}</span>
            @endif
        </div>
        <div class="space-y-4 px-6 py-5">
            <p class="text-xs text-gray-500 dark:text-gray-400">
                The digital answers above were read from a handwritten scan the requester uploaded. The original scan is shown here for verification.
            </p>

            @if ($scans !== [])
                <div>
                    <p class="mb-1 text-xs font-medium text-gray-400">Uploaded scan</p>
                    <x-admin.attachments :items="$scans" label="Handwritten scan" />
                </div>
            @endif

            @if ($warnings !== [])
                <div class="rounded-lg border border-warning-200 bg-warning-50 px-3 py-2 text-xs text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                    @foreach ($warnings as $warning)<p>{{ $warning }}</p>@endforeach
                </div>
            @endif
        </div>
    </div>
@endif
