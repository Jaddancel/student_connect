<?php

namespace App\Reports;

use App\Models\Organization;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Services\DocxTemplateService;
use App\Services\FormPrintTemplateService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Generates a report: runs its definition through the {@see ReportQueryEngine}
 * and prints every active template slot with {@see ReportDocxRenderer}. One
 * slot yields one .docx/.pdf; several are bundled into a .zip.
 */
final class ReportGenerator
{
    public function __construct(
        private readonly ReportQueryEngine $engine,
        private readonly ReportDocxRenderer $renderer,
        private readonly DocxTemplateService $docx,
        private readonly FormPrintTemplateService $templates,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     * @return array{path: string, filename: string, mime: string, directory: string}
     *                                                                                 `directory` is a scratch dir the caller must delete
     */
    public function generate(ReportTemplate $report, array $parameters, string $format, ?User $user): array
    {
        $format = $format === 'docx' ? 'docx' : 'pdf';
        $slots = $this->templates->activeReportTemplates($report);
        if ($slots->isEmpty()) {
            throw new RuntimeException('This report has no printed template yet.');
        }

        $result = $this->engine->run((array) $report->definition, $parameters);
        $organization = $this->organizationFor((array) $report->definition, $result['params']);

        $directory = storage_path('app/tmp/report-'.Str::uuid());
        File::ensureDirectoryExists($directory);
        $base = Str::slug((string) $report->name) ?: 'report';
        $stamp = now()->format('Y-m-d');

        try {
            $files = [];
            foreach ($slots as $index => $slot) {
                $docxPath = $this->renderer->render($slot, $result['data'], $user, $organization);
                try {
                    $path = $format === 'pdf' ? $this->docx->toPdf($docxPath) : $docxPath;
                    $name = $base.($slots->count() > 1 ? '-'.(Str::slug((string) $slot->template_name) ?: ($index + 1)) : '').'-'.$stamp.'.'.$format;
                    File::copy($path, $directory.'/'.$name);
                    $files[] = $directory.'/'.$name;
                } finally {
                    File::deleteDirectory(dirname($docxPath));
                }
            }

            if (count($files) === 1) {
                return [
                    'path' => $files[0],
                    'filename' => basename($files[0]),
                    'mime' => $format === 'pdf'
                        ? 'application/pdf'
                        : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'directory' => $directory,
                ];
            }

            $zipPath = $directory.'/'.$base.'-'.$stamp.'.zip';
            $zip = new \ZipArchive;
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not bundle the report files.');
            }
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();

            return ['path' => $zipPath, 'filename' => basename($zipPath), 'mime' => 'application/zip', 'directory' => $directory];
        } catch (\Throwable $throwable) {
            File::deleteDirectory($directory);

            throw $throwable;
        }
    }

    /**
     * The organization `{{profile.org_*}}` tokens resolve against: the value of
     * the first organization parameter, when one was picked.
     *
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $params
     */
    private function organizationFor(array $definition, array $params): ?Organization
    {
        foreach ((array) ($definition['parameters'] ?? []) as $parameter) {
            if (($parameter['type'] ?? null) === 'entity'
                && ($parameter['entity'] ?? null) === 'organizations'
                && ($params[$parameter['name']] ?? null) !== null) {
                return Organization::query()->find((int) $params[$parameter['name']]);
            }
        }

        $sessionOrganization = $params[ReportDefinitionValidator::SESSION_ORGANIZATION] ?? null;

        return $sessionOrganization !== null ? Organization::query()->find((int) $sessionOrganization) : null;
    }
}
