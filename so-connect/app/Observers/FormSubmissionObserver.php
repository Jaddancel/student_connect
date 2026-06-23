<?php

namespace App\Observers;

use App\Models\FormSubmission;
use App\Services\SignatureRecordService;

class FormSubmissionObserver
{
    public function __construct(private readonly SignatureRecordService $signatures) {}

    public function created(FormSubmission $submission): void
    {
        $payload = $submission->payload ?? [];

        $signaturePath = $payload['signature']
            ?? $payload['signaturePresident']
            ?? $payload['signature_path']
            ?? null;

        if (! $signaturePath) {
            return;
        }

        $submitterName = $this->resolveSubmitterName($payload);
        if (! $submitterName) {
            return;
        }

        try {
            $this->signatures->store(
                submitterName: $submitterName,
                signaturePath: $signaturePath,
                submissionId: (int) $submission->getKey(),
                userId: $submission->submitted_by ? (int) $submission->submitted_by : null,
            );
        } catch (\Throwable) {
            // Non-fatal: signature indexing must not block submission
        }
    }

    private function resolveSubmitterName(array $payload): ?string
    {
        // Try common name key patterns
        if (! empty($payload['name'])) {
            return (string) $payload['name'];
        }

        if (! empty($payload['full_name'])) {
            return (string) $payload['full_name'];
        }

        if (! empty($payload['first_name'])) {
            $parts = array_filter([
                $payload['first_name'],
                $payload['middle_name'] ?? null,
                $payload['last_name'] ?? null,
            ]);

            return implode(' ', $parts) ?: null;
        }

        if (! empty($payload['presidentName'])) {
            return (string) $payload['presidentName'];
        }

        if (! empty($payload['nameOfPresident'])) {
            return (string) $payload['nameOfPresident'];
        }

        if (! empty($payload['presidentname'])) {
            return (string) $payload['presidentname'];
        }

        return null;
    }
}
