@php
    use App\Forms\FieldType;
    use App\Forms\SubmissionPresenter;

    /** @var \App\Models\Form $form */
    /** @var \Illuminate\Support\Collection $fields */ // FormDescription, ordered
    /** @var array $payload */

    $layout = (array) ($form->layout ?? []);
    $header = (array) ($layout['header'] ?? []);
    $rows = $layout['rows'] ?? null;

    // Index fields by key for layout lookups.
    $fieldsByKey = collect($fields)->keyBy('field_key');

    // Fallback layout: a single column per field, in order.
    if (! is_array($rows) || count($rows) === 0) {
        $rows = collect($fields)
            ->map(fn ($f) => ['columns' => [['span' => 12, 'fields' => [$f->field_key]]]])
            ->all();
    }

    $headerAlign = ($header['align'] ?? 'center');
    if (! in_array($headerAlign, ['left', 'center', 'right'], true)) {
        $headerAlign = 'center';
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 110px 40px 60px 40px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #1a1a1a; }
        .doc-header { text-align: {{ $headerAlign }}; margin-bottom: 18px; border-bottom: 1px solid #d0d0d0; padding-bottom: 10px; }
        .doc-header img.banner { width: 100%; max-height: 130px; object-fit: contain; }
        .doc-header img.logo { max-height: 70px; margin-bottom: 6px; }
        .doc-header .title { font-size: 16px; font-weight: bold; text-transform: uppercase; }
        .doc-header .subtitle { font-size: 12px; color: #444; margin-top: 2px; }
        .form-title { text-align: center; font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin: 6px 0 18px; }
        table.row { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.row td { vertical-align: top; padding: 0 6px; }
        .field-label { font-size: 9px; text-transform: uppercase; letter-spacing: .4px; color: #666; margin-bottom: 2px; }
        .field-value { font-size: 12px; min-height: 16px; border-bottom: 1px solid #cfcfcf; padding-bottom: 3px; }
        .field-value.empty { color: #aaa; }
        .heading { font-size: 13px; font-weight: bold; border-left: 3px solid #84cc16; padding-left: 8px; margin: 10px 0 4px; }
        .static-text { font-size: 11px; color: #333; white-space: pre-line; }
        .sig img, .img-field img { max-width: 100%; max-height: 120px; }
        .img-field img { margin-right: 6px; }
    </style>
</head>
<body>
    {{-- Letterhead / header --}}
    @if (! empty($header['image']) || ! empty($header['logo']) || ! empty($header['title']) || ! empty($header['subtitle']))
        <div class="doc-header">
            @php $bannerUri = ! empty($header['image']) ? SubmissionPresenter::pathToDataUri($header['image'], (string) config('documents.disk', 'public')) : null; @endphp
            @if ($bannerUri)
                <img class="banner" src="{{ $bannerUri }}" alt="header">
            @else
                @php $logoUri = ! empty($header['logo']) ? SubmissionPresenter::pathToDataUri($header['logo'], (string) config('documents.disk', 'public')) : null; @endphp
                @if ($logoUri)
                    <div><img class="logo" src="{{ $logoUri }}" alt="logo"></div>
                @endif
                @if (! empty($header['title']))
                    <div class="title">{{ $header['title'] }}</div>
                @endif
                @if (! empty($header['subtitle']))
                    <div class="subtitle">{{ $header['subtitle'] }}</div>
                @endif
            @endif
        </div>
    @endif

    <div class="form-title">{{ $form->name ?? 'Form' }}</div>

    @foreach ($rows as $row)
        @php $columns = $row['columns'] ?? []; @endphp
        @if (count($columns))
            <table class="row">
                <tr>
                    @foreach ($columns as $col)
                        @php $span = (int) ($col['span'] ?? 12); $width = max(1, min(12, $span)) / 12 * 100; @endphp
                        <td style="width: {{ $width }}%;">
                            @foreach (($col['fields'] ?? []) as $fieldKey)
                                @php $field = $fieldsByKey[$fieldKey] ?? null; @endphp
                                @if ($field)
                                    @php
                                        $type = $field->field_type;
                                        $opts = (array) ($field->field_options ?? []);
                                    @endphp
                                    @if ($type === FieldType::HEADING)
                                        <div class="heading">{{ $field->field_label }}</div>
                                    @elseif ($type === FieldType::STATIC_TEXT)
                                        <div class="static-text">{{ $opts['content'] ?? $field->field_label }}</div>
                                    @elseif (in_array($type, [FieldType::IMAGE, FieldType::FILE], true))
                                        <div class="field-label">{{ $field->field_label }}</div>
                                        <div class="img-field">
                                            @php $uris = SubmissionPresenter::imageDataUris($payload, $fieldKey); @endphp
                                            @forelse ($uris as $uri)
                                                <img src="{{ $uri }}" alt="{{ $field->field_label }}">
                                            @empty
                                                <span class="field-value empty">—</span>
                                            @endforelse
                                        </div>
                                    @elseif ($type === FieldType::SIGNATURE)
                                        <div class="field-label">{{ $field->field_label }}</div>
                                        <div class="sig">
                                            @php $uris = SubmissionPresenter::imageDataUris($payload, $fieldKey); @endphp
                                            @forelse ($uris as $uri)
                                                <img src="{{ $uri }}" alt="signature">
                                            @empty
                                                <span class="field-value empty">—</span>
                                            @endforelse
                                        </div>
                                    @else
                                        @php $display = SubmissionPresenter::display($payload, $fieldKey, $type, $opts); @endphp
                                        <div class="field-label">{{ $field->field_label }}</div>
                                        <div class="field-value {{ $display === '' ? 'empty' : '' }}">{{ $display === '' ? '—' : $display }}</div>
                                    @endif
                                @endif
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            </table>
        @endif
    @endforeach
</body>
</html>
