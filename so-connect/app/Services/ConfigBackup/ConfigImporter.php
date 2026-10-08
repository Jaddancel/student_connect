<?php

namespace App\Services\ConfigBackup;

use App\Forms\FieldType;
use App\Forms\SystemFunction;
use App\Helpers\MenuHelper;
use App\Models\AppSetting;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\IdTemplate;
use App\Models\ReportTemplate;
use App\Models\ScoringCategory;
use App\Models\ScoringCriterion;
use App\Models\ScoringRule;
use App\Models\Template;
use App\Services\FormPrintTemplateService;
use App\Services\RequestTypeService;
use App\Support\ZonePayloadValidator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

/**
 * Merges a Configuration Backup (see ConfigExporter) into this system:
 * matching items are updated, missing ones created, and nothing that is not
 * in the backup is deleted. All database writes happen in one transaction;
 * template documents are written to new files so a rollback leaves the
 * current ones untouched.
 *
 * Items are matched by natural key: settings by key, forms by route_name (or
 * system_function), fields by field_key, printed-template slots by
 * slot_order, report and waiver templates by name, scoring categories/criteria by key
 * and rules by their criterion.
 */
class ConfigImporter
{
    private const MAX_ENTRIES = 1000;

    private const MAX_TOTAL_BYTES = 200 * 1024 * 1024;

    private const MAX_CONFIG_BYTES = 20 * 1024 * 1024;

    /** @var array<string,int> */
    private array $counts = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var list<string> files written during this import (removed on failure) */
    private array $written = [];

    /** @var array<int,Form> forms whose printed templates changed */
    private array $templatesChanged = [];

    /** @var array<int,true> ids of forms touched by the import */
    private array $importedForms = [];

    public function __construct(
        private readonly FormPrintTemplateService $printTemplates,
        private readonly RequestTypeService $requestTypes,
    ) {}

    /**
     * Whether the archive is a configuration backup (by its manifest).
     */
    public static function isConfigArchive(string $zipPath): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return false;
        }

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $zip->close();

        return is_array($manifest) && ($manifest['type'] ?? null) === ConfigExporter::TYPE;
    }

    /**
     * Check the archive's structure, compatibility and payload without
     * writing anything.
     */
    public function validate(string $zipPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Could not open the configuration backup.');
        }

        try {
            $this->assertSafeEntries($zip);
            $this->assertCompatible($this->readJson($zip, 'manifest.json'));
            $this->validated($this->readJson($zip, 'config.json'), $zip);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array{created: array<string,int>, updated: array<string,int>, warnings: list<string>}
     */
    public function import(string $zipPath): array
    {
        $this->counts = [];
        $this->warnings = [];
        $this->written = [];
        $this->templatesChanged = [];
        $this->importedForms = [];

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Could not open the configuration backup.');
        }

        try {
            $this->assertSafeEntries($zip);
            $this->assertCompatible($this->readJson($zip, 'manifest.json'));
            $config = $this->validated($this->readJson($zip, 'config.json'), $zip);

            try {
                DB::transaction(function () use ($config, $zip) {
                    $this->importScoringCatalog($config['scoring']);
                    foreach ($config['forms'] as $form) {
                        $this->importForm($form, $zip);
                    }
                    $this->importScoringRules($config['scoring']['rules'] ?? []);
                    foreach ($config['report_templates'] as $report) {
                        $this->importReportTemplate($report, $zip);
                    }
                    foreach ($config['waiver_templates'] as $waiver) {
                        $this->importWaiverTemplate($waiver, $zip);
                    }
                    $this->importSettings($config['settings']);
                });
            } catch (\Throwable $e) {
                $disk = $this->disk();
                foreach ($this->written as $path) {
                    $disk->delete($path);
                }

                throw $e;
            }
        } finally {
            $zip->close();
        }

        foreach ($config['settings'] as $setting) {
            AppSetting::forgetCached($setting['key']);
        }
        foreach (Form::query()->whereIn('id', array_keys($this->importedForms))->get() as $form) {
            $this->requestTypes->syncForm($form);
        }
        foreach ($this->templatesChanged as $form) {
            $this->printTemplates->queueManualSchemas($form->fresh());
        }

        return $this->summary();
    }

    // ── archive checks ──────────────────────────────────────────────────────

    private function assertSafeEntries(ZipArchive $zip): void
    {
        if ($zip->numFiles > self::MAX_ENTRIES) {
            throw new RuntimeException('The configuration backup has too many entries.');
        }

        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string) ($stat['name'] ?? '');

            if ($name === 'files/') {
                continue;
            }
            if (! in_array($name, ['manifest.json', 'config.json'], true) && ! preg_match('#^files/[0-9a-f]{40}\.(docx|'.$this->imageExtensions().')$#', $name)) {
                throw new RuntimeException('The configuration backup contains an unexpected file: '.$name);
            }

            $total += (int) ($stat['size'] ?? 0);
            if ($total > self::MAX_TOTAL_BYTES) {
                throw new RuntimeException('The configuration backup is too large to restore.');
            }
        }
    }

    /**
     * @return array<mixed>
     */
    private function readJson(ZipArchive $zip, string $name): array
    {
        $stat = $zip->statName($name);
        if ($stat === false) {
            throw new RuntimeException("The configuration backup is missing {$name}.");
        }
        if ((int) $stat['size'] > self::MAX_CONFIG_BYTES) {
            throw new RuntimeException("{$name} in the configuration backup is too large.");
        }

        $data = json_decode((string) $zip->getFromName($name), true);
        if (! is_array($data)) {
            throw new RuntimeException("{$name} in the configuration backup is not valid JSON.");
        }

        return $data;
    }

    /**
     * @param  array<mixed>  $manifest
     */
    private function assertCompatible(array $manifest): void
    {
        if (($manifest['type'] ?? null) !== ConfigExporter::TYPE) {
            throw new RuntimeException('This is not a configuration backup.');
        }
        if ((int) ($manifest['format_version'] ?? 0) < 1 || (int) $manifest['format_version'] > ConfigExporter::FORMAT_VERSION) {
            throw new RuntimeException('This configuration backup was made by a newer version of the system.');
        }

        $missing = array_diff((array) ($manifest['migrations'] ?? []), ConfigExporter::appliedMigrations());
        if ($missing !== []) {
            throw new RuntimeException(
                'This configuration backup was made on a newer database schema ('.count($missing).' migration(s) not run here). Update this system first.'
            );
        }
    }

    /**
     * @param  array<mixed>  $config
     * @return array<string,mixed>
     */
    private function validated(array $config, ZipArchive $zip): array
    {
        $slot = fn (string $prefix) => [
            "{$prefix}" => ['present', 'array'],
            "{$prefix}.*.name" => ['required', 'string', 'max:255'],
            "{$prefix}.*.slot_order" => ['required', 'integer', 'min:0', 'max:65535'],
            "{$prefix}.*.file" => ['required', 'string', 'regex:#^files/[0-9a-f]{40}\.docx$#'],
        ];

        $validator = Validator::make($config, [
            'settings' => ['present', 'array'],
            'settings.*.key' => ['required', 'string', 'max:255'],
            'settings.*.value' => ['present'],

            'forms' => ['present', 'array'],
            'forms.*.ref' => ['required', 'array'],
            'forms.*.attributes' => ['required', 'array'],
            'forms.*.attributes.name' => ['required', 'string', 'max:255'],
            'forms.*.attributes.route_name' => ['nullable', 'required_without:forms.*.attributes.system_function', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/'],
            'forms.*.attributes.system_function' => ['nullable', 'string', Rule::in(SystemFunction::keys())],
            'forms.*.attributes.icon' => ['nullable', 'string', Rule::in(MenuHelper::iconNames())],
            'forms.*.attributes.semester_submission_limit' => ['nullable', 'integer', 'min:1'],
            'forms.*.attributes.layout' => ['nullable', 'array'],
            'forms.*.attributes.pdf_template' => ['nullable', 'array'],
            'forms.*.attributes.sidebar_group' => ['nullable', 'array'],
            'forms.*.fields' => ['present', 'array'],
            'forms.*.fields.*.field_key' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_]+$/'],
            'forms.*.fields.*.field_label' => ['required', 'string', 'max:255'],
            'forms.*.fields.*.field_type' => ['required', 'string', Rule::in(FieldType::all())],
            'forms.*.fields.*.field_order' => ['required', 'integer'],
            'forms.*.fields.*.field_options' => ['nullable', 'array'],
            ...$slot('forms.*.templates'),

            'report_templates' => ['present', 'array'],
            'report_templates.*.attributes' => ['required', 'array'],
            'report_templates.*.attributes.name' => ['required', 'string', 'max:255'],
            'report_templates.*.attributes.icon' => ['nullable', 'string', Rule::in(MenuHelper::iconNames())],
            'report_templates.*.attributes.audience' => ['nullable', 'string', Rule::in(array_keys(ReportTemplate::audiences()))],
            'report_templates.*.attributes.definition' => ['present', 'array'],
            ...$slot('report_templates.*.templates'),

            // Absent in backups taken before waiver templates were included.
            'waiver_templates' => ['sometimes', 'array'],
            'waiver_templates.*.name' => ['required', 'string', 'max:255'],
            'waiver_templates.*.image_width' => ['required', 'integer', 'min:1'],
            'waiver_templates.*.image_height' => ['required', 'integer', 'min:1'],
            'waiver_templates.*.zones' => ['required', 'array', 'min:1'],
            'waiver_templates.*.file' => ['required', 'string', 'regex:#^files/[0-9a-f]{40}\.('.$this->imageExtensions().')$#'],

            'scoring' => ['required', 'array'],
            'scoring.categories' => ['present', 'array'],
            'scoring.categories.*.key' => ['required', 'string', 'max:32'],
            'scoring.categories.*.label' => ['required', 'string', 'max:255'],
            'scoring.categories.*.cap' => ['required', 'integer', 'min:0'],
            'scoring.criteria' => ['present', 'array'],
            'scoring.criteria.*.key' => ['required', 'string', 'max:64'],
            'scoring.criteria.*.category_key' => ['required', 'string', 'max:32'],
            'scoring.criteria.*.label' => ['required', 'string', 'max:255'],
            'scoring.criteria.*.weight' => ['required', 'integer', 'min:0'],
            'scoring.rules' => ['present', 'array'],
            'scoring.rules.*.criterion_key' => ['required', 'string', 'max:64'],
            'scoring.rules.*.trigger' => ['required', 'array'],
            'scoring.rules.*.workspace' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException('The configuration backup is invalid: '.$validator->errors()->first());
        }

        foreach ([...$config['forms'], ...$config['report_templates']] as $item) {
            foreach ($item['templates'] as $template) {
                if ($zip->statName($template['file']) === false) {
                    throw new RuntimeException('The configuration backup is missing a template document ('.$template['file'].').');
                }
            }
        }

        $config['waiver_templates'] ??= [];
        foreach ($config['waiver_templates'] as $index => $waiver) {
            if ($zip->statName($waiver['file']) === false) {
                throw new RuntimeException('The configuration backup is missing a waiver template image ('.$waiver['file'].').');
            }

            try {
                $config['waiver_templates'][$index]['zones'] = ZonePayloadValidator::validate($waiver['zones']);
            } catch (ValidationException $e) {
                throw new RuntimeException('The configuration backup is invalid: waiver template "'.$waiver['name'].'": '.$e->validator->errors()->first());
            }
        }

        return $config;
    }

    // ── forms ───────────────────────────────────────────────────────────────

    /**
     * @param  array<string,mixed>  $data
     */
    private function importForm(array $data, ZipArchive $zip): void
    {
        $attributes = $data['attributes'];
        $form = FormRef::find($data['ref']);
        $label = FormRef::label($data['ref']);

        $fill = collect($attributes)->only([
            'name', 'description_text', 'sidebar_group', 'is_active', 'is_published', 'icon',
            'show_in_sidebar', 'semester_submission_limit', 'field_kit', 'layout', 'pdf_template',
        ])->all();

        $route = $attributes['route_name'] ?? null;
        $function = $attributes['system_function'] ?? null;

        if ($form === null) {
            $form = new Form;
            $form->fill($fill + ['route_name' => $route, 'system_function' => $function, 'organization_id' => null, 'created_by' => null]);
            $form->save();
            $this->count('forms', true);
        } else {
            if ($route !== null && $route !== $form->route_name) {
                if (Form::query()->where('route_name', $route)->whereKeyNot($form->getKey())->exists()) {
                    $this->warnings[] = "Form \"{$label}\": kept its URL slug \"{$form->route_name}\"; \"{$route}\" is used by another form.";
                } else {
                    $fill['route_name'] = $route;
                }
            }
            if ($function !== null && $function !== $form->system_function) {
                if ($form->system_function !== null || Form::query()->where('system_function', $function)->exists()) {
                    $this->warnings[] = "Form \"{$label}\": system function \"{$function}\" was not applied; it is bound elsewhere here.";
                } else {
                    $fill['system_function'] = $function;
                }
            }

            $form->fill($fill)->save();
            $this->count('forms', false);
        }

        $this->importedForms[(int) $form->getKey()] = true;

        $existing = $form->fields()->get()->keyBy('field_key');
        foreach ($data['fields'] as $field) {
            $values = collect($field)->only([
                'field_label', 'field_type', 'is_required', 'field_order', 'placeholder_hint', 'field_options', 'universal_key',
            ])->all();

            if ($row = $existing->get($field['field_key'])) {
                $row->fill($values)->save();
                $this->count('fields', false);
            } else {
                FormDescription::query()->create($values + ['form_id' => $form->getKey(), 'field_key' => $field['field_key']]);
                $this->count('fields', true);
            }
        }

        $directory = trim((string) config('documents.templates_directory', 'form-templates'), '/').'/'.$form->getKey();
        $changed = $this->importSlots(
            $form->activeTemplates()->get(),
            $data['templates'],
            $zip,
            fn () => $directory.'/printed-template-'.Str::uuid().'.docx',
            ['form_id' => $form->getKey(), 'organization_id' => $form->organization_id],
        );
        if ($changed) {
            $this->templatesChanged[(int) $form->getKey()] = $form;
        }
    }

    // ── report templates ────────────────────────────────────────────────────

    /**
     * @param  array<string,mixed>  $data
     */
    private function importReportTemplate(array $data, ZipArchive $zip): void
    {
        $attributes = collect($data['attributes'])->only(['name', 'description', 'icon', 'audience', 'definition', 'is_active'])->all();
        $report = ReportTemplate::query()->where('name', $attributes['name'])->orderBy('id')->first();

        if ($report === null) {
            $report = ReportTemplate::query()->create($attributes + ['created_by' => null]);
            $this->count('report_templates', true);
        } else {
            $report->fill($attributes)->save();
            $this->count('report_templates', false);
        }

        $this->importSlots(
            $report->activeTemplates()->get(),
            $data['templates'],
            $zip,
            fn () => 'report-templates/'.$report->getKey().'/report-template-'.Str::uuid().'.docx',
            ['report_template_id' => $report->getKey()],
        );
    }

    // ── waiver templates ────────────────────────────────────────────────────

    /**
     * Matched by name; a changed reference image is written to a new file
     * (never in place).
     *
     * @param  array<string,mixed>  $data
     */
    private function importWaiverTemplate(array $data, ZipArchive $zip): void
    {
        $disk = $this->disk();
        $bytes = (string) $zip->getFromName($data['file']);
        if ($bytes === '') {
            throw new RuntimeException('A waiver template image in the configuration backup is empty.');
        }

        $attributes = [
            'image_width' => (int) $data['image_width'],
            'image_height' => (int) $data['image_height'],
            'zones' => $data['zones'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        $template = IdTemplate::query()->where('kind', 'waiver')->where('name', $data['name'])->orderBy('id_template_id')->first();
        $current = $template && $template->image_path && $disk->exists($template->image_path) ? $disk->get($template->image_path) : null;

        if ($template === null || $current !== $bytes) {
            $path = 'waiver-templates/'.Str::uuid().'.'.pathinfo($data['file'], PATHINFO_EXTENSION);
            if (! $disk->put($path, $bytes)) {
                throw new RuntimeException('Could not store a waiver template image from the configuration backup.');
            }
            $this->written[] = $path;
            $attributes['image_path'] = $path;
        }

        if ($template === null) {
            IdTemplate::query()->create($attributes + ['name' => $data['name'], 'kind' => 'waiver', 'created_by' => null]);
            $this->count('waiver_templates', true);
        } else {
            $template->fill($attributes)->save();
            $this->count('waiver_templates', false);
        }
    }

    /**
     * Merge printed-template slots by slot_order. A changed document is
     * written to a new file (never in place) and the version bumped, which is
     * what invalidates editors and manual schemas.
     *
     * @param  Collection<int, Template>  $active
     * @param  list<array{name:string, slot_order:int, file:string}>  $slots
     * @param  array<string,mixed>  $owner
     */
    private function importSlots(Collection $active, array $slots, ZipArchive $zip, callable $newPath, array $owner): bool
    {
        $disk = $this->disk();
        $bySlot = $active->groupBy('slot_order')->map->first();
        $changed = false;

        foreach ($slots as $slot) {
            $bytes = (string) $zip->getFromName($slot['file']);
            if ($bytes === '') {
                throw new RuntimeException('A template document in the configuration backup is empty.');
            }

            $template = $bySlot->get($slot['slot_order']);
            $current = $template && $disk->exists((string) $template->docx_path) ? $disk->get((string) $template->docx_path) : null;

            if ($template && $current === $bytes) {
                if ($template->template_name !== $slot['name']) {
                    $template->forceFill(['template_name' => $slot['name']])->save();
                }
                $this->count('templates', false);

                continue;
            }

            $path = $newPath();
            if (! $disk->put($path, $bytes)) {
                throw new RuntimeException('Could not store a template document from the configuration backup.');
            }
            $this->written[] = $path;
            $changed = true;

            if ($template) {
                $template->forceFill([
                    'template_name' => $slot['name'],
                    'docx_path' => $path,
                    'version' => (int) ($template->version ?? 1) + 1,
                ])->save();
                $template->touch();
                $this->count('templates', false);
            } else {
                Template::query()->create($owner + [
                    'uploaded_by' => null,
                    'template_name' => $slot['name'],
                    'docx_path' => $path,
                    'version' => 1,
                    'is_active' => true,
                    'slot_order' => $slot['slot_order'],
                ]);
                $this->count('templates', true);
            }
        }

        return $changed;
    }

    // ── tally / scoring ─────────────────────────────────────────────────────

    /**
     * @param  array<string,mixed>  $scoring
     */
    private function importScoringCatalog(array $scoring): void
    {
        foreach ($scoring['categories'] as $category) {
            $row = ScoringCategory::query()->firstOrNew(['key' => $category['key']]);
            $created = ! $row->exists;
            $row->fill([
                'label' => $category['label'],
                'cap' => (int) $category['cap'],
                'sort_order' => (int) ($category['sort_order'] ?? 0),
            ])->save();
            $this->count('scoring_categories', $created);
        }

        foreach ($scoring['criteria'] as $criterion) {
            $row = ScoringCriterion::query()->firstOrNew(['key' => $criterion['key']]);
            $created = ! $row->exists;
            $row->fill(collect($criterion)->only(['category_key', 'label', 'weight', 'sort_order', 'is_system', 'is_active'])->all())->save();
            $this->count('scoring_criteria', $created);
        }
    }

    /**
     * @param  list<array<string,mixed>>  $rules
     */
    private function importScoringRules(array $rules): void
    {
        $criteria = ScoringCriterion::query()->pluck('scoring_criterion_id', 'key');

        foreach ($rules as $rule) {
            $criterionId = $criteria[$rule['criterion_key']] ?? null;
            if ($criterionId === null) {
                $this->warnings[] = "Tally rule for \"{$rule['criterion_key']}\" was skipped; its criterion is missing.";

                continue;
            }

            $row = ScoringRule::query()->firstOrNew(['criterion_id' => $criterionId]);
            $created = ! $row->exists;

            $trigger = $rule['trigger'];
            $enabled = (bool) ($rule['enabled'] ?? true);
            if (isset($trigger['when']) && is_array($trigger['when']) && array_key_exists('form', $trigger['when'])) {
                $ref = $trigger['when']['form'];
                if ($ref === null) {
                    // The rule's form was already gone where the backup was
                    // taken; keep whatever this system currently points at.
                    $formId = (int) ($row->trigger['when']['form_id'] ?? 0);
                } else {
                    $form = FormRef::find($ref);
                    if ($form === null) {
                        $this->warnings[] = "Tally rule for \"{$rule['criterion_key']}\" was disabled; form \"".FormRef::label($ref).'" does not exist here.';
                        $enabled = false;
                    }
                    $formId = (int) ($form?->getKey() ?? 0);
                }
                $trigger['when']['form_id'] = $formId;
                unset($trigger['when']['form']);
            }

            $row->fill([
                'workspace' => $rule['workspace'] ?? null,
                'trigger' => $trigger,
                'enabled' => $enabled,
                'updated_by' => $created ? null : $row->updated_by,
            ])->save();
            $this->count('scoring_rules', $created);
        }
    }

    // ── settings ────────────────────────────────────────────────────────────

    /**
     * @param  list<array{key:string, value:mixed}>  $settings
     */
    private function importSettings(array $settings): void
    {
        foreach ($settings as $setting) {
            $value = $setting['value'];
            $path = ConfigExporter::FORM_ID_SETTINGS[$setting['key']] ?? null;

            if ($path !== null && is_array($value) && isset($value[$path]) && is_array($value[$path])) {
                $ids = [];
                foreach ($value[$path] as $ref) {
                    $form = FormRef::find($ref);
                    if ($form) {
                        $ids[] = (int) $form->getKey();
                    } else {
                        $this->warnings[] = "Setting \"{$setting['key']}\": form \"".FormRef::label($ref).'" does not exist here and was left out.';
                    }
                }
                $value[$path] = $ids;
            }

            $row = AppSetting::query()->firstOrNew(['key' => $setting['key']]);
            $created = ! $row->exists;
            $row->fill(['value' => $value])->save();
            $this->count('settings', $created);
        }
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function count(string $what, bool $created): void
    {
        $bucket = $created ? 'created' : 'updated';
        $this->counts[$bucket][$what] = ($this->counts[$bucket][$what] ?? 0) + 1;
    }

    /**
     * @return array{created: array<string,int>, updated: array<string,int>, warnings: list<string>}
     */
    private function summary(): array
    {
        return [
            'created' => $this->counts['created'] ?? [],
            'updated' => $this->counts['updated'] ?? [],
            'warnings' => $this->warnings,
        ];
    }

    private function imageExtensions(): string
    {
        return implode('|', ConfigExporter::IMAGE_EXTENSIONS);
    }

    private function disk()
    {
        return Storage::disk((string) config('documents.disk', 'public'));
    }
}
