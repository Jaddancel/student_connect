<?php

namespace App\Forms;

use App\Models\Form\FormDescription;
use App\Models\User;
use App\Services\SignatureProfileRegistrar;
use App\Services\SignatureReferenceService;
use Illuminate\Support\Collection;

/**
 * Registers signatures captured on a form submission into the signature
 * reference registry, so the verifier recognizes them on later forms.
 *
 * Without this, a signature drawn on a form was only ever stored as a file on
 * the submission payload — the registry stayed empty and every check reported
 * "no saved signatures to compare against".
 *
 * Each newly captured signature is filed under the name typed on the same form:
 * the nearest name-carrying field to the signature, so an adviser's signature is
 * registered as the adviser, not as whoever submitted the form. A signature on
 * the submitter's own signature field additionally becomes their profile
 * signature when they have none yet.
 */
class SignatureEnroller
{
    /** Universal keys that name a person who might sign. */
    private const NAME_KEYS = [
        'first_name', 'middle_name', 'last_name',
        'adviser', 'org_president', 'org_auditor', 'org_secretary',
    ];

    /** The parts of a person's name, in the order they read. */
    private const NAME_PARTS = ['first_name', 'middle_name', 'last_name'];

    /** Labels that contain "name" but never name a signatory. */
    private const NAME_LABEL_EXCLUDES = '/organi[sz]ation|\borg\b|event|file|document|form|user\s*name|username|program|course|school|company/i';

    public function __construct(
        private readonly SignatureReferenceService $references,
        private readonly SignatureProfileRegistrar $registrar,
    ) {}

    /**
     * @param  Collection<int,FormDescription>  $fields  the form's fields, in field_order
     * @param  array<string,mixed>  $payload  the resolved submission payload
     * @param  array<int,string>  $newKeys  field keys whose signature was captured on this submission
     */
    public function enroll(Collection $fields, array $payload, array $newKeys, ?User $user): void
    {
        if ($newKeys === []) {
            return;
        }

        $ordered = $fields->values();

        foreach ($newKeys as $key) {
            $path = $payload[$key] ?? null;
            if (! is_string($path) || $path === '') {
                continue;
            }

            $index = $ordered->search(fn (FormDescription $field) => $field->field_key === $key);
            if ($index === false) {
                continue;
            }

            /** @var FormDescription $field */
            $field = $ordered[$index];

            // Compare-mode fields are enforced at submit against a known signer;
            // there is no unknown owner to auto-profile here.
            if (FieldType::signatureExpectsMatch((array) ($field->field_options ?? []))) {
                continue;
            }

            // The submitter's own signature field: adopt it as their profile
            // signature when they have none, which registers it via the profile
            // sync. Falls through to a plain registration when there is no profile.
            if ($field->universal_key === 'signature' && $this->adoptAsProfileSignature($user, $path)) {
                continue;
            }

            $name = $this->resolveName($ordered, (int) $index, $payload, $user);
            if ($name === null) {
                continue;
            }

            // File the captured signature under its signatory as a real (flagged)
            // profile, so it is browsable and recognized from here on. Deduped by
            // name, so a signature already named via the interactive prompt — or
            // re-submitted — does not create a second profile.
            $this->registrar->register($name, $path);
        }
    }

    /**
     * Save the signature onto the submitter's profile when that profile has none
     * yet, mirroring it into the registry. Returns whether it was adopted.
     */
    private function adoptAsProfileSignature(?User $user, string $path): bool
    {
        $profile = $user?->profile()->first();
        if (! $profile || trim((string) $profile->signature_path) !== '') {
            return false;
        }

        $profile->update(['signature_path' => $path]);
        $this->references->syncFromProfile($profile->fresh());

        return true;
    }

    /**
     * The name to file a signature under: the value of the name field nearest to
     * it on the form, falling back to the submitter's own name.
     *
     * @param  Collection<int,FormDescription>  $ordered
     * @param  array<string,mixed>  $payload
     */
    private function resolveName(Collection $ordered, int $index, array $payload, ?User $user): ?string
    {
        foreach ($this->nameFieldsByProximity($ordered, $index) as $field) {
            // A person's name split across first/middle/last fields reads as one
            // name, so collect the whole set rather than just the nearest part.
            $name = in_array($field->universal_key, self::NAME_PARTS, true)
                ? $this->joinNameParts($ordered, $payload)
                : $this->scalar($payload[$field->field_key] ?? null);

            if ($name !== '') {
                return $name;
            }
        }

        $fallback = $this->submitterName($user);

        return $fallback !== '' ? $fallback : null;
    }

    /**
     * The form's name-carrying fields, best match first: the nearest name field
     * *above* the signature, then the nearest one below it. Preferring the field
     * above keeps a "Participant name / signature / Guardian name / signature"
     * form pairing each signature with its own signatory rather than with the
     * next one; a form whose name line sits under the signature still resolves,
     * from the second group.
     *
     * @param  Collection<int,FormDescription>  $ordered
     * @return array<int,FormDescription>
     */
    private function nameFieldsByProximity(Collection $ordered, int $index): array
    {
        $candidates = [];
        foreach ($ordered as $position => $field) {
            if (! $this->namesAPerson($field)) {
                continue;
            }
            $candidates[] = [
                'field' => $field,
                'sort' => [(int) $position > $index ? 1 : 0, abs((int) $position - $index)],
            ];
        }

        usort($candidates, fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        return array_column($candidates, 'field');
    }

    /**
     * Whether a field holds the name of a person who could be the signatory.
     */
    private function namesAPerson(FormDescription $field): bool
    {
        if (! in_array($field->field_type, [FieldType::TEXT, FieldType::SELECT, FieldType::SEARCH], true)) {
            return false;
        }

        if (in_array((string) $field->universal_key, self::NAME_KEYS, true)) {
            return true;
        }

        // An unbound field counts when its label reads as a person's name.
        if ($field->universal_key !== null && $field->universal_key !== '') {
            return false;
        }

        $label = (string) $field->field_label;

        return preg_match('/\bnames?\b/i', $label) === 1
            && preg_match(self::NAME_LABEL_EXCLUDES, $label) !== 1;
    }

    /**
     * First/middle/last name fields joined into one name.
     *
     * @param  Collection<int,FormDescription>  $ordered
     * @param  array<string,mixed>  $payload
     */
    private function joinNameParts(Collection $ordered, array $payload): string
    {
        $parts = [];
        foreach (self::NAME_PARTS as $part) {
            $field = $ordered->first(fn (FormDescription $f) => $f->universal_key === $part);
            if ($field) {
                $value = $this->scalar($payload[$field->field_key] ?? null);
                if ($value !== '') {
                    $parts[] = $value;
                }
            }
        }

        return trim(implode(' ', $parts));
    }

    private function submitterName(?User $user): string
    {
        $profile = $user?->profile()->first();

        return $profile ? trim($profile->first_name.' '.$profile->last_name) : '';
    }

    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
