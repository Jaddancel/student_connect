<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportTemplate;
use App\Reports\ReportDefinitionValidator;
use App\Reports\ReportGenerator;
use App\Reports\ReportQueryEngine;
use App\Services\ActionLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

/**
 * The Reports page: the active report templates available to the user
 * (admins and organization officers, per each template's audience), with their
 * "ask at generation" parameters, downloadable as PDF or Word.
 */
class ReportController extends Controller
{
    public function index(Request $request, ReportQueryEngine $engine, ReportDefinitionValidator $validator)
    {
        $reports = ReportTemplate::availableTo($request->user())
            ->map(function (ReportTemplate $report) use ($engine, $validator) {
                $parameters = [];
                $contextNotice = null;
                try {
                    $definition = $validator->validate((array) $report->definition);
                    $notice = fn () => ($organization = $engine->activeOrganization()) !== null
                        ? 'For '.$organization['name'].' (your selected organization).'
                        : 'Select an organization in the organization switcher to generate this report.';
                    if ($engine->usesActiveOrganization($definition)) {
                        $contextNotice = $notice();
                    }
                    foreach ($definition['parameters'] as $parameter) {
                        if (($parameter['context'] ?? null) === ReportDefinitionValidator::CONTEXT_ACTIVE_ORGANIZATION) {
                            $contextNotice = $notice();

                            continue;
                        }
                        if ($parameter['type'] === 'entity') {
                            $parameter['options'] = $engine->parameterOptions($parameter);
                        }
                        $parameter['selected'] = (string) ($engine->parameterDefault($parameter) ?? '');
                        $parameters[] = $parameter;
                    }
                    $valid = true;
                } catch (ValidationException) {
                    $valid = false;
                }

                return ['model' => $report, 'parameters' => $parameters, 'valid' => $valid, 'notice' => $contextNotice];
            });

        return view('pages.admin.reports.index', [
            'title' => 'Reports',
            'reports' => $reports,
        ]);
    }

    public function generate(Request $request, ReportTemplate $reportTemplate, ReportGenerator $generator)
    {
        abort_unless($reportTemplate->is_active && $reportTemplate->isAvailableTo($request->user()), 404);

        $validated = $request->validate([
            'format' => ['nullable', 'in:pdf,docx'],
            'params' => ['nullable', 'array'],
        ]);
        $format = $validated['format'] ?? 'pdf';

        try {
            $file = $generator->generate($reportTemplate, (array) ($validated['params'] ?? []), $format, $request->user());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            report($throwable);

            $message = 'Could not generate "'.$reportTemplate->name.'": '.$throwable->getMessage();

            return $request->expectsJson()
                ? response()->json(['message' => $message], 500)
                : back()->withErrors(['report' => $message]);
        }

        ActionLogger::log(
            ActionLogger::CATEGORY_REPORTS,
            'generated',
            'Generated report "'.$reportTemplate->name.'" ('.strtoupper($format).')',
            ['report_template_id' => (int) $reportTemplate->getKey(), 'params' => (array) ($validated['params'] ?? [])],
            $reportTemplate,
        );

        $contents = File::get($file['path']);
        File::deleteDirectory($file['directory']);

        return response($contents, 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'attachment; filename="'.$file['filename'].'"',
        ]);
    }
}
