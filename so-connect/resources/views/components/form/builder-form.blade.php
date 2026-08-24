{{--
    Renders a builder form's letterhead + field <form>. Shared by the standalone
    /forms/{route} page (pages/form/render.blade.php) and the calendar drawer,
    which embeds the new_event form inline.

    Expected data: $form, $fields, $prefill, $conditions, $advisers, $special,
    $hidden. Optional: $preview (bool), $submitLabel (string).
--}}
@php
    use Illuminate\Support\Facades\Storage;

    $preview = $preview ?? false;
    $prefill = $prefill ?? [];
    $hidden = $hidden ?? [];
    $advisers = $advisers ?? [];
    $special = $special ?? [];
    $submitLabel = $submitLabel ?? 'Submit for approval';

    $layout = (array) ($form->layout ?? []);
    $header = (array) ($layout['header'] ?? []);
    $rows = $layout['rows'] ?? null;
    $fieldsByKey = collect($fields)->keyBy('field_key');

    if (! is_array($rows) || count($rows) === 0) {
        $rows = collect($fields)
            ->map(fn ($f) => ['columns' => [['span' => 12, 'fields' => [$f->field_key]]]])
            ->all();
    }

    $disk = (string) config('documents.disk', 'public');
    $headerAlign = in_array(($header['align'] ?? 'center'), ['left', 'center', 'right'], true) ? ($header['align'] ?? 'center') : 'center';
    $alignClass = ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'][$headerAlign];

    // Literal class strings so Tailwind's source scanner generates them.
    $spanClasses = [
        1 => 'md:col-span-1', 2 => 'md:col-span-2', 3 => 'md:col-span-3', 4 => 'md:col-span-4',
        5 => 'md:col-span-5', 6 => 'md:col-span-6', 7 => 'md:col-span-7', 8 => 'md:col-span-8',
        9 => 'md:col-span-9', 10 => 'md:col-span-10', 11 => 'md:col-span-11', 12 => 'md:col-span-12',
    ];
@endphp

{{-- Header / letterhead --}}
@if (! empty($header['image']) || ! empty($header['logo']) || ! empty($header['title']) || ! empty($header['subtitle']))
    <div class="{{ $alignClass }} mb-6 rounded-2xl border border-gray-200 bg-palette-lime-pale p-5 shadow-[inset_0_4px_0_var(--color-palette-lime)] dark:border-gray-800 dark:bg-white/[0.03]">
        @if (! empty($header['image']) && Storage::disk($disk)->exists($header['image']))
            <img src="{{ Storage::disk($disk)->url($header['image']) }}" alt="header" class="mx-auto max-h-40 w-full object-contain" />
        @else
            @if (! empty($header['logo']) && Storage::disk($disk)->exists($header['logo']))
                <img src="{{ Storage::disk($disk)->url($header['logo']) }}" alt="logo" class="mx-auto mb-2 max-h-20" />
            @endif
            @if (! empty($header['title']))
                <h2 class="text-base font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ $header['title'] }}</h2>
            @endif
            @if (! empty($header['subtitle']))
                <p class="mt-0.5 text-sm font-semibold text-gray-800 dark:text-white/90">{{ $header['subtitle'] }}</p>
            @endif
        @endif
    </div>
@endif

<form action="{{ $preview ? '#' : route('forms.render.submit', $form->route_name) }}" method="POST" enctype="multipart/form-data" class="space-y-6"
    x-data="formConditions({ conditions: {{ Illuminate\Support\Js::from($conditions ?? []) }} })"
    @input="track($event)" @change="track($event)"
    @if ($preview) onsubmit="return false;" @endif>
    @csrf
    @foreach (($hidden ?? []) as $hiddenName => $hiddenValue)
        <input type="hidden" name="{{ $hiddenName }}" value="{{ $hiddenValue }}" />
    @endforeach

    <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
        <div class="space-y-5">
            @foreach ($rows as $row)
                @php
                    $rowHeader = trim((string) ($row['header'] ?? ''));
                    $rowText = trim((string) ($row['static_text'] ?? ''));
                @endphp
                <div class="space-y-3">
                    @if ($rowHeader !== '')
                        <h3 class="mb-1 border-l-[3px] border-palette-lime pl-3 text-base font-semibold text-gray-800 dark:text-white/90">{{ $rowHeader }}</h3>
                    @endif
                    @if ($rowText !== '')
                        <p class="whitespace-pre-line text-sm text-gray-600 dark:text-gray-400">{{ $rowText }}</p>
                    @endif
                    <div class="grid grid-cols-12 gap-4">
                    @foreach (($row['columns'] ?? []) as $col)
                        @php $span = max(1, min(12, (int) ($col['span'] ?? 12))); @endphp
                        <div class="col-span-12 {{ $spanClasses[$span] }} space-y-4">
                            @foreach (($col['fields'] ?? []) as $fieldKey)
                                @php $field = $fieldsByKey[$fieldKey] ?? null; @endphp
                                @if ($field)
                                    {{-- Conditional visibility: hidden wrappers also disable
                                         their controls so the values stay out of the POST. --}}
                                    <div x-show="visible(@js($fieldKey))" x-cloak
                                        x-effect="toggleDisabled($el, visible(@js($fieldKey)))">
                                        @include('components.form.fields.field', ['field' => $field, 'prefill' => $prefill, 'advisers' => $advisers ?? [], 'special' => $special ?? []])
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
        <div class="flex justify-end gap-3">
            @if ($preview)
                <span class="text-sm text-gray-400">Submission is disabled in preview.</span>
            @else
                <button type="reset" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Clear</button>
                <button type="submit" class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">{{ $submitLabel }}</button>
            @endif
        </div>
    </div>
</form>
