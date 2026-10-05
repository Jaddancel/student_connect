<?php

namespace App\Reports;

use App\Support\DocxTemplateProcessor;

/**
 * {@see DocxTemplateProcessor} with access to the (split-run-repaired) main
 * document part, so {@see ReportDocxRenderer} can expand group blocks before
 * the normal placeholder fill runs.
 */
class ReportTemplateProcessor extends DocxTemplateProcessor
{
    public function mainPart(): string
    {
        return (string) $this->tempDocumentMainPart;
    }

    public function setMainPart(string $xml): void
    {
        $this->tempDocumentMainPart = $xml;
    }
}
