<?php

use App\Helpers\FormTemplateHelper;
use App\Models\Template\TemplateDescription;

it('extracts placeholders from docx files', function () {
    $docxPath = tempnam(sys_get_temp_dir(), 'docx_');

    if ($docxPath === false) {
        throw new RuntimeException('Unable to create a temporary DOCX path.');
    }

    unlink($docxPath);

    $zip = new ZipArchive();
    expect($zip->open($docxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document><w:body><w:p><w:r><w:t>{{ first_name }}</w:t></w:r><w:r><w:t>{{last_name}}</w:t></w:r></w:p></w:body></w:document>');
    $zip->close();

    try {
        expect(FormTemplateHelper::extractPlaceholdersFromDocx($docxPath))->toBe([
            'first_name',
            'last_name',
        ]);
    } finally {
        @unlink($docxPath);
    }
});

it('builds replacement maps and reports missing required fields', function () {
    $mappings = [
        new TemplateDescription([
            'field_key' => 'first_name',
            'placeholder_key' => 'first_name',
            'is_required' => true,
        ]),
        new TemplateDescription([
            'field_key' => 'middle_name',
            'placeholder_key' => 'middle_name',
            'is_required' => false,
        ]),
        new TemplateDescription([
            'field_key' => 'last_name',
            'placeholder_key' => 'last_name',
            'is_required' => true,
        ]),
    ];

    expect(FormTemplateHelper::missingRequiredFields($mappings, ['first_name' => 'Jane']))->toBe(['last_name']);

    expect(FormTemplateHelper::buildReplacementMap($mappings, [
        'first_name' => 'Jane',
        'middle_name' => null,
        'last_name' => 'Doe',
    ]))->toBe([
        'first_name' => 'Jane',
        'middle_name' => '',
        'last_name' => 'Doe',
    ]);
});
