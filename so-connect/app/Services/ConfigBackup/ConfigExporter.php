<?php

namespace App\Services\ConfigBackup;

use App\Models\AppSetting;
use App\Models\Form;
use App\Models\IdTemplate;
use App\Models\ReportTemplate;
use App\Models\ScoringCategory;
use App\Models\ScoringCriterion;
use App\Models\ScoringRule;
use App\Models\Template;
use App\Services\FormPrintTemplateService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Writes a Configuration Backup archive: settings, forms (+ fields), report
 * templates, Step 2 printed templates (+ their .docx files), waiver templates
 * (+ their reference images) and the tally (scoring) configuration.
 *
 * Archive layout:
 *   manifest.json   type / format version / origin
 *   config.json     the configuration payload
 *   files/<sha1>.docx  template documents, referenced from config.json
 *   files/<sha1>.<img> waiver template reference images
 *
 * Cross-references use natural keys (see FormRef), never DB ids. Anything
 * tied to a particular organization is left blank, and user references are
 * dropped — neither means the same thing in another environment.
 */
class ConfigExporter
{
    public const TYPE = 'configuration';

    public const FORMAT_VERSION = 1;

    /** Image extensions a waiver template reference image may have in the archive. */
    public const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'];

    /** Settings whose value embeds form ids, keyed by path within the value. */
    public const FORM_ID_SETTINGS = ['accreditation.conditions' => 'required_forms'];

    /** @var array<string,string> archive entry name => absolute source path */
    private array $files = [];

    public function __construct(private readonly FormPrintTemplateService $templates) {}

    public function export(string $zipPath): void
    {
        $this->files = [];
        $payload = $this->payload();

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the configuration backup archive.');
        }

        $zip->addFromString('manifest.json', json_encode([
            'type' => self::TYPE,
            'format_version' => self::FORMAT_VERSION,
            'created_at' => now()->toIso8601String(),
            'app_name' => (string) config('app.name'),
            'migrations' => self::appliedMigrations(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('config.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        foreach ($this->files as $entry => $source) {
            $zip->addFile($source, $entry);
        }

        if (! $zip->close()) {
            throw new RuntimeException('Could not write the configuration backup archive.');
        }
    }

    /**
     * Schema the backup was taken against; a restore refuses archives that
     * need migrations this system has not run.
     *
     * @return list<string>
     */
    public static function appliedMigrations(): array
    {
        return DB::table('migrations')->orderBy('id')->pluck('migration')->all();
    }

    /**
     * @return array<string,mixed>
     */
    private function payload(): array
    {
        $formRefs = Form::query()->get()->mapWithKeys(fn (Form $form) => [(int) $form->getKey() => FormRef::of($form)]);

        return [
            'settings' => $this->settings($formRefs),
            'forms' => $this->forms(),
            'report_templates' => $this->reportTemplates(),
            'waiver_templates' => $this->waiverTemplates(),
            'scoring' => $this->scoring($formRefs),
        ];
    }

    /**
     * @param  Collection<int, array|null>  $formRefs
     * @return list<array{key:string, value:mixed}>
     */
    private function settings(Collection $formRefs): array
    {
        return AppSetting::query()->orderBy('key')->get()->map(function (AppSetting $setting) use ($formRefs) {
            $value = $setting->value;
            $path = self::FORM_ID_SETTINGS[$setting->key] ?? null;

            if ($path !== null && is_array($value) && isset($value[$path]) && is_array($value[$path])) {
                $value[$path] = collect($value[$path])
                    ->map(fn ($id) => $formRefs[(int) $id] ?? null)
                    ->filter()
                    ->values()
                    ->all();
            }

            return ['key' => $setting->key, 'value' => $value];
        })->all();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function forms(): array
    {
        return Form::query()->orderBy('id')->get()
            ->filter(fn (Form $form) => FormRef::of($form) !== null)
            ->map(fn (Form $form) => [
                'ref' => FormRef::of($form),
                'attributes' => [
                    'name' => $form->name,
                    'description_text' => $form->description_text,
                    'sidebar_group' => $form->sidebar_group,
                    'is_active' => (bool) $form->is_active,
                    'is_published' => (bool) $form->is_published,
                    'route_name' => $form->route_name,
                    'icon' => $form->icon,
                    'show_in_sidebar' => (bool) ($form->show_in_sidebar ?? true),
                    'semester_submission_limit' => $form->semester_submission_limit,
                    'system_function' => $form->system_function,
                    'field_kit' => $form->field_kit,
                    'layout' => $form->layout,
                    'pdf_template' => $form->pdf_template,
                ],
                'fields' => $form->fields()->get()->map(fn ($field) => [
                    'field_key' => $field->field_key,
                    'field_label' => $field->field_label,
                    'field_type' => $field->field_type,
                    'is_required' => (bool) $field->is_required,
                    'field_order' => (int) $field->field_order,
                    'placeholder_hint' => $field->placeholder_hint,
                    'field_options' => $field->field_options,
                    'universal_key' => $field->universal_key,
                ])->values()->all(),
                'templates' => $this->templateSlots($this->templates->activeTemplates($form)),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function reportTemplates(): array
    {
        return ReportTemplate::query()->orderBy('id')->get()->map(fn (ReportTemplate $report) => [
            'attributes' => [
                'name' => $report->name,
                'description' => $report->description,
                'icon' => $report->icon,
                'audience' => $report->audience,
                'definition' => self::blankOrganizationFilters($report->definition ?? []),
                'is_active' => (bool) $report->is_active,
            ],
            'templates' => $this->templateSlots($this->templates->activeReportTemplates($report)),
        ])->values()->all();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function waiverTemplates(): array
    {
        $disk = Storage::disk((string) config('documents.disk', 'public'));

        return IdTemplate::query()->where('kind', 'waiver')->orderBy('id_template_id')->get()
            ->filter(fn (IdTemplate $template) => $template->image_path
                && $disk->exists($template->image_path)
                && in_array(strtolower(pathinfo($template->image_path, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true))
            ->map(function (IdTemplate $template) use ($disk) {
                $source = $disk->path($template->image_path);
                $entry = 'files/'.sha1_file($source).'.'.strtolower(pathinfo($template->image_path, PATHINFO_EXTENSION));
                $this->files[$entry] = $source;

                return [
                    'name' => (string) $template->name,
                    'image_width' => (int) $template->image_width,
                    'image_height' => (int) $template->image_height,
                    'zones' => $template->zones ?? [],
                    'is_active' => (bool) $template->is_active,
                    'file' => $entry,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array|null>  $formRefs
     * @return array<string,mixed>
     */
    private function scoring(Collection $formRefs): array
    {
        $criterionKeys = ScoringCriterion::query()->pluck('key', 'scoring_criterion_id');

        return [
            'categories' => ScoringCategory::query()->orderBy('sort_order')->get()
                ->map(fn ($c) => $c->only(['key', 'label', 'cap', 'sort_order']))
                ->values()->all(),
            'criteria' => ScoringCriterion::query()->orderBy('sort_order')->get()
                ->map(fn ($c) => [
                    'key' => $c->key,
                    'category_key' => $c->category_key,
                    'label' => $c->label,
                    'weight' => (int) $c->weight,
                    'sort_order' => (int) $c->sort_order,
                    'is_system' => (bool) $c->is_system,
                    'is_active' => (bool) $c->is_active,
                ])->values()->all(),
            'rules' => ScoringRule::query()->orderBy('scoring_rule_id')->get()
                ->filter(fn (ScoringRule $rule) => isset($criterionKeys[$rule->criterion_id]))
                ->map(function (ScoringRule $rule) use ($criterionKeys, $formRefs) {
                    $trigger = (array) $rule->trigger;
                    if (isset($trigger['when']) && is_array($trigger['when']) && array_key_exists('form_id', $trigger['when'])) {
                        $trigger['when']['form'] = $formRefs[(int) $trigger['when']['form_id']] ?? null;
                        unset($trigger['when']['form_id']);
                    }

                    return [
                        'criterion_key' => $criterionKeys[$rule->criterion_id],
                        'workspace' => $rule->workspace,
                        'trigger' => $trigger,
                        'enabled' => (bool) $rule->enabled,
                    ];
                })->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, Template>  $templates
     * @return list<array{name:string, slot_order:int, file:string}>
     */
    private function templateSlots(Collection $templates): array
    {
        $disk = Storage::disk((string) config('documents.disk', 'public'));

        return $templates->map(function (Template $template) use ($disk) {
            $source = $disk->path((string) $template->docx_path);
            $entry = 'files/'.sha1_file($source).'.docx';
            $this->files[$entry] = $source;

            return [
                'name' => (string) $template->template_name,
                'slot_order' => (int) $template->slot_order,
                'file' => $entry,
            ];
        })->values()->all();
    }

    /**
     * Report WHERE conditions pinned to a literal organization are tied to
     * that organization: keep the condition but blank its value.
     */
    public static function blankOrganizationFilters(mixed $node): mixed
    {
        if (! is_array($node)) {
            return $node;
        }

        foreach ($node as $key => $child) {
            $node[$key] = self::blankOrganizationFilters($child);
        }

        if (is_string($node['column'] ?? null)
            && preg_match('/(^|[._])organization(_id)?$/', $node['column'])
            && array_key_exists('value', $node)) {
            $node['value'] = null;
        }

        return $node;
    }
}
