<?php

namespace App\Forms\Handlers;

use App\Models\Form;
use Illuminate\Validation\ValidationException;

/**
 * Payload lookup shared by the system-function handlers: a well-known key is
 * satisfied by the form field mapped to that universal key, or by a field
 * whose own field_key matches it literally.
 */
trait ResolvesPayloadKeys
{
    /**
     * @param  array<string,mixed>  $payload
     */
    protected function payloadValue(Form $form, array $payload, string $key): mixed
    {
        foreach ($form->fields as $field) {
            if ($field->universal_key === $key && array_key_exists($field->field_key, $payload)) {
                $value = $payload[$field->field_key];
                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }

        return $payload[$key] ?? null;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  string[]  $keys
     *
     * @throws ValidationException listing every missing well-known key
     */
    protected function requirePayloadKeys(Form $form, array $payload, array $keys): array
    {
        $values = [];
        $missing = [];
        foreach ($keys as $key) {
            $value = $this->payloadValue($form, $payload, $key);
            if ($value === null || $value === '') {
                $missing[] = $key;
            } else {
                $values[$key] = $value;
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'form' => 'This form is bound to the "'.\App\Forms\SystemFunction::label((string) $form->system_function)
                    .'" function but is missing required field key(s): '.implode(', ', $missing)
                    .'. Add fields with these keys (or map them to the matching universal fields) in the Form Builder.',
            ]);
        }

        return $values;
    }
}
