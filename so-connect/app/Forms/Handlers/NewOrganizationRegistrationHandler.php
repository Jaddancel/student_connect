<?php

namespace App\Forms\Handlers;

use App\Forms\FieldType;
use App\Models\Approval;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use App\Models\User;
use App\Services\RequestTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * New Organization Registration: a public form submits a request to create a
 * new organization. Admin approval creates the organization and either
 * attaches existing president/officer accounts or issues invitations that
 * ultimately assign the respective roles.
 */
class NewOrganizationRegistrationHandler implements SystemFunctionHandler
{
    use ResolvesPayloadKeys;

    /** The action_type used for new-organization requests. */
    public const ACTION_TYPE = 12;

    /**
     * Canonical builder keys the approval side effect needs to create an
     * organization. They may be supplied as literal field_keys or as
     * universal_key mappings.
     */
    public const ORGANIZATION_KEYS = [
        'organization_name',
        'organization_initials',
        'organization_description',
        'organization_type',
    ];

    public function validatePayload(Form $form, array $payload, Request $request): void
    {
        $presidentEmail = $this->resolvePresidentEmail($form, $payload);
        if ($presidentEmail === null || $presidentEmail === '') {
            throw ValidationException::withMessages([
                'form' => 'This form is bound to the "'.\App\Forms\SystemFunction::label((string) $form->system_function)
                    .'" function but is missing a required "'.FieldType::label(FieldType::NEW_PRESIDENT_EMAIL).'" field.',
            ]);
        }

        $officerEmails = $this->resolveOfficerEmails($form, $payload);
        if ($officerEmails === []) {
            throw ValidationException::withMessages([
                'form' => 'This form is bound to the "'.\App\Forms\SystemFunction::label((string) $form->system_function)
                    .'" function but is missing a required "'.FieldType::label(FieldType::NEW_OFFICER_EMAIL).'" field.',
            ]);
        }

        if (in_array($presidentEmail, $officerEmails, true)) {
            throw ValidationException::withMessages([
                'officer_email' => 'The new officer email must be different from the new president email.',
            ]);
        }

        if (count($officerEmails) !== count(array_unique($officerEmails))) {
            throw ValidationException::withMessages([
                'officer_email' => 'Each new officer email must be different.',
            ]);
        }

        $this->requirePayloadKeys($form, $payload, self::ORGANIZATION_KEYS);

        $normalized = [
            'name' => $this->normalizeText($this->payloadValue($form, $payload, 'organization_name')),
            'initials' => $this->normalizeText($this->payloadValue($form, $payload, 'organization_initials')),
            'description' => $this->normalizeText($this->payloadValue($form, $payload, 'organization_description')),
            'type' => $this->normalizeText($this->payloadValue($form, $payload, 'organization_type')),
        ];

        if ($normalized['name'] === '') {
            throw ValidationException::withMessages([
                'form' => 'An organization name is required to register a new organization.',
            ]);
        }

        if ($normalized['initials'] === '') {
            throw ValidationException::withMessages([
                'form' => 'Organization initials are required to register a new organization.',
            ]);
        }

        $hasPending = ActionRequest::query()
            ->where('form_id', (int) $form->getKey())
            ->where('action_type', self::ACTION_TYPE)
            ->whereNotIn('request_id', Approval::query()->select('request')->whereNotNull('request'))
            ->where(function ($query) use ($presidentEmail, $officerEmails, $normalized) {
                $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.president_email')) = ?", [$presidentEmail])
                    ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.organization_name'))) = ?", [$normalized['name']]);

                foreach ($officerEmails as $officerEmail) {
                    $query->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.officer_email')) = ?", [$officerEmail])
                        ->orWhereRaw("JSON_CONTAINS(JSON_EXTRACT(payload, '$.officer_emails'), JSON_QUOTE(?))", [$officerEmail]);
                }
            })
            ->exists();

        if ($hasPending) {
            throw ValidationException::withMessages([
                'form' => 'A registration request for this organization name or email is already pending review.',
            ]);
        }
    }

    public function handle(Form $form, FormSubmission $submission, array $payload, Request $request): RedirectResponse
    {
        $presidentEmail = strtolower((string) $this->resolvePresidentEmail($form, $payload));
        $officerEmails = $this->resolveOfficerEmails($form, $payload);

        $requestTypeId = $form->request_type_id
            ? (int) $form->request_type_id
            : (int) app(RequestTypeService::class)->resolveSystemType(
                RequestType::SYSTEM_KEY_NEW_ORGANIZATION_REGISTRATION,
                'New Organization Registration Request',
                RequestType::CATEGORY_ORGANIZATION,
                $request->user()?->getKey(),
            )->getKey();

        ActionRequest::query()->create([
            'action' => 'new_organization_registration',
            'action_type' => self::ACTION_TYPE,
            'request_type_id' => $requestTypeId,
            'form_id' => (int) $form->getKey(),
            'organization_id' => null,
            'requested_by' => $request->user()?->getKey(),
            'payload' => array_merge($payload, [
                'president_email' => $presidentEmail,
                'president_is_registered' => $this->isRegisteredEmail($presidentEmail),
                // Keep the original scalar fields for existing request pages,
                // while retaining every duplicate officer field for approval.
                'officer_email' => $officerEmails[0],
                'officer_is_registered' => $this->isRegisteredEmail($officerEmails[0]),
                'officer_emails' => $officerEmails,
                'officer_registration_statuses' => array_map(
                    fn (string $email) => $this->isRegisteredEmail($email),
                    $officerEmails,
                ),
                'form_submission_id' => (int) $submission->getKey(),
            ]),
            'user' => $request->user()?->getKey(),
            'requested_at' => now(),
        ]);

        return redirect()->route('forms.render', $form->route_name)
            ->with('success', 'New organization registration submitted for review.');
    }

    /**
     * Find the submitted president email by looking for its special field type.
     */
    public function resolvePresidentEmail(Form $form, array $payload): ?string
    {
        return $this->resolveEmailForType($form, $payload, FieldType::NEW_PRESIDENT_EMAIL, 'president_email');
    }

    /** Find the submitted officer email by looking for its special field type. */
    public function resolveOfficerEmail(Form $form, array $payload): ?string
    {
        return $this->resolveOfficerEmails($form, $payload)[0] ?? null;
    }

    /**
     * Find every submitted officer email. Duplicate officer controls are
     * supported only by the New Organization Registration form.
     *
     * @param  array<string,mixed>  $payload
     * @return array<int,string>
     */
    public function resolveOfficerEmails(Form $form, array $payload): array
    {
        $emails = [];
        foreach ($form->fields as $field) {
            if ($field->field_type !== FieldType::NEW_OFFICER_EMAIL || ! array_key_exists($field->field_key, $payload)) {
                continue;
            }

            $value = $payload[$field->field_key];
            if (is_string($value) && trim($value) !== '') {
                $emails[] = strtolower(trim($value));
            }
        }

        if ($emails !== []) {
            return $emails;
        }

        return array_values(array_filter(
            array_map('strval', (array) ($payload['officer_emails'] ?? [$this->payloadValue($form, $payload, 'officer_email')])),
            fn (string $email) => $email !== '',
        ));
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function resolveEmailForType(Form $form, array $payload, string $fieldType, string $fallbackKey): ?string
    {
        foreach ($form->fields as $field) {
            if ($field->field_type === $fieldType && array_key_exists($field->field_key, $payload)) {
                $value = $payload[$field->field_key];

                return is_string($value) ? strtolower(trim($value)) : null;
            }
        }

        $legacy = $this->payloadValue($form, $payload, $fallbackKey);

        return is_string($legacy) ? strtolower(trim($legacy)) : null;
    }

    /**
     * A "registered" email belongs to an existing superadmin, admin, or officer.
     * Guest accounts (type 4) are not considered registered for this purpose.
     */
    public function isRegisteredEmail(string $email): bool
    {
        return User::query()
            ->where('user_email', $email)
            ->whereIn('user_type', [User::TYPE_SUPERADMIN, User::TYPE_ADMIN, User::TYPE_OFFICER])
            ->exists();
    }

    private function normalizeText(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
