<?php

namespace App\Services;

use App\Forms\FieldType;
use App\Models\ActivityWaiverReview;
use App\Models\FormSubmission;
use App\Models\Request as ActionRequest;

/**
 * Authoritative activity-approval gate for waiver attachments.
 *
 * New submissions normally contain server-side OCR results in
 * `_waiver_validation`. Legacy/imported requests may have an attachment but no
 * result; those remain blocked until an administrator records a manual review.
 */
final class ActivityWaiverApprovalGate
{
    /**
     * @return array{
     *   status:string,base_status:string,label:string,message:string,
     *   can_approve:bool,can_review:bool,attachment_count:int,
     *   failed_count:int,unvalidated_count:int,review:?ActivityWaiverReview
     * }
     */
    public function evaluate(ActionRequest $actionRequest): array
    {
        $payload = (array) ($actionRequest->payload ?? []);
        $submission = FormSubmission::query()->with('form')
            ->find((int) ($payload['submission_id'] ?? 0));
        $review = ActivityWaiverReview::query()
            ->with('reviewer.profileRecord')
            ->where('request_id', $actionRequest->getKey())
            ->first();

        if ($submission === null || $submission->form === null) {
            return $this->state('missing', $review, 0, 0, 0);
        }

        $submissionPayload = (array) ($submission->payload ?? []);
        $waiverFields = $submission->form->fields()
            ->where('field_type', FieldType::WAIVER_SCAN)
            ->get(['field_key']);

        $attachmentCount = 0;
        $failedCount = 0;
        $unvalidatedCount = 0;
        $allValidation = (array) ($submissionPayload['_waiver_validation'] ?? []);

        foreach ($waiverFields as $field) {
            $items = array_values(array_filter(
                (array) ($submissionPayload[$field->field_key] ?? []),
                fn ($value): bool => is_string($value) && trim($value) !== '',
            ));
            $attachmentCount += count($items);

            $validations = (array) ($allValidation[$field->field_key] ?? []);
            if (array_key_exists('valid', $validations)) {
                $validations = [$validations];
            }

            foreach ($items as $index => $_item) {
                $validation = $validations[$index] ?? null;
                $valid = is_array($validation) ? ($validation['valid'] ?? null) : null;
                if ($valid === false) {
                    $failedCount++;
                } elseif ($valid !== true) {
                    $unvalidatedCount++;
                }
            }
        }

        $baseStatus = match (true) {
            $attachmentCount === 0 => 'missing',
            $failedCount > 0 => 'failed',
            $unvalidatedCount > 0 => 'unvalidated',
            default => 'valid',
        };

        return $this->state(
            $baseStatus,
            $review,
            $attachmentCount,
            $failedCount,
            $unvalidatedCount,
        );
    }

    /** @return array<string,mixed> */
    private function state(
        string $baseStatus,
        ?ActivityWaiverReview $review,
        int $attachmentCount,
        int $failedCount,
        int $unvalidatedCount,
    ): array {
        $status = $baseStatus;
        if ($baseStatus === 'unvalidated' && $review !== null) {
            $status = $review->decision === ActivityWaiverReview::DECISION_APPROVED
                ? 'manually_approved'
                : 'manually_rejected';
        }

        [$label, $message] = match ($status) {
            'valid' => ['Valid', 'Every attached waiver passed automatic validation.'],
            'failed' => ['Failed', 'At least one attached waiver failed validation. Reject the request and ask the applicant to submit a corrected waiver.'],
            'unvalidated' => ['Unvalidated', 'The waiver scanner could not validate every attachment. An administrator must review it manually before approval.'],
            'manually_approved' => ['Manually Approved', 'An administrator reviewed and accepted the unvalidated waiver.'],
            'manually_rejected' => ['Manually Rejected', 'An administrator reviewed and rejected the unvalidated waiver.'],
            default => ['Missing', 'No waiver attachment was found for this activity request.'],
        };

        return [
            'status' => $status,
            'base_status' => $baseStatus,
            'label' => $label,
            'message' => $message,
            'can_approve' => in_array($status, ['valid', 'manually_approved'], true),
            'can_review' => $baseStatus === 'unvalidated',
            'attachment_count' => $attachmentCount,
            'failed_count' => $failedCount,
            'unvalidated_count' => $unvalidatedCount,
            'review' => $review,
        ];
    }
}
