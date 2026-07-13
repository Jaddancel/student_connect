<?php

namespace App\Http\Controllers;

use App\Forms\ConditionEvaluator;
use App\Forms\FieldType;
use App\Forms\SystemFunction;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Organization;
use App\Services\DocumentGenerationService;
use App\Support\OrganizationField;
use App\Support\SignatureImage;
use App\Support\UniversalField;
use Illuminate\Http\Request;

/**
 * Generic renderer for WYSIWYG builder forms. One controller serves every form
 * defined through the builder, replacing bespoke per-form pages.
 *
 * Submissions are stored with a payload keyed by `field_key` — the compatibility
 * contract that keeps the admin approval pages working.
 */
class FormRenderController extends Controller
{
    public function show(Request $request, string $routeName)
    {
        $form = Form::query()
            ->where('route_name', $routeName)
            ->where('is_active', true)
            ->firstOrFail();

        abort_unless(Form::isAccessibleBy($request->user()), 403,
            'Form pages are available to organization officers only.');

        $fields = $form->fields()->get();
        $organization = OrganizationField::resolveOrganization($request->user());

        return view('pages.form.render', [
            'title' => $form->name,
            'form' => $form,
            'fields' => $fields,
            'prefill' => $this->profilePrefill($request, $fields, $organization),
            // Option list for any field mapped to the "Advisers" universal field.
            'advisers' => OrganizationField::advisers($organization),
            // Per-field visibility conditions for the client-side toggling.
            'conditions' => ConditionEvaluator::clientConditions($fields),
        ]);
    }

    /**
     * Build a [field_key => value] map of universal-field autofills. Profile-source
     * keys read from the signed-in user's profile; org-source keys (president /
     * auditor / secretary) read from their organization's current officeholders.
     * File/image fields are skipped (a browser cannot pre-populate <input
     * type=file>), and `adviser` has no autofill value (it's a dropdown choice).
     *
     * @param  \Illuminate\Support\Collection<int,\App\Models\Form\FormDescription>  $fields
     * @return array<string,mixed>
     */
    private function profilePrefill(Request $request, $fields, ?Organization $organization): array
    {
        // `profile` is a FK column on users and shadows the relation, so
        // `$user->profile` returns the id — load the related model explicitly.
        $profile = $request->user()?->profile()->first();

        $prefill = [];
        foreach ($fields as $field) {
            $key = $field->universal_key;
            if (! $key || FieldType::isFileLike($field->field_type)) {
                continue;
            }

            $value = UniversalField::isOrgField($key)
                ? OrganizationField::value($organization, $key)
                : ($profile ? UniversalField::valueFor($profile, $key) : null);

            if ($value !== null && $value !== '') {
                $prefill[$field->field_key] = $value;
            }
        }

        return $prefill;
    }

    public function submit(Request $request, string $routeName, DocumentGenerationService $docService)
    {
        $form = Form::query()
            ->where('route_name', $routeName)
            ->where('is_active', true)
            ->firstOrFail();

        abort_unless(Form::isAccessibleBy($request->user()), 403,
            'Form pages are available to organization officers only.');

        // Defense in depth: an untemplated form must never accept a submission,
        // since it could not produce its printed document.
        $pdfTemplate = (array) ($form->pdf_template ?? []);
        if (trim((string) ($pdfTemplate['html'] ?? '')) === '') {
            abort(422, 'This form is not ready to accept submissions yet (no printed template).');
        }

        $fields = $form->fields()->get();

        // Conditional visibility is enforced server-side against the raw
        // input, regardless of what the client showed: hidden fields skip
        // validation entirely and their values are dropped from the payload.
        $visibility = ConditionEvaluator::visibilityMap(
            $fields,
            fn (string $key) => $request->input($key),
        );
        $isHidden = fn (string $key): bool => ($visibility[$key] ?? true) === false;

        // Build validation rules dynamically from the field catalog.
        $rules = [];
        foreach ($fields as $field) {
            if (FieldType::isPresentational($field->field_type) || $isHidden($field->field_key)) {
                continue;
            }
            $fieldRules = FieldType::validationRules(
                $field->field_type,
                (bool) $field->is_required,
                (array) ($field->field_options ?? []),
            );
            if (! empty($fieldRules)) {
                $rules[$field->field_key] = $fieldRules;
            }
        }

        $validated = $request->validate($rules);

        $payload = [];
        foreach ($fields as $field) {
            $key = $field->field_key;
            $type = $field->field_type;

            if (FieldType::isPresentational($type)) {
                continue;
            }

            if ($isHidden($key)) {
                $payload[$key] = null;
                continue;
            }

            if (FieldType::isFileLike($type)) {
                if ($request->hasFile($key)) {
                    $payload[$key] = $request->file($key)->store(
                        'form-uploads/'.$form->route_name,
                        (string) config('documents.disk', 'public'),
                    );
                } else {
                    $payload[$key] = null;
                }
                continue;
            }

            if ($type === FieldType::SIGNATURE) {
                $payload[$key] = $this->resolveSignature($validated[$key] ?? null, $form->route_name, $request);
                continue;
            }

            $payload[$key] = $validated[$key] ?? null;
        }

        $user = $request->user();

        // Persist any newly-typed adviser name so the org's dropdown offers it next time.
        $organization = OrganizationField::resolveOrganization($user);
        foreach ($fields as $field) {
            if ($field->universal_key === 'adviser') {
                OrganizationField::rememberAdviser($organization, $payload[$field->field_key] ?? null);
            }
        }

        // A form bound to a system function routes its submission into that
        // function's request-approval flow instead of generating immediately.
        $handler = SystemFunction::handlerForForm($form);
        if ($handler) {
            $handler->validatePayload($form, $payload, $request);
        }

        $submission = FormSubmission::query()->create([
            'form_id' => (int) $form->getKey(),
            'organization_id' => $form->organization_id,
            'submitted_by' => $user ? (int) $user->getKey() : null,
            'payload' => $payload,
            'submitted_at' => now(),
        ]);

        if ($handler) {
            return $handler->handle($form, $submission, $payload, $request);
        }

        try {
            $docService->generateFromSubmission(
                $submission->fresh('form'),
                null,
                $user ? (int) $user->getKey() : 0,
            );

            return redirect()->route('documents.index')
                ->with('success', $form->name.' submitted and the PDF has been generated.');
        } catch (\Throwable $e) {
            return redirect()->route('forms.render', $form->route_name)
                ->with('success', $form->name.' submitted, but PDF generation failed: '.$e->getMessage());
        }
    }

    /**
     * Resolve a submitted signature value to a stored path. A freshly-drawn
     * data-URL is persisted as a new PNG; the submitter's own saved profile
     * signature (offered as prefill) is kept as its existing path. Anything
     * else — notably an arbitrary path a client could inject — is dropped.
     */
    private function resolveSignature(?string $value, string $routeName, Request $request): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (str_starts_with($value, 'data:image')) {
            return SignatureImage::storeDataUrl($value, 'form-uploads/'.$routeName.'/signatures');
        }

        $profileSignature = $request->user()?->profile()->first()?->signature_path;

        return ($profileSignature !== null && $value === $profileSignature) ? $value : null;
    }
}
