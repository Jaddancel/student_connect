<?php

namespace App\Services\ManualForm;

use App\Models\Template as FormTemplate;
use App\Services\DocumentVision\DocumentVisionClient;
use App\Services\DocxTemplateService;
use App\Services\PdfRasterizer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Builds and persists a template's baseline manual-filling schema: the saved
 * field metadata plus best-effort VLM-located writable areas from a blank
 * render of the exact adopted template revision.
 *
 * Writable-area location is best-effort — if the vision model is unavailable
 * the fields are still recorded (areas marked `uncertain`); the authoritative
 * writable-area check happens per session against the frozen partial PDF.
 *
 * Version-stale results are discarded so a save that lands mid-generation is
 * not overwritten by an older run.
 */
class TemplateManualSchemaGenerator
{
    public const SCHEMA_TEMPLATE_VERSION = 1;

    public function __construct(
        private readonly ManualSchemaBuilder $builder,
        private readonly DocxTemplateService $docx,
        private readonly PdfRasterizer $rasterizer,
        private readonly DocumentVisionClient $vision,
    ) {}

    /**
     * Generate for the given template id at the expected version. No-ops when
     * the template is gone or its version has since moved.
     */
    public function generate(int $templateId, int $expectedVersion): void
    {
        $template = FormTemplate::find($templateId);
        if ($template === null || (int) $template->version !== $expectedVersion) {
            return;
        }

        $form = $template->form;
        if ($form === null) {
            $this->fail($template, $expectedVersion, 'form missing for template');

            return;
        }

        try {
            $fields = $this->builder->fieldsFor($form);
            $areas = $this->locateWritableAreas($template, $fields);

            $schema = [
                'template_id' => (int) $template->getKey(),
                'template_version' => $expectedVersion,
                'schema_template_version' => self::SCHEMA_TEMPLATE_VERSION,
                'fields' => $this->mergeAreas($fields, $areas),
            ];

            $fresh = FormTemplate::find($templateId);
            if ($fresh === null || (int) $fresh->version !== $expectedVersion) {
                return; // superseded while we worked
            }

            $fresh->forceFill([
                'manual_schema' => $schema,
                'manual_schema_status' => 'ready',
                'manual_schema_error' => null,
                'manual_schema_template_version' => $expectedVersion,
                'manual_schema_generated_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            report($e);
            $this->fail($template, $expectedVersion, 'schema generation failed');
        }
    }

    /**
     * Render a blank copy of the template, rasterize it, and ask the vision
     * model to locate each paper field's writable area. Returns [] on any
     * failure so the caller records the fields without blocking.
     *
     * @param  array<int,array<string,mixed>>  $fields
     * @return array<string,array{page:int,bounds:?array<int,float>,writable_area:string}>
     */
    private function locateWritableAreas(FormTemplate $template, array $fields): array
    {
        $paperFields = array_values(array_filter(
            $fields,
            fn ($f) => in_array($f['paper_support'] ?? '', ['extract', 'signature'], true),
        ));
        if ($paperFields === []) {
            return [];
        }

        $workDir = storage_path('app/manual-form/template-'.$template->getKey().'-'.uniqid());

        try {
            $pdfPath = $this->docx->toPdf($this->docx->populate($template, []));
        } catch (\Throwable $e) {
            Log::info('Manual baseline: blank render unavailable: '.$e->getMessage());

            return [];
        }

        try {
            $raster = $this->rasterizer->rasterize($pdfPath, $workDir);
            if (! ($raster['ok'] ?? false)) {
                return [];
            }

            $pages = [];
            foreach ($raster['pages'] as $page) {
                $pages[] = ['index' => $page['index'], 'image' => $this->encode($page['path'])];
            }

            $result = $this->vision->locateWritableAreas([
                'fields' => array_map(fn ($f) => [
                    'key' => $f['key'], 'label' => $f['label'], 'type' => $f['type'],
                ], $paperFields),
                'pages' => $pages,
            ]);

            return ($result['ok'] ?? false) ? (array) ($result['areas'] ?? []) : [];
        } finally {
            File::deleteDirectory($workDir);
            @File::delete($pdfPath);
        }
    }

    /**
     * @param  array<int,array<string,mixed>>  $fields
     * @param  array<string,array{page:int,bounds:?array<int,float>,writable_area:string}>  $areas
     * @return array<int,array<string,mixed>>
     */
    private function mergeAreas(array $fields, array $areas): array
    {
        return array_map(function (array $field) use ($areas) {
            if (! in_array($field['paper_support'] ?? '', ['extract', 'signature'], true)) {
                return $field;
            }

            $area = $areas[$field['key']] ?? null;

            return $field + [
                'page' => $area['page'] ?? null,
                'bounds' => $area['bounds'] ?? null,
                'writable_area' => $area['writable_area'] ?? 'uncertain',
            ];
        }, $fields);
    }

    private function encode(string $path): string
    {
        return 'data:image/png;base64,'.base64_encode((string) File::get($path));
    }

    private function fail(FormTemplate $template, int $expectedVersion, string $message): void
    {
        $fresh = FormTemplate::find($template->getKey());
        if ($fresh === null || (int) $fresh->version !== $expectedVersion) {
            return;
        }

        $fresh->forceFill([
            'manual_schema_status' => 'failed',
            'manual_schema_error' => $message,
            'manual_schema_template_version' => $expectedVersion,
            'manual_schema_generated_at' => now(),
        ])->save();
    }
}
