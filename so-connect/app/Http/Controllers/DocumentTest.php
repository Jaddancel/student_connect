<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;

class DocumentTest extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $templateProcessor = new TemplateProcessor('private/test.docx');
        $activities = [
            [
                'title-of-act' => 'Field Trip to Museum',
                'date-of-act' => 'May 12, 2026',
                'students' => 'John Doe, Jane Smith',
                'problems' => 'Bus was 15 minutes late.',
            ],
            [
                'title-of-act' => 'Science Fair Setup',
                'date-of-act' => 'May 14, 2026',
                'students' => 'Alice Johnson',
                'problems' => 'None',
            ],
            [
                'title-of-act' => 'Coding Bootcamp',
                'date-of-act' => 'May 15, 2026',
                'students' => 'Mark Lee, Sara Connor',
                'problems' => 'Wi-Fi connectivity issues.',
            ],
        ];

        $templateProcessor->setValue('name', 'John');
        $templateProcessor->setValue('year', 'Doe');
        $templateProcessor->cloneRowAndSetValues('title-of-act', $activities);
        $templateProcessor->saveAs('private/result.docx');
    }
}
