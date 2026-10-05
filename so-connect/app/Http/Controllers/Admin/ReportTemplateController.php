<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\MenuHelper;
use App\Http\Controllers\Controller;
use App\Models\ReportTemplate;
use App\Reports\ReportDefinitionValidator;
use App\Reports\ReportPalette;
use App\Reports\ReportQueryEngine;
use App\Reports\SchemaCatalog;
use App\Services\ActionLogger;
use App\Services\FormPrintTemplateService;
use App\Services\OnlyOfficeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Report Templates (user type 2): a re-implementation of the form-builder
 * wizard where Step 1 defines data tokens over the introspected schema
 * instead of form fields, Step 2 is the same OnlyOffice printed-template
 * editor, and Step 3 holds the details.
 */
class ReportTemplateController extends Controller
{
    public function index()
    {
        $reports = ReportTemplate::query()
            ->withCount(['templates as slots_count' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return view('pages.admin.report-templates.index', [
            'title' => 'Report Templates',
            'reports' => $reports,
        ]);
    }

    public function create()
    {
        return $this->editor(null);
    }

    public function edit(ReportTemplate $reportTemplate)
    {
        return $this->editor($reportTemplate);
    }

    private function editor(?ReportTemplate $report)
    {
        return view('pages.admin.report-templates.editor', [
            'title' => $report ? 'Edit Report Template' : 'New Report Template',
            'report' => $report,
            'editorData' => [
                'name' => $report?->name ?? '',
                'description' => $report?->description ?? '',
                'icon' => $report?->icon ?? '',
                'audience' => $report?->audience ?? ReportTemplate::AUDIENCE_ADMINS,
                'is_active' => $report?->is_active ?? true,
                'definition' => $report?->definition ?? ['parameters' => [], 'tokens' => []],
            ],
            'audiences' => ReportTemplate::audiences(),
            'iconChoices' => collect(MenuHelper::iconNames())
                ->mapWithKeys(fn (string $name) => [$name => MenuHelper::getIconSvg($name)])
                ->all(),
        ]);
    }

    public function store(Request $request, ReportDefinitionValidator $validator, FormPrintTemplateService $templates): JsonResponse
    {
        $data = $this->validated($request, $validator);

        $report = DB::transaction(fn () => ReportTemplate::query()->create(array_merge($data['attributes'], [
            'created_by' => $request->user()->getKey(),
        ])));

        $this->adoptDraft($report, $data['draft_id'], (int) $request->user()->getKey(), $templates);

        ActionLogger::log(ActionLogger::CATEGORY_REPORTS, 'created', 'Created report template "'.$report->name.'"', ['report_template_id' => (int) $report->getKey()], $report);
        session()->flash('success', 'Report template saved.');

        return response()->json(['message' => 'Saved.', 'redirect' => route('admin.report-templates.index')]);
    }

    public function update(Request $request, ReportTemplate $reportTemplate, ReportDefinitionValidator $validator, FormPrintTemplateService $templates): JsonResponse
    {
        $data = $this->validated($request, $validator);

        $reportTemplate->update($data['attributes']);
        $this->adoptDraft($reportTemplate, $data['draft_id'], (int) $request->user()->getKey(), $templates);

        ActionLogger::log(ActionLogger::CATEGORY_REPORTS, 'updated', 'Updated report template "'.$reportTemplate->name.'"', ['report_template_id' => (int) $reportTemplate->getKey()], $reportTemplate);
        session()->flash('success', 'Report template saved.');

        return response()->json(['message' => 'Saved.', 'redirect' => route('admin.report-templates.index')]);
    }

    public function destroy(ReportTemplate $reportTemplate)
    {
        $disk = Storage::disk((string) config('documents.disk', 'public'));
        foreach ($reportTemplate->templates()->get() as $slot) {
            if ($slot->docx_path) {
                $disk->delete((string) $slot->docx_path);
            }
        }
        $name = $reportTemplate->name;
        $id = (int) $reportTemplate->getKey();
        $reportTemplate->delete();

        ActionLogger::log(ActionLogger::CATEGORY_REPORTS, 'deleted', 'Deleted report template "'.$name.'"', ['report_template_id' => $id]);

        return redirect()->route('admin.report-templates.index')->with('success', 'Report template deleted.');
    }

    /**
     * The data catalog for the Data Box pills.
     */
    public function schema(SchemaCatalog $catalog): JsonResponse
    {
        return response()->json([
            'tables' => $catalog->forEditor(),
            'ops' => ReportDefinitionValidator::OPS,
            'aggregates' => ReportDefinitionValidator::AGGREGATES,
            'formats' => ReportDefinitionValidator::FORMATS,
            'paramTypes' => ReportDefinitionValidator::PARAM_TYPES,
        ]);
    }

    /**
     * Data Preview: run the (unsaved) definition with a row cap.
     */
    public function preview(Request $request, ReportQueryEngine $engine): JsonResponse
    {
        $request->validate([
            'definition' => ['present', 'array'],
            'params' => ['nullable', 'array'],
        ]);

        try {
            $result = $engine->run((array) $request->input('definition', []), (array) $request->input('params', []), preview: true);
        } catch (ValidationException $exception) {
            return response()->json(['errors' => collect($exception->errors())->flatten()->values()], 422);
        } catch (\Throwable $throwable) {
            report($throwable);

            return response()->json(['errors' => ['The preview query failed: '.$throwable->getMessage()]], 422);
        }

        return response()->json(['data' => $result['data'], 'truncated' => $result['truncated']]);
    }

    /**
     * Options for an entity parameter (preview inputs).
     */
    public function parameterOptions(Request $request, ReportDefinitionValidator $validator, ReportQueryEngine $engine): JsonResponse
    {
        $request->validate(['parameter' => ['required', 'array']]);

        try {
            $definition = $validator->validate(['parameters' => [$request->input('parameter')], 'tokens' => []]);
        } catch (ValidationException $exception) {
            return response()->json(['errors' => collect($exception->errors())->flatten()->values()], 422);
        }

        return response()->json(['options' => $engine->parameterOptions($definition['parameters'][0])]);
    }

    /**
     * Entering Step 2: snapshot the report's tokens into a printed-template
     * draft (the same non-persistent drafts the form builder uses) and return
     * the editor URLs.
     */
    public function syncDraft(Request $request, ReportDefinitionValidator $validator, FormPrintTemplateService $templates, OnlyOfficeService $onlyOffice): JsonResponse
    {
        $validated = $request->validate([
            'draft_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-]+$/'],
            'report_id' => ['nullable', 'integer', 'exists:report_templates,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'definition' => ['present', 'array'],
        ]);

        $definition = $validator->validate((array) $validated['definition']);
        $reportId = isset($validated['report_id']) ? (int) $validated['report_id'] : null;
        $userId = (int) $request->user()->getKey();

        $draftId = $validated['draft_id'] ?? (string) Str::uuid();
        $existing = $templates->readDraft($draftId);
        if ($existing !== null) {
            abort_unless(
                (int) $existing['created_by'] === $userId
                    && ($existing['owner'] ?? null) === 'report'
                    && ($existing['report_template_id'] ?? null) === $reportId,
                403,
            );
        }

        $name = trim((string) ($validated['name'] ?? '')) ?: 'Report template';
        $templates->writeDraft($draftId, null, $name, [], $existing !== null ? (int) $existing['version'] : 1, $userId, [
            'owner' => 'report',
            'report_template_id' => $reportId,
            'tokens' => ReportPalette::tokens($definition),
        ]);

        $report = $reportId !== null ? ReportTemplate::query()->find($reportId) : null;
        $templates->ensureDraftSlotsFrom(
            $draftId,
            $name,
            $report ? $templates->activeReportTemplates($report) : new \Illuminate\Database\Eloquent\Collection,
        );

        return response()->json([
            'draftId' => $draftId,
            'configUrl' => route('admin.form-builder.draft.config', $draftId),
            'importUrl' => route('admin.form-builder.draft.import', $draftId),
            'versionUrl' => route('admin.form-builder.draft.version', $draftId),
            'addUrl' => route('admin.form-builder.draft.slots.store', $draftId),
            'slots' => app(FormPrintTemplateController::class)->slotResponses($draftId, $templates),
        ]);
    }

    /**
     * @return array{attributes: array<string, mixed>, draft_id: ?string}
     */
    private function validated(Request $request, ReportDefinitionValidator $validator): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:64'],
            'audience' => ['required', 'in:'.implode(',', array_keys(ReportTemplate::audiences()))],
            'is_active' => ['nullable', 'boolean'],
            'definition' => ['present', 'array'],
            'draft_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-]+$/'],
        ]);

        $definition = $validator->validate((array) $validated['definition']);
        if ($definition['tokens'] === []) {
            throw ValidationException::withMessages(['definition' => 'Add at least one token in Step 1.']);
        }

        $icon = $validated['icon'] ?? null;

        return [
            'attributes' => [
                'name' => trim($validated['name']),
                'description' => $validated['description'] ?? null,
                'icon' => $icon !== null && in_array($icon, MenuHelper::iconNames(), true) ? $icon : null,
                'audience' => $validated['audience'],
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'definition' => $definition,
            ],
            'draft_id' => $validated['draft_id'] ?? null,
        ];
    }

    private function adoptDraft(ReportTemplate $report, ?string $draftId, int $userId, FormPrintTemplateService $templates): void
    {
        if ($draftId !== null && $templates->readDraft($draftId) !== null) {
            $templates->adoptReportDraft($report, $draftId, $userId);

            return;
        }

        // Never saved through Step 2: give the report a starter document so it
        // can always be generated (and edited later).
        if ($templates->activeReportTemplates($report)->isEmpty()) {
            $templates->createReportSlot($report, $report->name, $templates->blankDocumentFor($report->name), 0, $userId);
        }
    }
}
