<?php

namespace App\Http\Controllers\Admin;

use App\Forms\ConditionEvaluator;
use App\Forms\FieldType;
use App\Forms\SystemFunction;
use App\Helpers\MenuHelper;
use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Services\FormPrintTemplateService;
use App\Support\UniversalField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin WYSIWYG form builder. Persists a form's canvas arrangement to
 * `forms.layout` and upserts its `form_descriptions` rows from the posted JSON,
 * replacing the DOCX Template Manager.
 */
class FormBuilderController extends Controller
{
    /**
     * Typeahead for a signature field's expected-signer picker. Returns matching
     * people by name, each annotated with their current organization role (from
     * their most recent officer assignment) — the only details the picker
     * exposes. Backs `admin.form-builder.signatory-search`.
     */
    public function searchSignatories(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $query = (string) ($validated['q'] ?? '');
        $limit = (int) ($validated['limit'] ?? 15);

        $profiles = \App\Helpers\ProfileMatchHelper::search($query, $limit);

        $profileIds = $profiles->pluck('profile_id')->map(fn ($id) => (int) $id)->all();
        $roleByProfile = [];
        if ($profileIds !== []) {
            $officers = \App\Models\Officer::query()
                ->join('users', 'users.user_id', '=', 'organization_officers.user')
                ->whereIn('users.profile', $profileIds)
                ->orderByDesc('organization_officers.org_officer_id')
                ->get(['users.profile as profile_id', 'organization_officers.role', 'organization_officers.position']);

            foreach ($officers as $officer) {
                $pid = (int) $officer->profile_id;
                if (isset($roleByProfile[$pid])) {
                    continue; // keep the most recent assignment (ordered desc)
                }
                $role = trim((string) ($officer->position ?: $officer->role));
                if ($role !== '') {
                    $roleByProfile[$pid] = Str::title($role);
                }
            }
        }

        $data = $profiles->map(function (\App\Models\Profile $profile) use ($roleByProfile) {
            $name = trim(implode(' ', array_filter(
                [$profile->first_name, $profile->middle_name, $profile->last_name],
                static fn ($part) => trim((string) $part) !== '',
            )));

            return [
                'profile_id' => (int) $profile->profile_id,
                'name' => $name,
                'org_role' => $roleByProfile[(int) $profile->profile_id] ?? null,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function index()
    {
        $forms = Form::query()
            ->withCount('fields')
            ->orderBy('name')
            ->get();

        // System-function slots: one row per function, showing the bound form
        // (or an empty slot the admin can fill).
        $boundByFunction = $forms->whereNotNull('system_function')->keyBy('system_function');
        $slots = collect(SystemFunction::catalog())->map(function ($meta, $key) use ($boundByFunction) {
            $form = $boundByFunction->get($key);

            return [
                'key' => $key,
                'label' => $meta['label'],
                'form' => $form,
            ];
        })->values();

        // Sign Up is the account-creation flow — it gets its own section,
        // separate from the other system functions.
        $signUpSlot = $slots->firstWhere('key', SystemFunction::SIGN_UP);
        $slots = $slots->reject(fn ($slot) => $slot['key'] === SystemFunction::SIGN_UP)->values();

        return view('pages.admin.form-builder.index', [
            'title' => 'Form Builder',
            // Regular forms table excludes function-bound forms (they live in
            // the System Functions / Sign Up sections).
            'forms' => $forms->whereNull('system_function')->values(),
            'signUpSlot' => $signUpSlot,
            'slots' => $slots,
        ]);
    }

    public function create(Request $request)
    {
        $editorData = $this->blankEditorData();
        $kit = null;

        // Creating a form for a system-function slot: pre-bind + lock the
        // function, and unlock its special-field palette. Rejected if the slot
        // is already filled.
        $function = (string) $request->query('function', '');
        if ($function !== '') {
            abort_unless(SystemFunction::has($function), 404);
            if (SystemFunction::form($function) !== null) {
                return redirect()->route('admin.form-builder.index')
                    ->with('toast_error', 'The "'.SystemFunction::label($function).'" function already has a form.');
            }
            $editorData['system_function'] = $function;
            $kit = $function;
        }

        return view('pages.admin.form-builder.editor', [
            'title' => 'New Form',
            'form' => null,
            'editorData' => $editorData,
            'fieldCatalog' => FieldType::paletteCatalog($kit),
            'kit' => $kit,
            'eventFieldChoices' => $kit === SystemFunction::NEW_WORKPLAN ? $this->eventFieldChoices() : [],
            'lockedFunction' => $function !== '' ? $function : null,
            'iconChoices' => $this->iconChoices(),
        ]);
    }

    public function edit(Form $form)
    {
        $kit = \App\Forms\FieldKit::forForm($form);

        return view('pages.admin.form-builder.editor', [
            'title' => 'Edit Form',
            'form' => $form,
            'editorData' => $this->editorDataFromForm($form),
            'fieldCatalog' => FieldType::paletteCatalog($kit),
            'kit' => $kit,
            'eventFieldChoices' => $kit === SystemFunction::NEW_WORKPLAN ? $this->eventFieldChoices() : [],
            // A form's system-function binding is immutable after creation.
            'lockedFunction' => $form->system_function ?: null,
            'iconChoices' => $this->iconChoices(),
            'manualSchema' => $this->manualSchemaStatus($form),
            'submissionLimitUsage' => ($semester = \App\Models\Semester::current())
                ? ['semester' => $semester, 'accepted' => \App\Forms\SemesterSubmissionLimit::acceptedCount($form, $semester)]
                : null,
        ]);
    }

    /**
     * Icon key => rendered SVG map for the builder's icon picker.
     *
     * @return array<string,string>
     */
    private function iconChoices(): array
    {
        return collect(MenuHelper::iconNames())
            ->mapWithKeys(fn (string $name) => [$name => MenuHelper::getIconSvg($name)])
            ->all();
    }

    /**
     * The New Events form's fields that can be printed as an Activity Table
     * column: everything with a submitted, cell-able value (presentational,
     * file, signature, waiver, photo-set and password fields are dropped). Used
     * by the workplan builder's Activity-Table column picker. Empty when the
     * New Events function has no bound form.
     *
     * @return array<int,array{key:string,label:string,type:string,type_label:string}>
     */
    private function eventFieldChoices(): array
    {
        $eventForm = SystemFunction::form(SystemFunction::NEW_EVENT);
        if ($eventForm === null) {
            return [];
        }

        $excluded = array_merge(
            FieldType::presentational(),
            FieldType::fileLike(),
            [FieldType::SIGNATURE, FieldType::WAIVER_SCAN, FieldType::MULTI_IMAGE, FieldType::PASSWORD],
        );

        return $eventForm->fields()
            ->orderBy('field_order')
            ->orderBy('id')
            ->get()
            ->reject(fn ($field) => in_array((string) $field->field_type, $excluded, true))
            ->map(fn ($field) => [
                'key' => (string) $field->field_key,
                'label' => (string) ($field->field_label ?: $field->field_key),
                'type' => (string) $field->field_type,
                'type_label' => FieldType::label((string) $field->field_type),
            ])
            ->values()
            ->all();
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request, null);

        $form = DB::transaction(function () use ($data, $request) {
            $form = Form::create([
                'name' => $data['name'],
                'description_text' => $data['description_text'],
                'route_name' => $data['route_name'],
                'icon' => $data['icon'],
                'semester_submission_limit' => $data['semester_submission_limit'],
                'system_function' => $data['system_function'],
                'is_active' => $data['is_active'],
                'is_published' => $data['is_published'],
                'created_by' => $request->user()?->getKey(),
                'layout' => ['rows' => $data['rows']],
                'pdf_template' => $data['pdf_template'],
            ]);

            $this->syncFields($form, $data['fields']);
            $this->syncRequestType($form, $request->user()?->getKey());

            return $form;
        });

        // Fold any Step-2 draft template into the now-persisted form.
        $this->adoptPrintedTemplateDraft($form, $data['draft_id'], $request->user()?->getKey());
        $this->dispatchManualSchema($form);

        \App\Services\ActionLogger::log(
            \App\Services\ActionLogger::CATEGORY_FORM_BUILDER,
            'created',
            'Created form "'.$form->name.'"',
            ['form_id' => (int) $form->getKey(), 'route_name' => $form->route_name, 'system_function' => $form->system_function],
            $form,
        );

        // The editor navigates back to the listing next; greet it with the toast.
        session()->flash('toast', 'Form created.');

        return response()->json([
            'message' => 'Form created.',
            'redirect' => route('admin.form-builder.index'),
        ]);
    }

    public function update(Request $request, Form $form)
    {
        $data = $this->validatePayload($request, $form);

        DB::transaction(function () use ($data, $form, $request) {
            $form->update([
                'name' => $data['name'],
                'description_text' => $data['description_text'],
                'route_name' => $data['route_name'],
                'icon' => $data['icon'],
                'semester_submission_limit' => $data['semester_submission_limit'],
                // The system-function binding is immutable once created.
                'system_function' => $form->system_function,
                'is_active' => $data['is_active'],
                'is_published' => $data['is_published'],
                'layout' => ['rows' => $data['rows']],
                'pdf_template' => $data['pdf_template'],
            ]);

            $this->syncFields($form, $data['fields']);
            $this->syncRequestType($form, $request->user()?->getKey());
        });

        // Fold any Step-2 draft template into the form (overwrites its .docx).
        $this->adoptPrintedTemplateDraft($form, $data['draft_id'], $request->user()?->getKey());
        $this->dispatchManualSchema($form);

        \App\Services\ActionLogger::log(
            \App\Services\ActionLogger::CATEGORY_FORM_BUILDER,
            'updated',
            'Updated form "'.$form->name.'"',
            ['form_id' => (int) $form->getKey(), 'route_name' => $form->route_name, 'system_function' => $form->system_function],
            $form,
        );

        // The editor navigates back to the listing next; greet it with the toast.
        session()->flash('toast', 'Form saved.');

        return response()->json([
            'message' => 'Form saved.',
            'redirect' => route('admin.form-builder.index'),
        ]);
    }

    public function destroy(Form $form)
    {
        $form->fields()->delete();
        $form->delete();

        \App\Services\ActionLogger::log(
            \App\Services\ActionLogger::CATEGORY_FORM_BUILDER,
            'deleted',
            'Deleted form "'.$form->name.'"',
            ['form_id' => (int) $form->getKey(), 'route_name' => $form->route_name],
        );

        return redirect()->route('admin.form-builder.index')
            ->with('success', 'Form deleted.');
    }

    /**
     * Upload a header image/logo; returns its disk-relative path for the layout.
     */
    public function uploadAsset(Request $request)
    {
        $request->validate([
            'asset' => ['required', 'image', 'max:5120'],
        ]);

        $path = $request->file('asset')->store(
            'form-headers',
            (string) config('documents.disk', 'public'),
        );

        return response()->json(['path' => $path]);
    }

    /**
     * @return array<string,mixed>
     */
    private function validatePayload(Request $request, ?Form $form): array
    {
        $formId = $form?->getKey();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description_text' => ['nullable', 'string', 'max:5000'],
            'route_name' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('forms', 'route_name')->ignore($formId),
            ],
            'system_function' => [
                'nullable', 'string', Rule::in(SystemFunction::keys()),
                Rule::unique('forms', 'system_function')->ignore($formId),
            ],
            'icon' => ['nullable', 'string', Rule::in(MenuHelper::iconNames())],
            'semester_submission_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'fields' => ['present', 'array'],
            'fields.*.field_key' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_]+$/'],
            'fields.*.field_label' => ['required', 'string', 'max:255'],
            'fields.*.field_type' => ['required', 'string', Rule::in(FieldType::all())],
            'fields.*.is_required' => ['boolean'],
            'fields.*.placeholder_hint' => ['nullable', 'string', 'max:255'],
            'fields.*.field_options' => ['nullable', 'array'],
            // Choice controls (select/radio/checkbox). Each option is a
            // {value,label} pair; these MUST be whitelisted or validate()
            // silently drops them and the field renders with no choices.
            'fields.*.field_options.options' => ['nullable', 'array'],
            'fields.*.field_options.options.*.value' => ['nullable', 'string', 'max:255'],
            'fields.*.field_options.options.*.label' => ['nullable', 'string', 'max:255'],
            // Dynamic option source: a select/search field drawing its choices
            // from a registered OptionSource instead of hand-typed options.
            // (Unlisted keys are silently dropped on save — keep this whitelisted.)
            'fields.*.field_options.source' => ['nullable', 'string', Rule::in(\App\Forms\OptionSource::keys())],
            // Presentational + numeric + upload option keys the builder emits.
            'fields.*.field_options.content' => ['nullable', 'string', 'max:5000'],
            'fields.*.field_options.rows' => ['nullable', 'integer', 'min:1', 'max:50'],
            'fields.*.field_options.min' => ['nullable', 'numeric'],
            'fields.*.field_options.max' => ['nullable', 'numeric'],
            'fields.*.field_options.step' => ['nullable', 'numeric'],
            'fields.*.field_options.accept' => ['nullable', 'string', 'max:255'],
            // Date/time autofill-with-now toggle.
            'fields.*.field_options.autofill_now' => ['nullable', 'boolean'],
            // Dropdown "allow multiple selections" toggle.
            'fields.*.field_options.multiple' => ['nullable', 'boolean'],
            // Same-row date-derived number calculations (e.g. birthday -> age).
            'fields.*.field_options.calculate_from' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            // Column totalled when calculate_from names a table field.
            'fields.*.field_options.calculate_column' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            // Special kit-scoped option keys: table columns + totals, computed
            // formulas, photo-set limits and media mirroring, event autofill.
            // Shared by table-input and activity-table columns. The key/type are
            // widened for activity-table (whose column key is a New Events field
            // key, and whose type is any FieldType); tableColumns() still coerces
            // the type down to its own four for table-input.
            'fields.*.field_options.columns' => ['nullable', 'array'],
            'fields.*.field_options.columns.*.key' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_]*$/'],
            'fields.*.field_options.columns.*.label' => ['nullable', 'string', 'max:255'],
            'fields.*.field_options.columns.*.type' => ['nullable', 'string', Rule::in(FieldType::all())],
            'fields.*.field_options.columns.*.required' => ['nullable', 'boolean'],
            'fields.*.field_options.row_total' => ['nullable', 'array'],
            'fields.*.field_options.row_total.key' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]*$/'],
            'fields.*.field_options.row_total.label' => ['nullable', 'string', 'max:255'],
            'fields.*.field_options.row_total.multiply' => ['nullable', 'array'],
            'fields.*.field_options.row_total.multiply.*' => ['nullable', 'string', 'max:64'],
            'fields.*.field_options.row_total.op' => ['nullable', 'string', Rule::in(FieldType::ROW_TOTAL_OPS)],
            'fields.*.field_options.formula' => ['nullable', 'string', Rule::in(['sum', 'difference', 'table_sum'])],
            'fields.*.field_options.args' => ['nullable', 'array'],
            'fields.*.field_options.args.*' => ['nullable', 'string', 'max:128'],
            'fields.*.field_options.table' => ['nullable', 'string', 'max:64'],
            'fields.*.field_options.column' => ['nullable', 'string', 'max:64'],
            'fields.*.field_options.max_files' => ['nullable', 'integer', 'min:1', 'max:10'],
            'fields.*.field_options.max_kb' => ['nullable', 'integer', 'min:1', 'max:10240'],
            'fields.*.field_options.media_copy' => ['nullable', 'string', Rule::in(['accomplishment'])],
            'fields.*.field_options.autofill_map' => ['nullable', 'array'],
            'fields.*.field_options.autofill_map.*' => ['nullable', 'string', 'max:64'],
            'fields.*.field_options.visible_when' => ['nullable', 'array'],
            'fields.*.field_options.visible_when.field' => ['nullable', 'string', 'max:255'],
            'fields.*.field_options.visible_when.op' => ['nullable', 'string', Rule::in(ConditionEvaluator::OPS)],
            'fields.*.field_options.visible_when.value' => ['nullable', 'string', 'max:255'],
            // Signature expected-signer config (Compare/Normal mode). Whitelisted
            // or validate() silently drops them like the option keys above.
            'fields.*.field_options.match_mode' => ['nullable', 'string', Rule::in(['normal', 'compare'])],
            'fields.*.field_options.expected_positions' => ['nullable', 'array'],
            'fields.*.field_options.expected_positions.*' => ['nullable', 'string', Rule::in(FieldType::POSITION_OPTIONS)],
            'fields.*.field_options.expected_profiles' => ['nullable', 'array'],
            'fields.*.field_options.expected_profiles.*' => ['nullable', 'integer'],
            // Display-only companion for the chips (backend reads expected_profiles).
            'fields.*.field_options.expected_people' => ['nullable', 'array'],
            'fields.*.field_options.expected_people.*.id' => ['nullable', 'integer'],
            'fields.*.field_options.expected_people.*.name' => ['nullable', 'string', 'max:255'],
            'fields.*.field_options.expected_people.*.org_role' => ['nullable', 'string', 'max:255'],
            'fields.*.universal_key' => ['nullable', 'string', Rule::in(UniversalField::keys())],
            'rows' => ['present', 'array'],
            // A row's header + static text render at the top of that row.
            'rows.*.header' => ['nullable', 'string', 'max:255'],
            'rows.*.static_text' => ['nullable', 'string', 'max:5000'],
            'pdf_template' => ['nullable', 'array'],
            'pdf_template.html' => ['nullable', 'string'],
            'pdf_template.page' => ['nullable', 'array'],
            'pdf_template.page.size' => ['nullable', 'string', Rule::in(['a4', 'letter', 'legal'])],
            'pdf_template.page.orientation' => ['nullable', 'string', Rule::in(['portrait', 'landscape'])],
            'pdf_template.font' => ['nullable', 'array'],
            'pdf_template.font.family' => ['nullable', 'string', 'max:120'],
            'pdf_template.font.size' => ['nullable', 'string', 'max:8'],
            'pdf_template.header' => ['nullable', 'array'],
            'pdf_template.footer' => ['nullable', 'array'],
            // Non-persistent Step-2 printed-template draft to fold into the real
            // template after save (see adoptDraft). Optional — a form saved
            // without opening Step 2 has none.
            'draft_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-]+$/'],
        ], [
            'route_name.regex' => 'The route name may only contain lowercase letters, numbers and hyphens.',
            'fields.*.field_key.regex' => 'Field keys may only contain letters, numbers and underscores.',
        ]);

        // Ensure field keys are unique within the form.
        $keys = array_column($validated['fields'], 'field_key');
        if (count($keys) !== count(array_unique($keys))) {
            abort(response()->json(['message' => 'Field keys must be unique within a form.'], 422));
        }

        $this->enforceFieldKit($validated, $form);

        $validated['fields'] = $this->normalizeImageOptions($validated['fields']);
        $validated['fields'] = $this->normalizeVisibilityConditions($validated['fields']);
        $validated['fields'] = $this->normalizeActivityTableColumns($validated['fields']);

        $pdfTemplate = $this->cleanPdfTemplate($validated['pdf_template'] ?? []);

        return [
            'name' => $validated['name'],
            'description_text' => $validated['description_text'] ?? null,
            'route_name' => $validated['route_name'],
            'icon' => ($validated['icon'] ?? '') !== '' ? $validated['icon'] : null,
            'semester_submission_limit' => isset($validated['semester_submission_limit']) ? (int) $validated['semester_submission_limit'] : null,
            'system_function' => ($validated['system_function'] ?? '') !== '' ? $validated['system_function'] : null,
            // Saving publishes: there is no draft/unpublished state — every
            // form goes live immediately on save/create.
            'is_active' => true,
            'is_published' => true,
            'fields' => $validated['fields'],
            // Validating rows.*.header/static_text makes $validated['rows'] hold
            // only those sub-keys (dropping columns), so sanitize the raw input;
            // the validation above still enforces the header/static-text limits.
            'rows' => $this->cleanRows($request->input('rows', []), $keys),
            'pdf_template' => $pdfTemplate,
            'draft_id' => ($validated['draft_id'] ?? '') !== '' ? $validated['draft_id'] : null,
        ];
    }

    /** Fold every Step-2 draft slot into its saved form. */
    private function adoptPrintedTemplateDraft(Form $form, ?string $draftId, ?int $userId): void
    {
        if ($draftId === null) {
            return;
        }

        app(FormPrintTemplateService::class)->adoptDraft($form, $draftId, $userId);
    }

    /**
     * Queue baseline manual-filling schema generation for the form's active
     * template, mapped to its current version. Runs after the Step 2 draft is
     * adopted so it reflects the final printed layout. Marks the template
     * pending immediately so the builder can show progress. Never fails the
     * save.
     */
    private function dispatchManualSchema(Form $form): void
    {
        try {
            foreach (app(FormPrintTemplateService::class)->activeTemplates($form) as $template) {
                $template->forceFill([
                    'manual_schema_status' => 'pending',
                    'manual_schema_error' => null,
                ])->save();

                \App\Jobs\GenerateTemplateManualSchema::dispatch(
                    (int) $template->getKey(),
                    (int) $template->version,
                );
            }
        } catch (\Throwable $throwable) {
            report($throwable);
        }
    }

    private function manualSchemaStatus(Form $form): ?array
    {
        $templates = app(FormPrintTemplateService::class)->activeTemplates($form);
        if ($templates->isEmpty()) {
            return null;
        }
        $failed = $templates->first(fn ($template) => $template->manual_schema_status === 'failed');
        if ($failed !== null) {
            return ['manual_schema_status' => 'failed', 'manual_schema_error' => $failed->manual_schema_error];
        }
        if ($templates->contains(fn ($template) => $template->manual_schema_status !== 'ready')) {
            return ['manual_schema_status' => 'pending'];
        }

        $fields = app(\App\Services\ManualForm\ManualSchemaBuilder::class)->fieldsFor($form);
        foreach ($fields as $field) {
            if (! ($field['required'] ?? false) || ! in_array(
                $field['paper_support'],
                [\App\Forms\FieldType::PAPER_EXTRACT, \App\Forms\FieldType::PAPER_SIGNATURE],
                true,
            )) {
                continue;
            }
            $covered = $templates->contains(fn ($template) => collect($template->manual_schema['fields'] ?? [])
                ->contains(fn ($area) => ($area['key'] ?? null) === $field['key']
                    && ($area['writable_area'] ?? 'uncertain') !== 'missing'));
            if (! $covered) {
                return [
                    'manual_schema_status' => 'failed',
                    'manual_schema_error' => 'No printed template has writable space for '.$field['label'].'.',
                ];
            }
        }

        return ['manual_schema_status' => 'ready'];
    }

    /**
     * Enforce the form's field kit: special field types are only allowed when
     * the kit unlocks them, and every kit-required field key must be present
     * with the right type. 422s name the offending/missing keys so the admin
     * can fix the form in place.
     *
     * @param  array<string,mixed>  $validated
     */
    private function enforceFieldKit(array $validated, ?Form $form): void
    {
        $kit = $form
            ? \App\Forms\FieldKit::forForm($form)
            : ((($validated['system_function'] ?? '') !== '' && \App\Forms\FieldKit::has((string) $validated['system_function']))
                ? (string) $validated['system_function']
                : null);

        $allowedSpecial = array_flip(\App\Forms\FieldKit::types($kit));
        $byKey = collect($validated['fields'])->keyBy('field_key');

        foreach ($validated['fields'] as $field) {
            $type = (string) $field['field_type'];
            if (FieldType::isSpecial($type) && ! isset($allowedSpecial[$type])) {
                abort(response()->json([
                    'message' => '"'.FieldType::label($type).'" fields are only available on the form they belong to'
                        .($kit ? ' — this form\'s "'.\App\Forms\FieldKit::label($kit).'" category does not include them.' : '.'),
                ], 422));
            }

            // "Age - Computed from Birthday" is a Sign-Up-only autofill: it needs
            // a sibling birthday field, which only that form guarantees.
            if (($field['universal_key'] ?? '') === 'age_from_birthday' && $kit !== SystemFunction::SIGN_UP) {
                abort(response()->json([
                    'message' => 'The "'.UniversalField::label('age_from_birthday').'" autofill is only available on the Sign Up form.',
                ], 422));
            }
        }

        $missing = [];
        foreach (\App\Forms\FieldKit::required($kit) as $requiredKey => $requiredType) {
            $field = $byKey->get($requiredKey);
            $satisfied = $field && (string) $field['field_type'] === $requiredType;

            // The approved-events picker is matched by TYPE, not key: the
            // workplan handler resolves it by field_type (NewWorkplanHandler),
            // and its palette label ("Approved events") slugs to a different key
            // than `workplan_events`. So any field of that type satisfies it,
            // whatever its key. (Other required keys — email, first_name,
            // organization_id, … — are resolved by key/universal key, so they
            // stay key-matched.)
            if (! $satisfied && $requiredType === FieldType::WORKPLAN_EVENTS) {
                $satisfied = $byKey->contains(
                    fn ($f) => (string) $f['field_type'] === FieldType::WORKPLAN_EVENTS,
                );
            }

            if (! $satisfied) {
                $missing[] = $requiredKey.' ('.FieldType::label($requiredType).')';
            }
        }

        if ($missing !== []) {
            abort(response()->json([
                'message' => 'This form must include the following required field(s): '.implode(', ', $missing).'.',
            ], 422));
        }
    }

    /**
     * Provision/sync the form's own request type (see
     * RequestTypeService::resolveFormType). Forms bound to sign-up /
     * new-event / new-workplan are excepted — their handlers own dedicated
     * request flows — and lose the link if they had one from before binding.
     */
    private function syncRequestType(Form $form, ?int $userId): void
    {
        $excepted = in_array((string) $form->system_function, [
            SystemFunction::SIGN_UP,
            SystemFunction::NEW_EVENT,
            SystemFunction::NEW_WORKPLAN,
            SystemFunction::NEW_ORGANIZATION_REGISTRATION,
            SystemFunction::AFTER_EVENT_REPORT,
        ], true);

        if ($excepted) {
            if ($form->request_type_id !== null) {
                $form->forceFill(['request_type_id' => null])->save();
            }

            return;
        }

        app(\App\Services\RequestTypeService::class)->resolveFormType($form, $userId);
    }

    /**
     * Validate + normalise per-field `visible_when` conditions: the
     * controlling field must exist in this form, differ from the field
     * itself, carry a value (no layout/upload/signature controllers), the
     * comparison ops need an expected value, and chains must be acyclic.
     *
     * @param  array<int,array<string,mixed>>  $fields
     * @return array<int,array<string,mixed>>
     */
    private function normalizeVisibilityConditions(array $fields): array
    {
        $byKey = collect($fields)->keyBy('field_key');

        foreach ($fields as $i => $field) {
            $condition = (array) (($field['field_options'] ?? [])['visible_when'] ?? []);
            $controllerKey = trim((string) ($condition['field'] ?? ''));

            if ($controllerKey === '') {
                unset($fields[$i]['field_options']['visible_when']);

                continue;
            }

            $label = (string) ($field['field_label'] ?? $field['field_key']);
            $op = (string) ($condition['op'] ?? 'equals');

            if ($controllerKey === ($field['field_key'] ?? '')) {
                abort(response()->json(['message' => "\"{$label}\" cannot depend on itself."], 422));
            }

            $controller = $byKey->get($controllerKey);
            if (! $controller) {
                abort(response()->json(['message' => "\"{$label}\" depends on a field that does not exist on this form."], 422));
            }

            $controllerType = (string) ($controller['field_type'] ?? FieldType::TEXT);
            if (! FieldType::canControlVisibility($controllerType)) {
                abort(response()->json(['message' => "\"{$label}\" cannot depend on a layout, upload, signature or list-valued field."], 422));
            }

            if (in_array($op, ConditionEvaluator::VALUE_OPS, true)
                && trim((string) ($condition['value'] ?? '')) === '') {
                abort(response()->json(['message' => "The visibility condition on \"{$label}\" needs a comparison value."], 422));
            }

            $fields[$i]['field_options']['visible_when'] = [
                'field' => $controllerKey,
                'op' => $op,
                'value' => (string) ($condition['value'] ?? ''),
            ];
        }

        // Reject circular chains (A shown when B … B shown when A).
        $edges = [];
        foreach ($fields as $field) {
            $condition = ($field['field_options'] ?? [])['visible_when'] ?? null;
            if (is_array($condition)) {
                $edges[$field['field_key']] = (string) $condition['field'];
            }
        }
        foreach (array_keys($edges) as $start) {
            $seen = [];
            $node = $start;
            while (isset($edges[$node])) {
                if (in_array($node, $seen, true)) {
                    abort(response()->json(['message' => 'Field visibility conditions form a loop — break the circular dependency.'], 422));
                }
                $seen[] = $node;
                $node = $edges[$node];
            }
        }

        return array_values($fields);
    }

    /**
     * Normalise each activity-table field's `columns`: keep only well-formed
     * `{key,label,type}` entries (de-duped, order preserved) and, for keys that
     * still exist on the live New Events form, refresh the `label`/`type` from
     * it. Keys no longer on that form keep their stored snapshot — the label
     * snapshot is what keeps a stale column printing its heading.
     *
     * @param  array<int,array<string,mixed>>  $fields
     * @return array<int,array<string,mixed>>
     */
    private function normalizeActivityTableColumns(array $fields): array
    {
        $liveByKey = collect($this->eventFieldChoices())->keyBy('key');

        foreach ($fields as $i => $field) {
            if ((string) ($field['field_type'] ?? '') !== FieldType::ACTIVITY_TABLE) {
                continue;
            }

            $columns = FieldType::activityTableColumns((array) ($field['field_options'] ?? []));
            $columns = array_map(function (array $column) use ($liveByKey) {
                $live = $liveByKey->get($column['key']);
                if ($live !== null) {
                    $column['label'] = $live['label'];
                    $column['type'] = $live['type'];
                }

                return $column;
            }, $columns);

            $fields[$i]['field_options']['columns'] = $columns;
        }

        return $fields;
    }

    /**
     * Keep image upload options as predictable scalars when persisted. An
     * image field holds exactly one picture (any legacy `multiple` flag is
     * dropped); only a photo set takes several, capped by `max_files`. The
     * `multiple` flag survives only on a dropdown, where it makes a multi-select.
     *
     * @param  array<int,array<string,mixed>>  $fields
     * @return array<int,array<string,mixed>>
     */
    private function normalizeImageOptions(array $fields): array
    {
        foreach ($fields as $i => $field) {
            $type = (string) ($field['field_type'] ?? '');
            // `multiple` only means something on a dropdown (multi-select).
            if ($type !== FieldType::SELECT) {
                unset($fields[$i]['field_options']['multiple']);
            } elseif (array_key_exists('multiple', (array) ($field['field_options'] ?? []))) {
                $fields[$i]['field_options']['multiple'] = FieldType::isMultiSelect($type, (array) $field['field_options']);
            }

            if (! in_array($type, [FieldType::IMAGE, FieldType::MULTI_IMAGE], true)) {
                continue;
            }

            $options = (array) ($fields[$i]['field_options'] ?? []);

            if ($type === FieldType::IMAGE) {
                unset($options['max_files']);
            } else {
                $maxFiles = (int) ($options['max_files'] ?? 5);
                $options['max_files'] = max(1, min(10, $maxFiles));
            }

            $fields[$i]['field_options'] = $options;
        }

        return $fields;
    }

    /**
     * Normalise + sanitize the printed-PDF template payload.
     *
     * @param  array<string,mixed>  $template
     * @return array{html:string, page:array{size:string, orientation:string}}
     */
    private function cleanPdfTemplate(array $template): array
    {
        $page = (array) ($template['page'] ?? []);
        $size = (string) ($page['size'] ?? 'a4');
        $size = in_array($size, ['a4', 'letter', 'legal'], true) ? $size : 'a4';
        $orientation = (string) ($page['orientation'] ?? 'portrait');
        $orientation = in_array($orientation, ['portrait', 'landscape'], true) ? $orientation : 'portrait';

        return [
            'html' => $this->sanitizeTemplateHtml((string) ($template['html'] ?? '')),
            'page' => ['size' => $size, 'orientation' => $orientation],
            'font' => $this->cleanFont((array) ($template['font'] ?? [])),
            'header' => $this->cleanHeader((array) ($template['header'] ?? [])),
            'footer' => $this->cleanFooter((array) ($template['footer'] ?? [])),
        ];
    }

    /**
     * The document's default font family + size. Family is whitelisted to the
     * dompdf-safe editor options; size is clamped to a sane px range.
     *
     * @param  array<string,mixed>  $font
     * @return array{family:string, size:string}
     */
    private function cleanFont(array $font): array
    {
        $default = "'Times New Roman', Times, serif";
        $allowed = [
            'Arial, Helvetica, sans-serif',
            "'Times New Roman', Times, serif",
            'Georgia, serif',
            "'Courier New', Courier, monospace",
            "'DejaVu Sans', sans-serif",
        ];
        $family = (string) ($font['family'] ?? $default);
        if (! in_array($family, $allowed, true)) {
            $family = $default;
        }

        $size = 12;
        if (preg_match('/^(\d{1,2})px$/', (string) ($font['size'] ?? ''), $m)) {
            $size = min(48, max(8, (int) $m[1]));
        }

        return ['family' => $family, 'size' => $size.'px'];
    }

    /**
     * Normalise the printed footer (image only, for now).
     *
     * @param  array<string,mixed>  $footer
     * @return array<string,mixed>
     */
    private function cleanFooter(array $footer): array
    {
        return array_filter([
            'image' => $footer['image'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Authoritatively strip the template HTML down to the editor's allowed tag
     * and attribute set (headings, paragraphs, inline emphasis, lists, field
     * tokens, basic alignment) so only dompdf-safe markup is persisted.
     */
    private function sanitizeTemplateHtml(string $html): string
    {
        return \App\Forms\TemplateHtmlSanitizer::sanitize($html);
    }

    /**
     * Upsert form_descriptions from the posted fields, pruning removed keys.
     *
     * @param  array<int,array<string,mixed>>  $fields
     */
    private function syncFields(Form $form, array $fields): void
    {
        $keys = [];
        foreach ($fields as $order => $field) {
            $keys[] = $field['field_key'];
            FormDescription::updateOrCreate(
                ['form_id' => $form->id, 'field_key' => $field['field_key']],
                [
                    'field_label' => $field['field_label'],
                    'field_type' => $field['field_type'],
                    'is_required' => (bool) ($field['is_required'] ?? false),
                    'field_order' => $order + 1,
                    'placeholder_hint' => $field['placeholder_hint'] ?? null,
                    'field_options' => $field['field_options'] ?? null,
                    'universal_key' => ($field['universal_key'] ?? '') !== '' ? $field['universal_key'] : null,
                ],
            );
        }

        FormDescription::where('form_id', $form->id)
            ->whereNotIn('field_key', $keys)
            ->delete();
    }

    /**
     * @param  array<string,mixed>  $header
     * @return array<string,mixed>
     */
    private function cleanHeader(array $header): array
    {
        $align = $header['align'] ?? 'center';
        $align = in_array($align, ['left', 'center', 'right'], true) ? $align : 'center';

        return array_filter([
            'image' => $header['image'] ?? null,
            'logo' => $header['logo'] ?? null,
            'title' => $header['title'] ?? null,
            'subtitle' => $header['subtitle'] ?? null,
            'align' => $align,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Normalise rows, keeping only known field keys and valid spans.
     *
     * @param  array<int,mixed>  $rows
     * @param  array<int,string>  $validKeys
     * @return array<int,array<string,mixed>>
     */
    private function cleanRows(array $rows, array $validKeys): array
    {
        $validKeys = array_flip($validKeys);
        $clean = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $columns = [];
            $hasField = false;
            foreach (($row['columns'] ?? []) as $col) {
                if (! is_array($col)) {
                    continue;
                }
                $fieldKeys = array_values(array_filter(
                    array_map('strval', (array) ($col['fields'] ?? [])),
                    fn ($k) => isset($validKeys[$k]),
                ));
                $hasField = $hasField || ! empty($fieldKeys);
                $columns[] = [
                    'span' => max(1, min(12, (int) ($col['span'] ?? 12))),
                    'fields' => $fieldKeys,
                ];
            }

            $header = trim((string) ($row['header'] ?? ''));
            $staticText = trim((string) ($row['static_text'] ?? ''));

            // Keep a row only when it carries something: a field, a header, or
            // static text. Fully-empty rows (e.g. drag-emptied) are dropped; a
            // header-only "section divider" row survives.
            if (! $hasField && $header === '' && $staticText === '') {
                continue;
            }

            $entry = ['columns' => $columns];
            if ($header !== '') {
                $entry['header'] = mb_substr($header, 0, 255);
            }
            if ($staticText !== '') {
                $entry['static_text'] = mb_substr($staticText, 0, 5000);
            }
            $clean[] = $entry;
        }

        return $clean;
    }

    /**
     * @return array<string,mixed>
     */
    private function blankEditorData(): array
    {
        return [
            'name' => '',
            'description_text' => '',
            'route_name' => '',
            'icon' => '',
            'semester_submission_limit' => null,
            'system_function' => '',
            'fields' => [],
            'rows' => [],
            'pdf_template' => [
                'html' => '',
                'page' => ['size' => 'a4', 'orientation' => 'portrait'],
                'font' => ['family' => "'Times New Roman', Times, serif", 'size' => '12px'],
                'header' => ['align' => 'center'],
                'footer' => [],
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function editorDataFromForm(Form $form): array
    {
        $layout = (array) ($form->layout ?? []);

        $fields = $form->fields()->get()->map(fn (FormDescription $f) => [
            'field_key' => $f->field_key,
            'field_label' => $f->field_label,
            'field_type' => $f->field_type,
            'is_required' => (bool) $f->is_required,
            'placeholder_hint' => $f->placeholder_hint,
            // field_options is always an assoc MAP. Cast to object so an empty
            // config serializes as `{}` instead of `[]` — on a JS array the
            // builder's `f.field_options.options = [...]` would set a named
            // property that JSON.stringify silently drops on save.
            'field_options' => (object) ($f->field_options ?? []),
            'universal_key' => $f->universal_key ?? '',
        ])->values()->all();

        $pdfTemplate = (array) ($form->pdf_template ?? []);

        // Migrate a legacy letterhead stored on layout.header into the template
        // header, so forms authored before the move don't lose it.
        $header = (array) ($pdfTemplate['header'] ?? ($layout['header'] ?? ['align' => 'center']));

        return [
            'name' => $form->name,
            'description_text' => $form->description_text,
            'route_name' => $form->route_name,
            'icon' => (string) ($form->icon ?? ''),
            'semester_submission_limit' => $form->semester_submission_limit,
            'system_function' => (string) ($form->system_function ?? ''),
            'fields' => $fields,
            'rows' => $layout['rows'] ?? [],
            'pdf_template' => [
                'html' => (string) ($pdfTemplate['html'] ?? ''),
                'page' => [
                    'size' => (string) ($pdfTemplate['page']['size'] ?? 'a4'),
                    'orientation' => (string) ($pdfTemplate['page']['orientation'] ?? 'portrait'),
                ],
                'font' => [
                    'family' => (string) ($pdfTemplate['font']['family'] ?? "'Times New Roman', Times, serif"),
                    'size' => (string) ($pdfTemplate['font']['size'] ?? '12px'),
                ],
                'header' => $header,
                'footer' => (array) ($pdfTemplate['footer'] ?? []),
            ],
        ];
    }

    /**
     * Admin preview of a form (and, optionally, its printed document), bypassing
     * the live page's `is_active`/`is_published` gates. Resolves by form id so
     * drafts without a `route_name` still preview.
     */
    public function preview(Request $request, Form $form)
    {
        // "Preview printed document": render the PDF template with sample/blank
        // values inline, without persisting a submission or generated document.
        if ($request->query('document')) {
            $templates = app(\App\Services\FormPrintTemplateService::class)->activeTemplates($form);
            $selectedId = $request->query('template');
            abort_if($selectedId !== null && (! ctype_digit((string) $selectedId) || (int) $selectedId <= 0), 404);
            $template = $selectedId === null
                ? $templates->first()
                : $templates->first(fn ($item) => (int) $item->getKey() === (int) $selectedId);
            abort_if($selectedId !== null && $template === null, 404);
            if ($template !== null) {
                $fields = $form->fields()->get();
                $data = app(\App\Forms\DocxTemplateData::class)->build(
                    $this->sampleValues($fields),
                    $fields,
                    $request->user()?->profile()->first(),
                    \App\Support\OrganizationField::resolveOrganization($request->user()),
                );
                $docx = app(\App\Services\DocxTemplateService::class);
                $populated = $docx->populate($template, $data['values'], $data['images'], $data['tables']);
                try {
                    $pdf = $docx->toPdf($populated);
                    $contents = \Illuminate\Support\Facades\File::get($pdf);
                } finally {
                    \Illuminate\Support\Facades\File::deleteDirectory(dirname($populated));
                }

                return response($contents, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="preview-'.\Illuminate\Support\Str::slug((string) ($template->template_name ?: $form->name)).'.pdf"',
                ]);
            }

            $pdfTemplate = (array) ($form->pdf_template ?? []);
            $templateHtml = (string) ($pdfTemplate['html'] ?? '');

            if (trim($templateHtml) === '') {
                abort(404, 'This form has no printed template yet.');
            }

            $fields = $form->fields()->get();
            $page = (array) ($pdfTemplate['page'] ?? []);
            $size = (string) ($page['size'] ?? 'a4');
            $size = in_array($size, ['a4', 'letter', 'legal'], true) ? $size : 'a4';
            $orientation = (string) ($page['orientation'] ?? 'portrait');
            $orientation = in_array($orientation, ['portrait', 'landscape'], true) ? $orientation : 'portrait';

            $body = app(\App\Forms\PdfTemplateRenderer::class)->render(
                $templateHtml,
                $this->sampleValues($fields),
                $fields,
                organization: \App\Support\OrganizationField::resolveOrganization($request->user()),
            );

            $html = view('documents.form-template-pdf', [
                'form' => $form,
                'body' => $body,
                'page' => $page,
                'font' => (array) ($pdfTemplate['font'] ?? []),
                'header' => (array) ($pdfTemplate['header'] ?? []),
                'footer' => (array) ($pdfTemplate['footer'] ?? []),
            ])->render();

            return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
                ->setPaper($size, $orientation)
                ->stream('preview-'.($form->route_name ?: $form->getKey()).'.pdf');
        }

        $fields = $form->fields()->get();

        return view('pages.form.render', [
            'title' => $form->name.' (Preview)',
            'form' => $form,
            'fields' => $fields,
            'special' => \App\Forms\SpecialFieldData::resolve($request->user(), $fields, $form),
            'preview' => true,
            'previewTemplates' => app(\App\Services\FormPrintTemplateService::class)->activeTemplates($form),
        ]);
    }

    /**
     * Sample values for the printed-document preview: a readable placeholder per
     * fillable field, keyed by field_key.
     *
     * @param  \Illuminate\Support\Collection<int,FormDescription>  $fields
     * @return array<string,mixed>
     */
    private function sampleValues($fields): array
    {
        $values = [];
        $skip = [
            FieldType::SIGNATURE, FieldType::PASSWORD, FieldType::ID_SCAN,
            FieldType::MULTI_IMAGE, FieldType::TABLE_INPUT, FieldType::ACTIVITY_TABLE,
        ];
        foreach ($fields as $field) {
            if (FieldType::isPresentational($field->field_type) || FieldType::isFileLike($field->field_type)
                || in_array($field->field_type, $skip, true)) {
                continue;
            }
            $values[$field->field_key] = '['.$field->field_label.']';
        }

        return $values;
    }
}
