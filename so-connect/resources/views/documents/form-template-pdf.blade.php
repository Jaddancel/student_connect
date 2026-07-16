@php
    use App\Forms\SubmissionPresenter;

    /** @var \App\Models\Form $form */
    /** @var string $body */  // pre-rendered HTML from PdfTemplateRenderer (tokens already replaced)
    /** @var array $page */
    /** @var array $header */
    /** @var array $footer */

    $header = $header ?? [];
    $footer = $footer ?? [];
    $font = $font ?? [];
    $disk = (string) config('documents.disk', 'public');

    // Whitelist the document font (dompdf-safe families only) + clamp the size.
    $allowedFonts = [
        'Arial, Helvetica, sans-serif',
        "'Times New Roman', Times, serif",
        'Georgia, serif',
        "'Courier New', Courier, monospace",
        "'DejaVu Sans', sans-serif",
    ];
    $fontFamily = in_array(($font['family'] ?? ''), $allowedFonts, true) ? $font['family'] : "'Times New Roman', Times, serif";
    $fontSize = preg_match('/^(\d{1,2})px$/', (string) ($font['size'] ?? ''), $m) ? min(48, max(8, (int) $m[1])) : 12;

    $size = $page['size'] ?? 'a4';
    $size = in_array($size, ['a4', 'letter', 'legal'], true) ? $size : 'a4';
    $orientation = $page['orientation'] ?? 'portrait';
    $orientation = in_array($orientation, ['portrait', 'landscape'], true) ? $orientation : 'portrait';

    $headerAlign = ($header['align'] ?? 'center');
    if (! in_array($headerAlign, ['left', 'center', 'right'], true)) {
        $headerAlign = 'center';
    }

    // Resolve letterhead/footer images to embeddable data-URIs (dompdf needs these).
    $headerBanner = ! empty($header['image']) ? SubmissionPresenter::pathToDataUri($header['image'], $disk) : null;
    $headerLogo = ! empty($header['logo']) ? SubmissionPresenter::pathToDataUri($header['logo'], $disk) : null;
    $footerImage = ! empty($footer['image']) ? SubmissionPresenter::pathToDataUri($footer['image'], $disk) : null;

    $hasHeader = $headerBanner || $headerLogo || ! empty($header['title']) || ! empty($header['subtitle']);
    $hasFooter = (bool) $footerImage;

    // Reserve page margins for the running header/footer.
    $marginTop = $hasHeader ? 160 : 60;
    $marginBottom = $hasFooter ? 110 : 60;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: {{ $size }} {{ $orientation }}; margin: {{ $marginTop }}px 56px {{ $marginBottom }}px 56px; }
        body { font-family: {{ $fontFamily }}; font-size: {{ $fontSize }}px; color: #1a1a1a; line-height: 1.5; }
        h1 { font-size: 20px; font-weight: bold; margin: 0 0 8px; }
        h2 { font-size: 16px; font-weight: bold; margin: 0 0 6px; }
        h3 { font-size: 14px; font-weight: bold; margin: 0 0 4px; }
        p { margin: 0 0 8px; }
        ul, ol { margin: 0 0 8px 22px; padding: 0; }
        strong, b { font-weight: bold; }
        em, i { font-style: italic; }
        u { text-decoration: underline; }
        img.token-image { max-width: 100%; max-height: 160px; }
        [style*="text-align: center"] { text-align: center; }
        [style*="text-align: right"] { text-align: right; }

        /* Running header/footer repeated on every page. */
        .doc-header { position: fixed; top: -{{ $marginTop - 20 }}px; left: 0; right: 0; text-align: {{ $headerAlign }}; }
        .doc-header img.banner { width: 100%; max-height: {{ $marginTop - 30 }}px; object-fit: contain; }
        .doc-header img.logo { max-height: 70px; margin-bottom: 4px; }
        .doc-header .title { font-size: 16px; font-weight: bold; text-transform: uppercase; }
        .doc-header .subtitle { font-size: 12px; color: #444; margin-top: 2px; }
        .doc-footer { position: fixed; bottom: -{{ $marginBottom - 20 }}px; left: 0; right: 0; text-align: center; }
        .doc-footer img { width: 100%; max-height: {{ $marginBottom - 30 }}px; object-fit: contain; }
    </style>
</head>
<body>
    @if ($hasHeader)
        <div class="doc-header">
            @if ($headerBanner)
                <img class="banner" src="{{ $headerBanner }}" alt="header">
            @else
                @if ($headerLogo)
                    <div><img class="logo" src="{{ $headerLogo }}" alt="logo"></div>
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

    @if ($hasFooter)
        <div class="doc-footer">
            <img src="{{ $footerImage }}" alt="footer">
        </div>
    @endif

    {!! $body !!}
</body>
</html>
