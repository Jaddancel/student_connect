@extends('layouts.app')

@php
    use Illuminate\Support\Facades\Storage;

    /** @var \App\Models\Form $form */
    /** @var \Illuminate\Support\Collection $fields */
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

@section('content')
    <x-common.page-breadcrumb :pageTitle="$form->name" />

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- Header / letterhead --}}
        @if (! empty($header['image']) || ! empty($header['logo']) || ! empty($header['title']) || ! empty($header['subtitle']))
            <div class="{{ $alignClass }} rounded-2xl border border-gray-200 bg-palette-lime-pale p-5 shadow-[inset_0_4px_0_var(--color-palette-lime)] dark:border-gray-800 dark:bg-white/[0.03]">
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

        <form action="{{ route('forms.render.submit', $form->route_name) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="space-y-5">
                    @foreach ($rows as $row)
                        <div class="grid grid-cols-12 gap-4">
                            @foreach (($row['columns'] ?? []) as $col)
                                @php $span = max(1, min(12, (int) ($col['span'] ?? 12))); @endphp
                                <div class="col-span-12 {{ $spanClasses[$span] }} space-y-4">
                                    @foreach (($col['fields'] ?? []) as $fieldKey)
                                        @php $field = $fieldsByKey[$fieldKey] ?? null; @endphp
                                        @if ($field)
                                            @include('components.form.fields.field', ['field' => $field])
                                        @endif
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-palette-surface p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div class="flex justify-end gap-3">
                    <button type="reset" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Clear</button>
                    <button type="submit" class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">Submit &amp; Generate PDF</button>
                </div>
            </div>
        </form>
    </div>
@endsection
