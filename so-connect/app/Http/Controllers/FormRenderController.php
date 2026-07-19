<?php

namespace App\Http\Controllers;

use App\Forms\ConditionEvaluator;
use App\Forms\FieldType;
use App\Forms\FormRenderContext;
use App\Forms\SystemFunction;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\DocumentGenerationService;
use App\Support\OrganizationField;
use App\Support\SignatureImage;
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

        // The Sign Up form is a public page (like the old signup wizard);
        // every other form is gated to organization officers.
        abort_unless(
            $form->system_function === SystemFunction::SIGN_UP || Form::isAccessibleBy($request->user()),
            403,
            'Form pages are available to organization officers only.',
        );

        $context = FormRenderContext::build($form, $request);
        $fields = $context['fields'];
        $prefill = $context['prefill'];
        $hidden = $context['hidden'];

        // The public sign-up form pre-fills from the landing-page Google popup's
        // query params (google_id/first_name/last_name/email); google_id rides
        // along as a hidden input the SignUp approval reads.
        if ($form->system_function === SystemFunction::SIGN_UP) {
            [$prefill, $hidden] = $this->signupQueryPrefill($request, $fields, $prefill);
        }

        // The calendar's "Create Event" action links here with the clicked day
        // as ?target_date=Y-m-d; prefill it when the form exposes that field.
        if ($form->system_function === SystemFunction::NEW_EVENT) {
            $prefill = self::applyTargetDatePrefill($request, $fields, $prefill);
        }

        return view('pages.form.render', array_merge($context, [
            'title' => $form->name,
            'prefill' => $prefill,
            'hidden' => $hidden,
            'recentSubmissions' => $this->recentSubmissions($request, $form),
        ]));
    }

    /**
     * Merge the landing-page Google popup's query params into the sign-up
     * form's prefill, mapping email/first_name/last_name onto whichever field
     * carries the matching universal key (or that literal field_key), and
     * returning google_id as a hidden input.
     *
     * @param  \Illuminate\Support\Collection<int,\App\Models\Form\FormDescription>  $fields
     * @param  array<string,mixed>  $prefill
     * @return array{0:array<string,mixed>,1:array<string,string>}
     */
    private function signupQueryPrefill(Request $request, $fields, array $prefill): array
    {
        $map = [
            'email' => (string) $request->query('email', ''),
            'first_name' => (string) $request->query('first_name', ''),
            'last_name' => (string) $request->query('last_name', ''),
        ];

        foreach ($fields as $field) {
            foreach ($map as $wellKnown => $value) {
                if ($value === '') {
                    continue;
                }
                if ($field->universal_key === $wellKnown || $field->field_key === $wellKnown) {
                    $prefill[$field->field_key] = $value;
                }
            }
        }

        $hidden = [];
        $googleId = (string) $request->query('google_id', '');
        if ($googleId !== '') {
            $hidden['google_id'] = $googleId;
        }

        return [$prefill, $hidden];
    }

    /**
     * Prefill the new_event form's target_date field from the ?target_date query
     * param (the calendar passes the clicked day). Shared with the calendar
     * drawer, which embeds this form inline.
     *
     * @param  \Illuminate\Support\Collection<int,\App\Models\Form\FormDescription>  $fields
     * @param  array<string,mixed>  $prefill
     * @return array<string,mixed>
     */
    public static function applyTargetDatePrefill(Request $request, $fields, array $prefill): array
    {
        $targetDate = (string) $request->query('target_date', '');
        if ($targetDate !== '' && $fields->firstWhere('field_key', 'target_date')) {
            try {
                $prefill['target_date'] = \Illuminate\Support\Carbon::parse($targetDate)->toDateString();
            } catch (\Carbon\Exceptions\InvalidFormatException) {
                // Ignore an unparseable query value; the user picks a date.
            }
        }

        return $prefill;
    }

    /**
     * The signed-in user's latest requests for this form, with their decision
     * state — the pending/approved/rejected(+reason) feedback loop of the
     * request lifecycle. Rejected → the user simply fills the form again.
     *
     * @return array<int,array{requested_at:?string, status:string, reason:string}>
     */
    private function recentSubmissions(Request $request, Form $form): array
    {
        $user = $request->user();
        if (! $user) {
            return [];
        }

        $requests = \App\Models\Request::query()
            ->where('form_id', (int) $form->getKey())
            ->where('requested_by', (int) $user->getKey())
            ->orderByDesc('requested_at')
            ->limit(5)
            ->get();

        if ($requests->isEmpty()) {
            return [];
        }

        $approvals = \App\Models\Approval::query()
            ->whereIn('request', $requests->pluck('request_id'))
            ->get()
            ->keyBy('request');

        return $requests->map(function (\App\Models\Request $req) use ($approvals) {
            $approval = $approvals->get($req->getKey());

            return [
                'requested_at' => optional($req->requested_at)->format('M j, Y g:i A'),
                'status' => $approval === null ? 'pending' : ($approval->is_rejected ? 'rejected' : 'approved'),
                'reason' => (string) ($approval?->rejection_reason ?? ''),
            ];
        })->all();
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
    public function submit(Request $request, string $routeName, DocumentGenerationService $docService)
    {
        $form = Form::query()
            ->where('route_name', $routeName)
            ->where('is_active', true)
            ->firstOrFail();

        // The Sign Up form is a public page (like the old signup wizard);
        // every other form is gated to organization officers.
        abort_unless(
            $form->system_function === SystemFunction::SIGN_UP || Form::isAccessibleBy($request->user()),
            403,
            'Form pages are available to organization officers only.',
        );

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
            $options = (array) ($field->field_options ?? []);
            $fieldRules = FieldType::validationRules(
                $field->field_type,
                (bool) $field->is_required,
                $options,
            );
            if (! empty($fieldRules)) {
                $rules[$field->field_key] = $fieldRules;
            }
            // Element rules for array-valued types (lists, tables, photo sets).
            foreach (FieldType::nestedValidationRules($field->field_type, $options) as $suffix => $nested) {
                $rules[$field->field_key.'.'.$suffix] = $nested;
            }
            // The ID-scan wizard's photo captures ride along as fixed inputs.
            if ($field->field_type === FieldType::ID_SCAN) {
                $rules['id_photo_front'] = ['nullable', 'file', 'mimes:jpeg,jpg,png,heic,heif', 'max:5120'];
                $rules['id_photo_back'] = ['nullable', 'file', 'mimes:jpeg,jpg,png,heic,heif', 'max:5120'];
            }
        }

        $validated = $request->validate($rules);

        $payload = [];
        $waiverValidations = [];
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

            if ($type === FieldType::MULTI_IMAGE) {
                $paths = [];
                foreach ((array) $request->file($key, []) as $file) {
                    if ($file !== null) {
                        $paths[] = $file->store(
                            'form-uploads/'.$form->route_name,
                            (string) config('documents.disk', 'public'),
                        );
                    }
                }
                $payload[$key] = $paths;
                continue;
            }

            if ($type === FieldType::PASSWORD) {
                // Stored pre-hashed: neither the submission payload nor the
                // approval request ever carries the plaintext.
                $raw = (string) ($validated[$key] ?? '');
                $payload[$key] = $raw !== '' ? \Illuminate\Support\Facades\Hash::make($raw) : null;
                continue;
            }

            if ($type === FieldType::ID_SCAN) {
                $payload[$key] = $validated[$key] ?? null;
                foreach (['id_photo_front', 'id_photo_back'] as $photoKey) {
                    $payload[$photoKey] = $request->hasFile($photoKey)
                        ? $request->file($photoKey)->store(
                            'form-uploads/'.$form->route_name,
                            (string) config('documents.disk', 'public'),
                        )
                        : null;
                }
                continue;
            }

            if ($type === FieldType::WAIVER_SCAN) {
                // Store the scanned waiver + authoritatively re-validate it
                // server-side (best-effort; never blocks the submission).
                $result = app(\App\Services\WaiverSubmissionService::class)->process($validated[$key] ?? null, $field);
                $payload[$key] = $result['path'];
                if ($result['validation'] !== null) {
                    $waiverValidations[$key] = $result['validation'];
                }
                continue;
            }

            if ($type === FieldType::COMPUTED) {
                // Recomputed below from the assembled payload.
                $payload[$key] = null;
                continue;
            }

            if ($type === FieldType::TEXT_LIST) {
                $payload[$key] = array_values(array_filter(
                    array_map(fn ($v) => trim((string) $v), (array) ($validated[$key] ?? [])),
                    fn ($v) => $v !== '',
                ));
                continue;
            }

            if ($type === FieldType::TABLE_INPUT) {
                $payload[$key] = $this->normalizeTableRows(
                    (array) ($validated[$key] ?? []),
                    (array) ($field->field_options ?? []),
                );
                continue;
            }

            if ($type === FieldType::WORKPLAN_EVENTS) {
                $payload[$key] = array_values(array_unique(array_map('intval', (array) ($validated[$key] ?? []))));
                continue;
            }

            // A single checkmark (no option list) is a boolean the browser omits
            // entirely when unticked. Store it as 1 (checked) / 0 (unchecked) so
            // scoring-rule conditions can reliably test it (`field:key = 1`/`= 0`).
            // Checkbox groups keep their submitted array of chosen values.
            if ($type === FieldType::CHECKBOX
                && FieldType::optionValues((array) ($field->field_options ?? [])) === []) {
                $payload[$key] = ! empty($validated[$key] ?? null) ? 1 : 0;
                continue;
            }

            $payload[$key] = $validated[$key] ?? null;
        }

        // Stash the authoritative waiver re-validation results for the review UI.
        if ($waiverValidations !== []) {
            $payload['_waiver_validation'] = $waiverValidations;
        }

        // The public sign-up form carries a hidden google_id from the OAuth
        // popup; the SignUp approval reads it off the request payload.
        if ($form->system_function === SystemFunction::SIGN_UP) {
            $googleId = trim((string) $request->input('google_id', ''));
            if ($googleId !== '') {
                $payload['google_id'] = $googleId;
            }
        }

        // Derived values (table row totals, computed fields) are recomputed
        // server-side — whatever the client posted for them is overwritten.
        $payload = \App\Forms\FieldCompute::apply($fields, $payload);

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

        // Bound forms keep the form's own org scoping (previous behavior);
        // a plain form's submission belongs to the submitter's organization —
        // the approval-time org check and the scoring joins both key on it.
        $submissionOrgId = $handler
            ? $form->organization_id
            : ($organization?->getKey() ?? $form->organization_id);

        $submission = FormSubmission::query()->create([
            'form_id' => (int) $form->getKey(),
            'organization_id' => $submissionOrgId,
            'submitted_by' => $user ? (int) $user->getKey() : null,
            'payload' => $payload,
            'submitted_at' => now(),
        ]);

        // Kit side effects (event linking, posts-wall media mirroring).
        \App\Forms\FieldKit::afterSubmit($form, $submission, $payload);

        if ($handler) {
            return $handler->handle($form, $submission, $payload, $request);
        }

        // Plain forms follow the request-approval lifecycle: the submission
        // waits as a pending request on the form's own admin request page;
        // the document is generated when an admin approves, and a rejection
        // sends the submitter back to this form.
        $docService->createDocumentGenerationRequest(
            $submissionOrgId ? (int) $submissionOrgId : null,
            (int) $submission->getKey(),
            (int) $form->getKey(),
            $user ? (int) $user->getKey() : 0,
        );

        return redirect()->route('forms.render', $form->route_name)
            ->with('success', $form->name.' submitted for approval — the document will be generated once an admin approves it.');
    }

    /**
     * Keep only the declared columns of each submitted table row, dropping
     * rows with no values at all. Row totals are recomputed later by
     * {@see \App\Forms\FieldCompute}.
     *
     * @param  array<int,mixed>  $rows
     * @param  array<string,mixed>  $options
     * @return array<int,array<string,mixed>>
     */
    private function normalizeTableRows(array $rows, array $options): array
    {
        $columns = FieldType::tableColumns($options);
        if ($columns === []) {
            return [];
        }

        $clean = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cleanRow = [];
            $hasValue = false;
            foreach ($columns as $column) {
                $value = $row[$column['key']] ?? null;
                $value = is_scalar($value) ? trim((string) $value) : null;
                $cleanRow[$column['key']] = ($value === '' ? null : $value);
                $hasValue = $hasValue || ($cleanRow[$column['key']] !== null);
            }
            if ($hasValue) {
                $clean[] = $cleanRow;
            }
        }

        return $clean;
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
