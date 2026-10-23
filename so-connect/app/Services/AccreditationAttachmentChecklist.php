<?php

namespace App\Services;

use App\Forms\FieldType;
use App\Forms\SystemFunction;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Treats every required upload field on the accreditation form as one item in
 * its required-attachment checklist. Administrators can therefore change the
 * checklist with the existing form builder instead of maintaining a second,
 * disconnected configuration screen.
 */
class AccreditationAttachmentChecklist
{
    /** @return Collection<int,\App\Models\Form\FormDescription> */
    public function requirements(Form $form): Collection
    {
        if ((string) $form->system_function !== SystemFunction::ORG_ACCREDITATION) {
            return collect();
        }

        return $form->fields()
            ->where('is_required', true)
            ->whereIn('field_type', [FieldType::FILE, FieldType::IMAGE, FieldType::MULTI_IMAGE])
            ->get();
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<int,array{key:string,label:string,attached:bool}>
     */
    public function status(Form $form, array $payload): array
    {
        return $this->requirements($form)
            ->map(fn ($field) => [
                'key' => (string) $field->field_key,
                'label' => (string) $field->field_label,
                'attached' => $this->hasAttachment($payload[$field->field_key] ?? null),
            ])
            ->values()
            ->all();
    }

    /** @param array<string,mixed> $payload */
    public function validateSubmission(Form $form, array $payload): void
    {
        $missing = array_values(array_filter($this->status($form, $payload), fn ($item) => ! $item['attached']));

        if ($missing === []) {
            return;
        }

        throw ValidationException::withMessages([
            'accreditation_attachments' => array_map(
                fn ($item) => $item['label'].' is required for accreditation.',
                $missing,
            ),
        ]);
    }

    public function assertApprovable(FormSubmission $submission): void
    {
        $form = $submission->form ?: Form::query()->find($submission->form_id);
        if (! $form || (string) $form->system_function !== SystemFunction::ORG_ACCREDITATION) {
            return;
        }

        $missing = array_values(array_filter(
            $this->status($form, (array) ($submission->payload ?? [])),
            fn ($item) => ! $item['attached'],
        ));

        if ($missing !== []) {
            throw new RuntimeException(
                'Accreditation cannot be approved. Missing required attachment(s): '
                .implode(', ', array_column($missing, 'label')).'.'
            );
        }
    }

    private function hasAttachment(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->hasAttachment($item)) {
                    return true;
                }
            }

            return false;
        }

        return is_string($value) && trim($value) !== '';
    }
}
