<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared strong-password policy: min 8 chars with at least one uppercase,
 * lowercase, number and special character. Used by the first-login wizard
 * (PasswordChangeController) and the Settings password form so the two never
 * drift apart.
 */
class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;

        $failures = [];

        if (mb_strlen($password) < 8) {
            $failures[] = 'at least 8 characters';
        }
        if (! preg_match('/[A-Z]/', $password)) {
            $failures[] = 'at least one uppercase letter';
        }
        if (! preg_match('/[a-z]/', $password)) {
            $failures[] = 'at least one lowercase letter';
        }
        if (! preg_match('/[0-9]/', $password)) {
            $failures[] = 'at least one number';
        }
        if (! preg_match('/[^A-Za-z0-9]/', $password)) {
            $failures[] = 'at least one special character';
        }

        if ($failures !== []) {
            $fail('Password must contain '.implode(', ', $failures).'.');
        }
    }
}
