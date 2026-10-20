<?php

namespace App\Rules;

use App\Http\Controllers\Auth\GoogleLinkController;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;

/**
 * A submitted google_id must be one this session verified through the Google
 * OAuth popup. Without this, any client could post an arbitrary google_id —
 * which links that Google account and marks the email as verified.
 */
class VerifiedGoogleId implements ValidationRule
{
    public function __construct(private readonly Request $request) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! GoogleLinkController::isVerified($this->request, $value)) {
            $fail('The Google account link has expired. Please sign in with Google again.');
        }
    }
}
