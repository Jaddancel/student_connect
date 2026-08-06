<?php

return [
    'disk' => env('DOCUMENTS_DISK', 'public'),

    'templates_directory' => env('DOCUMENTS_TEMPLATES_DIRECTORY', 'form-templates'),

    'generated_directory' => env('DOCUMENTS_GENERATED_DIRECTORY', 'generated-documents'),

    'libreoffice' => [
        'binary' => env('LIBREOFFICE_BINARY', 'soffice'),
        'timeout' => (int) env('LIBREOFFICE_TIMEOUT', 120),
    ],

    // Warm-listener conversion sidecar (docker/docxconvert). Blank disables it
    // and every conversion goes straight to the `libreoffice.binary` above.
    'converter' => [
        'url' => env('DOCX_CONVERT_URL'),
        'timeout' => (int) env('DOCX_CONVERT_TIMEOUT', 120),
    ],

    'pdfunite_binary' => env('PDFUNITE_BINARY', 'pdfunite'),
];
