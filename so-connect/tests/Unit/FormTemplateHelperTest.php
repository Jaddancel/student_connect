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

it('reads exact token usage across split runs and headers without normalizing keys', function () {
    $path = tempnam(sys_get_temp_dir(), 'token-docx-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', '<w:document><w:p><w:r><w:t>{{expenses.</w:t></w:r><w:r><w:t>amount#}}</w:t></w:r></w:p><w:p><w:r><w:t>{{Full_Name}} {{ Full_Name }} {{bad-key}} {{Full_Name}}</w:t></w:r></w:p></w:document>');
    $zip->addFromString('word/header1.xml', '<w:hdr><w:p><w:r><w:t>{{organization_name}}</w:t></w:r></w:p></w:hdr>');
    $zip->addFromString('word/styles.xml', '<w:t>{{not_a_document_token}}</w:t>');
    $zip->close();

    try {
        expect(FormTemplateHelper::exactTokenKeysFromDocxContents(file_get_contents($path)))
            ->toBe(['expenses.amount#', 'Full_Name', ' Full_Name ', 'bad-key', 'organization_name']);
    } finally {
        unlink($path);
    }
});

it('reports unreadable documents explicitly during token usage inspection', function () {
    FormTemplateHelper::exactTokenKeysFromDocxContents('not a zip');
})->throws(RuntimeException::class, 'Unable to read DOCX template file.');
