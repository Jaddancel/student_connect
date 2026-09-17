<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Services\DocxTemplateService;
use App\Services\FormPrintTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Blank printable PDF of a published form — the form's active printed
 * (Step 2) template populated with empty values, converted to PDF, and
 * streamed inline. This is what the `/forms` directory cards link to: a
 * print-and-fill-on-paper copy, not the online renderer.
 *
 * Read-only by contract: unlike the builder's editor flow this never seeds a
 * missing template — a form whose template row or .docx is gone simply 404s,
 * matching exactly what the directory lists.
 */
class FormBlankPdfController extends Controller
{
    public function show(
        Request $request,
        string $routeName,
        FormPrintTemplateService $templates,
        DocxTemplateService $docx,
    ) {
        // Same gate as the rendered form pages: built forms are for
        // organization officers/presidents.
        abort_unless(
            Form::isAccessibleBy($request->user()),
            403,
            'Blank form PDFs are available to organization officers only.',
        );

        $form = Form::query()
            ->where('route_name', $routeName)
            ->where('is_published', true)
            ->where('is_active', true)
            ->directoryEligible()
            ->firstOrFail();

        $template = $templates->activeStored($form);
        abort_if($template === null, 404);

        try {
            // populate() blanks every placeholder that has no data behind it
            // (fillRemaining), so passing no values yields a clean blank form —
            // the generated document never ships raw {{tokens}}.
            $generatedPath = $docx->toPdf($docx->populate($template, []));
        } catch (\Throwable $throwable) {
            report($throwable);

            abort(500, 'Could not generate the blank PDF.');
        }

        // Read then drop the scratch directory so neither the PDF nor the
        // intermediate .docx behind it outlives the response.
        $contents = File::get($generatedPath);
        File::deleteDirectory(dirname($generatedPath));

        $filename = Str::slug((string) ($template->template_name ?: $form->name)) ?: 'form';

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'-blank.pdf"',
        ]);
    }
}
