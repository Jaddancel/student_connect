<?php

namespace App\Forms;

use App\Models\Form;
use App\Models\FormSubmission;

/**
 * Registry of "field kits": named sets of special palette field types that are
 * only offered on the form carrying the kit, plus the field keys that form is
 * required to include. One mechanism serves both system-function forms (the
 * kit is the function key) and re-created legacy forms (`forms.field_kit`).
 *
 * Mirrors the code-defined-catalog style of {@see SystemFunction} and
 * {@see \App\Support\UniversalField}.
 */
final class FieldKit
{
    /**
     * @return array<string, array{label:string, types:string[], required:array<string,string>}>
     */
    public static function catalog(): array
    {
        return [
            // ---- System-function kits (kit key === SystemFunction key) ----
            SystemFunction::SIGN_UP => [
                'label' => 'Sign Up fields',
                'types' => [
                    FieldType::ID_SCAN,
                    FieldType::ORG_SELECT,
                    FieldType::POSITION_SELECT,
                    FieldType::PASSWORD,
                ],
                'required' => [
                    'email' => FieldType::EMAIL,
                    'first_name' => FieldType::TEXT,
                    'last_name' => FieldType::TEXT,
                    'organization_id' => FieldType::ORG_SELECT,
                    'position' => FieldType::POSITION_SELECT,
                    'password' => FieldType::PASSWORD,
                ],
            ],
            SystemFunction::NEW_EVENT => [
                'label' => 'New Event fields',
                'types' => [FieldType::ORG_SELECT, FieldType::WAIVER_SCAN],
                'required' => [
                    'organization_id' => FieldType::ORG_SELECT,
                    'title' => FieldType::TEXT,
                    'target_date' => FieldType::DATE,
                    'event_location' => FieldType::TEXT,
                    'event_start_time' => FieldType::TIME,
                    'event_end_time' => FieldType::TIME,
                ],
            ],
            SystemFunction::NEW_WORKPLAN => [
                'label' => 'Workplan fields',
                'types' => [FieldType::WORKPLAN_EVENTS, FieldType::ACTIVITY_TABLE],
                // Only the approved-events picker is mandatory; the handler
                // derives everything else from the selected plans. The submitter
                // isn't asked for their name here — it's already recorded
                // (FormSubmission.submitted_by) and shown to admins on the
                // activity-requests page.
                'required' => [
                    'workplan_events' => FieldType::WORKPLAN_EVENTS,
                ],
            ],
            SystemFunction::MEMBERSHIP_REGISTRATION => [
                'label' => 'Membership fields',
                'types' => [FieldType::ORG_SELECT],
                'required' => [
                    'organization_id' => FieldType::ORG_SELECT,
                ],
            ],

            // ---- Re-created legacy form kits ----
            'project_request' => [
                'label' => 'Project Request fields',
                'types' => [FieldType::ORG_SELECT],
                'required' => [
                    'organization_id' => FieldType::ORG_SELECT,
                ],
            ],
            'organization_recognition' => [
                'label' => 'Recognition fields',
                'types' => [
                    FieldType::ORG_SELECT,
                    FieldType::COMPUTED,
                    FieldType::WORKPLAN_SELECT,
                ],
                'required' => [
                    'organization_id' => FieldType::ORG_SELECT,
                ],
            ],
            'accomplishment_report' => [
                'label' => 'Accomplishment fields',
                'types' => [
                    FieldType::ORG_SELECT,
                    FieldType::EVENT_SELECT,
                    FieldType::MULTI_IMAGE,
                ],
                'required' => [
                    'organization_id' => FieldType::ORG_SELECT,
                ],
            ],
            'financial_report' => [
                'label' => 'Financial Report fields',
                'types' => [
                    FieldType::ORG_SELECT,
                    FieldType::TABLE_INPUT,
                    FieldType::COMPUTED,
                ],
                'required' => [
                    'organization_id' => FieldType::ORG_SELECT,
                ],
            ],
        ];
    }

    /**
     * @return string[]
     */
    public static function keys(): array
    {
        return array_keys(self::catalog());
    }

    public static function has(string $kit): bool
    {
        return array_key_exists($kit, self::catalog());
    }

    public static function label(string $kit): string
    {
        return self::catalog()[$kit]['label'] ?? $kit;
    }

    /**
     * The kit in effect for a form: its system function when bound, otherwise
     * the seeded `field_kit`. Null when the form carries no kit.
     */
    public static function forForm(?Form $form): ?string
    {
        if ($form === null) {
            return null;
        }

        $kit = (string) ($form->system_function ?? '') ?: (string) ($form->field_kit ?? '');

        return $kit !== '' && self::has($kit) ? $kit : null;
    }

    /**
     * Special field types the kit unlocks.
     *
     * @return string[]
     */
    public static function types(?string $kit): array
    {
        return $kit !== null ? (self::catalog()[$kit]['types'] ?? []) : [];
    }

    /**
     * Field keys the kit's form is required to include, as key => type.
     *
     * @return array<string,string>
     */
    public static function required(?string $kit): array
    {
        return $kit !== null ? (self::catalog()[$kit]['required'] ?? []) : [];
    }

    /**
     * Post-submission side effects the flat render pipeline doesn't know
     * about, run after the FormSubmission row exists but before the request
     * is filed: persisting the picked event on the submission row and
     * mirroring accomplishment photos onto the posts wall.
     *
     * Computed values are recomputed in the controller (they apply to any
     * form with computed fields, kit or not).
     *
     * @param  array<string,mixed>  $payload
     */
    public static function afterSubmit(Form $form, FormSubmission $submission, array $payload): void
    {
        $fields = $form->fields;

        foreach ($fields as $field) {
            // The chosen event backs the submission row (dedupe + reporting).
            if ($field->field_type === FieldType::EVENT_SELECT) {
                $eventId = (int) ($payload[$field->field_key] ?? 0);
                if ($eventId > 0) {
                    $submission->forceFill(['event_id' => $eventId])->save();
                }
            }

            // Accomplishment documentation photos feed the posts wall.
            if ($field->field_type === FieldType::MULTI_IMAGE
                && (($field->field_options['media_copy'] ?? '') === 'accomplishment')) {
                self::copyAccomplishmentMedia(
                    $submission,
                    (array) ($payload[$field->field_key] ?? []),
                    (string) ($payload['title'] ?? ''),
                );
            }
        }
    }

    /**
     * Mirror uploaded documentation photos into the posts wall media store,
     * as the legacy AccomplishmentReportController did.
     *
     * @param  array<int,mixed>  $paths
     */
    private static function copyAccomplishmentMedia(FormSubmission $submission, array $paths, string $activityTitle): void
    {
        $disk = \Illuminate\Support\Facades\Storage::disk((string) config('documents.disk', 'public'));

        foreach ($paths as $path) {
            $path = (string) $path;
            if ($path === '' || ! $disk->exists($path)) {
                continue;
            }

            $target = 'posts/media/accomplishment/'.\Illuminate\Support\Str::random(8).'-'.basename($path);
            try {
                $disk->copy($path, $target);
                \App\Models\AccomplishmentMedia::query()->create([
                    'form_submission_id' => (int) $submission->getKey(),
                    'organization_id' => $submission->organization_id,
                    'file_path' => $target,
                    'activity_title' => $activityTitle,
                    'submitted_at' => now(),
                ]);
            } catch (\Throwable) {
                // Media mirroring must never block a submission.
            }
        }
    }
}
