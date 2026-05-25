<?php

return [
    'disk' => env('DOCUMENTS_DISK', 'public'),

    'templates_directory' => env('DOCUMENTS_TEMPLATES_DIRECTORY', 'form-templates'),

    'generated_directory' => env('DOCUMENTS_GENERATED_DIRECTORY', 'generated-documents'),

    'libreoffice' => [
        'binary' => env('LIBREOFFICE_BINARY', 'soffice'),
        'timeout' => (int) env('LIBREOFFICE_TIMEOUT', 120),
    ],

    'pdfunite_binary' => env('PDFUNITE_BINARY', 'pdfunite'),
];
