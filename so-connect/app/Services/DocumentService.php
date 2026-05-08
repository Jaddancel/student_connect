<?php

namespace App\Services;

use PhpOffice\PhpWord\TemplateProcessor;

class DocumentService
{
    public function writeDocument(int $template, array $fields)
    {
        switch ($template) {
            case 0:
                $this->accomplishedReport($fields);
                break;
        }
    }

    public function accomplishedReport(array $fields)
    {
        $templateProcessor = new TemplateProcessor($fields['filename']);
        $activities = $fields['activity'];
        $templateProcessor->setValue('name', $fields['name']);
        $templateProcessor->setValue('year', $fields['year']);
        $templateProcessor->cloneRowAndSetValues('title-of-act', $activities);
    }
}
