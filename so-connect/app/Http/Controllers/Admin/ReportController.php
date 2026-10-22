<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportTemplate;
use App\Reports\ReportDefinitionValidator;
use App\Reports\ReportGenerator;
use App\Reports\ReportQueryEngine;
use App\Services\ActionLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $availableReports = ReportTemplate::availableTo($request->user());
        $categories = \App\Models\ContentCategory::query()->active()->ordered()->get();
        $categoryCounts = $availableReports
            ->countBy(fn (ReportTemplate $report) => $report->category_id ? (string) $report->category_id : 'uncategorized');

        $reports = $availableReports
            ->when($request->filled('template'), fn ($items) => $items->where('id', $request->integer('template'))->values())
            ->when($request->filled('category'), fn ($items) => $items->where('category_id', $request->integer('category'))->values())
            ->map(function (ReportTemplate $report) use ($engine, $validator) {
                $parameters = [];
                $contextNotice = null;
                try {
                    $definition = $validator->validate((array) $report->definition);
                    $notice = fn () => ($organization = $engine->activeOrganization()) !== null
                        ? 'For '.$organization['name'].' (your selected organization). Use the Organization filter above to change it.'
                        : 'Choose an organization in the Report filters above to generate this report.';
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
                    $scopes = $engine->scopeAvailability($definition);
                    $valid = true;
                } catch (ValidationException) {
                    $scopes = [];
                    $valid = false;
                }

                return ['model' => $report, 'parameters' => $parameters, 'scopes' => $scopes, 'valid' => $valid, 'notice' => $contextNotice];
            });

        $reportGroups = collect();
        foreach ($categories as $category) {
            $entries = $reports->filter(fn (array $entry) => (int) $entry['model']->category_id === (int) $category->getKey())->values();
            if ($entries->isNotEmpty()) {
                $reportGroups->push([
                    'key' => 'category-'.$category->getKey(),
                    'category' => $category,
                    'name' => $category->name,
                    'description' => $category->description,
                    'icon' => $category->icon ?: 'pages',
                    'color' => $category->color ?: 'brand',
                    'reports' => $entries,
                ]);
            }
        }

        $uncategorized = $reports->filter(fn (array $entry) => ! $entry['model']->category_id)->values();
        if ($uncategorized->isNotEmpty()) {
            $reportGroups->push([
                'key' => 'uncategorized',
                'category' => null,
                'name' => 'Other Reports',
                'description' => 'Reports that have not yet been assigned to a purpose category.',
                'icon' => 'pages',
                'color' => 'gray',
                'reports' => $uncategorized,
            ]);
        }

        return view('pages.admin.reports.index', [
            'title' => 'Reports',
            'reports' => $reports,
            'reportGroups' => $reportGroups,
            'categories' => $categories,
            'categoryCounts' => $categoryCounts,
            'totalReportCount' => $availableReports->count(),
            'scopeOptions' => [
                'organization' => DB::table('organizations as o')
                    ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                    ->orderBy('od.name')
                    ->get(['o.organization_id', 'od.name'])
                    ->map(fn ($organization) => [
                        'value' => (string) $organization->organization_id,
                        'label' => (string) ($organization->name ?: 'Organization #'.$organization->organization_id),
                    ])->all(),
                'organization_status' => [
                    ['value' => 'new', 'label' => 'New Organization'],
                    ['value' => 'accredited', 'label' => 'Accredited Organization'],
                ],
                'school_year' => $engine->parameterOptions([
                    'entity' => 'school_years',
                    'display' => ['label'],
                ]),
                'semester' => $engine->parameterOptions([
                    'entity' => 'semesters',
                    'display' => ['name'],
                ]),
            ],
        ]);
    }

    public function generate(Request $request, ReportTemplate $reportTemplate, ReportGenerator $generator)
    {
        abort_unless($reportTemplate->is_active && $reportTemplate->isAvailableTo($request->user()), 404);

        $validated = $request->validate([
            'format' => ['nullable', 'in:pdf,docx'],
            'params' => ['nullable', 'array'],
            'scope' => ['nullable', 'array'],
            'scope.organization' => ['nullable', 'integer', 'exists:organizations,organization_id'],
            'scope.organization_status' => ['nullable', 'in:new,accredited'],
        ]);
        $format = $validated['format'] ?? 'pdf';
        $scope = (array) ($validated['scope'] ?? []);

        try {
            $file = $generator->generate($reportTemplate, (array) ($validated['params'] ?? []), $format, $request->user(), $scope);
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
            ['report_template_id' => (int) $reportTemplate->getKey(), 'params' => (array) ($validated['params'] ?? []), 'scope' => $scope],
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
