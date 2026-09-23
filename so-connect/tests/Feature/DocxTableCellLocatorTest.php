<?php

use App\Services\ManualForm\DocxTableCellLocator;

/**
 * Writes a minimal `.docx` (only `word/document.xml` is needed by the locator)
 * wrapping the given body markup, with US Letter page geometry and 1" margins.
 */
function locatorDocx(string $bodyXml): string
{
    $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        .'<w:body>'.$bodyXml
        .'<w:sectPr>'
        .'<w:pgSz w:w="12240" w:h="15840"/>'
        .'<w:pgMar w:left="1440" w:right="1440" w:top="1440" w:bottom="1440"/>'
        .'</w:sectPr>'
        .'</w:body></w:document>';

    $path = tempnam(sys_get_temp_dir(), 'loc').'.docx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', $document);
    $zip->close();

    return $path;
}

function locatorCell(string $inner): string
{
    return '<w:tc><w:p><w:r><w:t>'.$inner.'</w:t></w:r></w:p></w:tc>';
}

function locatorRow(string $cells, int $height = 600, string $rule = 'exact'): string
{
    return '<w:tr><w:trPr><w:trHeight w:val="'.$height.'" w:hRule="'.$rule.'"/></w:trPr>'.$cells.'</w:tr>';
}

function locatorTable(string $rows): string
{
    return '<w:tbl><w:tblGrid><w:gridCol w:w="4680"/><w:gridCol w:w="4680"/></w:tblGrid>'.$rows.'</w:tbl>';
}

it('maps a field placeholder to its encapsulating table cell', function () {
    $docx = locatorDocx(locatorTable(
        locatorRow(locatorCell('Adviser 1 Name:').locatorCell('{{adviser1_name}}'))
        .locatorRow(locatorCell('Adviser 2 Name:').locatorCell('{{adviser2_name}}'))
    ));

    $mapping = app(DocxTableCellLocator::class)->mapFieldsToCells($docx, ['adviser1_name', 'adviser2_name']);

    expect($mapping)->toHaveKeys(['adviser1_name', 'adviser2_name'])
        ->and($mapping['adviser1_name'])->toMatchArray(['block' => 0, 'row' => 0, 'col' => 1])
        ->and($mapping['adviser2_name'])->toMatchArray(['block' => 0, 'row' => 1, 'col' => 1]);

    @unlink($docx);
});

it('derives normalized cell bounds from the grid and row geometry', function () {
    $docx = locatorDocx(locatorTable(
        locatorRow(locatorCell('Adviser 1 Name:').locatorCell('{{adviser1_name}}'))
    ));

    $geometry = app(DocxTableCellLocator::class)->geometryFor(
        $docx,
        ['block' => 0, 'row' => 0, 'col' => 1],
        'text',
    );

    // x1 = (1440 margin + 4680 first column) / 12240 = 0.5
    expect($geometry['page'])->toBe(0)
        ->and($geometry['bounds'][0])->toBe(0.5)
        ->and($geometry['bounds'][2])->toBeGreaterThan(0.85)
        ->and($geometry['writable_area'])->toBe('present');

    @unlink($docx);
});

it('skips repeating tables so row-cloning fields stay on the vision path', function () {
    $docx = locatorDocx(locatorTable(
        locatorRow(locatorCell('{{member_name#}}').locatorCell('{{member_role#}}'))
    ));

    $mapping = app(DocxTableCellLocator::class)->mapFieldsToCells($docx, ['member_name', 'member_role']);

    expect($mapping)->toBe([]);

    @unlink($docx);
});

it('reports missing space when a preceding grown row pushes a field off the page', function () {
    $locator = app(DocxTableCellLocator::class);
    $cellPath = ['block' => 0, 'row' => 1, 'col' => 1];

    // Baseline: a short first row leaves the target row comfortably on the page.
    $baseline = locatorDocx(locatorTable(
        locatorRow(locatorCell('Header').locatorCell('value'))
        .locatorRow(locatorCell('Adviser 2 Name:').locatorCell('{{adviser2_name}}'))
    ));

    // Populated: a long digital value grew the first row past the printable
    // height, so the target row now overflows the page (no room to write).
    $populated = locatorDocx(locatorTable(
        locatorRow(locatorCell('Header').locatorCell('long value'), 14000)
        .locatorRow(locatorCell('Adviser 2 Name:').locatorCell('{{adviser2_name}}'))
    ));

    expect($locator->geometryFor($baseline, $cellPath, 'text')['writable_area'])->not->toBe('missing')
        ->and($locator->geometryFor($populated, $cellPath, 'text')['writable_area'])->toBe('missing');

    @unlink($baseline);
    @unlink($populated);
});

it('shrinks writable width when a label precedes the placeholder in the cell', function () {
    $locator = app(DocxTableCellLocator::class);

    // A cell wide enough for the placeholder alone, but a long inline label
    // eats the remaining room so a required field loses its space.
    $withLabel = locatorDocx(locatorTable(
        locatorRow(locatorCell('spacer').locatorCell(
            'Complete adviser full legal name here: {{adviser1_name}}'
        ))
    ));

    $geometry = $locator->geometryFor($withLabel, ['block' => 0, 'row' => 0, 'col' => 1], 'text');

    expect($geometry['writable_area'])->toBe('missing');

    @unlink($withLabel);
});
